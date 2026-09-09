<?php

namespace App\Models;

use App\Enums\StatusMarca;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Table('marcas')]
#[Fillable([
    'adquirente_id', 'nome', 'slug', 'site_url', 'logo_path', 'descricao', 'youtube_video_id',
    'reclame_aqui_nota', 'reclame_aqui_url', 'reclame_aqui_consultado_em',
    'publica_tabela', 'aceita_relatos', 'status', 'ordem',
])]
class Marca extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'reclame_aqui_nota' => 'decimal:1',
            'reclame_aqui_consultado_em' => 'date',
            'publica_tabela' => 'boolean',
            'aceita_relatos' => 'boolean',
            'status' => StatusMarca::class,
            'ordem' => 'integer',
        ];
    }

    /** logo_path e caminho relativo no disco 'public' — nunca a URL pronta. */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null);
    }

    public function adquirente(): BelongsTo
    {
        return $this->belongsTo(Adquirente::class);
    }

    public function planos(): HasMany
    {
        return $this->hasMany(Plano::class);
    }

    public function equipamentos(): HasMany
    {
        return $this->hasMany(Equipamento::class);
    }

    public function cupons(): HasMany
    {
        return $this->hasMany(Cupom::class);
    }

    /** Regra 7: bandeiras aceitas. O grupo de cada uma e definido no pivot. */
    public function bandeiras(): BelongsToMany
    {
        return $this->belongsToMany(Bandeira::class, 'bandeira_marca')
            ->withPivot('grupo_bandeira_id')
            ->withTimestamps();
    }

    public function taxasDivulgadas(): HasMany
    {
        return $this->hasMany(TaxaDivulgada::class);
    }

    public function faixasReportadas(): HasMany
    {
        return $this->hasMany(FaixaReportada::class);
    }

    /**
     * Regra 4: marca que nao publica tabela (Cielo, Rede, GetNet, Stone) so
     * admite faixa_reportada. As duas classes nunca se misturam.
     */
    public function aceitaTaxaDivulgada(): bool
    {
        return (bool) $this->publica_tabela;
    }

    #[Scope]
    protected function ativas(Builder $query): void
    {
        $query->where('status', StatusMarca::Ativa);
    }

    /** Etapa 10: marca que aparece no select de /enviar-proposta. */
    #[Scope]
    protected function aceitamRelatos(Builder $query): void
    {
        $query->where('aceita_relatos', true);
    }

    public function propostasRecebidas(): HasMany
    {
        return $this->hasMany(PropostaRecebida::class);
    }

    public function relatosTaxaIncorreta(): HasMany
    {
        return $this->hasMany(RelatoTaxaIncorreta::class);
    }
}
