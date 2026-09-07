<?php

namespace App\Models;

use App\Enums\StatusItem;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Custos 2 e 3 da regra 6: adesao e aluguel do aparelho dentro de um plano.
 * E pivot com carga propria porque o preco varia por plano para o mesmo
 * equipamento - o custo pertence ao par, nao a maquininha.
 */
#[Table('equipamento_plano')]
class EquipamentoPlano extends Pivot
{
    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'preco_adesao' => 'decimal:2',
            'preco_adesao_promocional' => 'decimal:2',
            'aluguel_mensal' => 'decimal:2',
            'status' => StatusItem::class,
        ];
    }

    /** O promocional quando houver, senao o cheio. */
    protected function precoAdesaoVigente(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->preco_adesao_promocional ?? $this->preco_adesao);
    }
}
