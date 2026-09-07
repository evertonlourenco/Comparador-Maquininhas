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

    protected function estaVigente(): Attribute
    {
        return Attribute::get(fn (): bool => $this->status === StatusItem::Ativo
            && $this->valido_de?->startOfDay()->lessThanOrEqualTo(Carbon::today())
            && $this->valido_ate?->startOfDay()->greaterThanOrEqualTo(Carbon::today()));
    }

    /** Regra 5: ocultacao automatica ao vencer. */
    #[Scope]
    protected function vigentes(Builder $query): void
    {
        $query->where('status', StatusItem::Ativo)
            ->whereDate('valido_de', '<=', Carbon::today())
            ->whereDate('valido_ate', '>=', Carbon::today());
    }

    #[Scope]
    protected function vencidos(Builder $query): void
    {
        $query->whereDate('valido_ate', '<', Carbon::today());
    }
}
