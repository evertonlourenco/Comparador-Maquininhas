<?php

namespace App\Models;

use App\Enums\StatusItem;
use App\Enums\TipoEquipamento;
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

/**
 * So o que e do aparelho. Preco de adesao e aluguel nao moram aqui: a mesma
 * maquininha custa diferente em cada plano da marca (ver EquipamentoPlano).
 */
#[Table('equipamentos')]
#[Fillable([
    'marca_id', 'nome', 'slug', 'tipo', 'descricao', 'imagem_path',
    'tem_chip_gratis', 'imprime_comprovante', 'aceita_nfc', 'exige_celular',
    'status', 'ordem',
])]
class Equipamento extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'tipo' => TipoEquipamento::class,
            'tem_chip_gratis' => 'boolean',
            'imprime_comprovante' => 'boolean',
            'aceita_nfc' => 'boolean',
            'exige_celular' => 'boolean',
            'status' => StatusItem::class,
            'ordem' => 'integer',
        ];
    }

    /** imagem_path e caminho relativo no disco 'public' — nunca a URL pronta. */
    protected function imagemUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->imagem_path ? Storage::disk('public')->url($this->imagem_path) : null);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function planos(): BelongsToMany
    {
        return $this->belongsToMany(Plano::class, 'equipamento_plano')
            ->using(EquipamentoPlano::class)
            ->withPivot([
                'id', 'preco_adesao', 'preco_adesao_promocional', 'parcelas_adesao',
                'aluguel_mensal', 'observacao', 'status',
            ])
            ->withTimestamps();
    }

    public function cupons(): HasMany
    {
        return $this->hasMany(Cupom::class);
    }

    #[Scope]
    protected function ativos(Builder $query): void
    {
        $query->where('status', StatusItem::Ativo);
    }
}
