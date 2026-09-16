<?php

namespace App\Models;

use App\Enums\IncideSobre;
use App\Enums\StatusItem;
use App\Enums\TipoDesconto;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Regra 5. Nao existe campo de taxa aqui, de proposito: a taxa do afiliado e
 * igual a do site oficial. A vantagem do link e o desconto na adesao.
 *
 * equipamento_id nulo (o caso comum) = vale para o catalogo todo da marca.
 */
#[Table('cupons')]
#[Fillable([
    'marca_id', 'equipamento_id', 'codigo', 'descricao',
    'tipo_desconto', 'incide_sobre', 'valor',
    'valido_de', 'valido_ate', 'link_afiliado', 'termos', 'status', 'ordem',
    'link_ultimo_status', 'link_ultima_falha', 'link_quebrado', 'link_verificado_em',
    'link_confirmado_manualmente',
])]
class Cupom extends Model
{
    protected function casts(): array
    {
        return [
            'tipo_desconto' => TipoDesconto::class,
            'incide_sobre' => IncideSobre::class,
            'valor' => 'decimal:2',
            'valido_de' => 'date',
            'valido_ate' => 'date',
            'status' => StatusItem::class,
            'ordem' => 'integer',
            'link_quebrado' => 'boolean',
            'link_verificado_em' => 'datetime',
            'link_confirmado_manualmente' => 'boolean',
        ];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function equipamento(): BelongsTo
    {
        return $this->belongsTo(Equipamento::class);
    }

    /** Cupom sem equipamento vale para todo o catalogo da marca. */
    protected function valeParaCatalogoTodo(): Attribute
    {
        return Attribute::get(fn (): bool => $this->equipamento_id === null);
    }

    /**
     * Etapa 17: valido_ate nulo significa "sem prazo definido" (o comum, dito
     * pelo Everton), nao "vencido" - so vence quem tem data e ela passou.
     */
    protected function estaVigente(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === StatusItem::Ativo
            && $this->valido_de?->startOfDay()->lessThanOrEqualTo(Carbon::today())
            && ($this->valido_ate === null
                || $this->valido_ate->startOfDay()->greaterThanOrEqualTo(Carbon::today())));
    }

    /** Regra 5: ocultacao automatica ao vencer - so quando ha data pra vencer. */
    #[Scope]
    protected function vigentes(Builder $query): void
    {
        $query->where('status', StatusItem::Ativo)
            ->whereDate('valido_de', '<=', Carbon::today())
            ->where(fn (Builder $q) => $q->whereNull('valido_ate')
                ->orWhereDate('valido_ate', '>=', Carbon::today()));
    }

    #[Scope]
    protected function vencidos(Builder $query): void
    {
        $query->whereNotNull('valido_ate')->whereDate('valido_ate', '<', Carbon::today());
    }

    /** Etapa 16: link de afiliado que o verificador marcou como fora do ar. */
    #[Scope]
    protected function comLinkQuebrado(Builder $query): void
    {
        $query->where('link_quebrado', true);
    }
}
