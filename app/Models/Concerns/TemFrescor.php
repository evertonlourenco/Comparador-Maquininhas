<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

/**
 * Regra 8: o selo de frescor degrada sozinho apos 45 dias.
 *
 * Nao existe coluna de frescor: ela congelaria e passaria a mentir no dia
 * seguinte. Tudo aqui e calculado sobre data_verificacao.
 */
trait TemFrescor
{
    public const DIAS_ATE_DEGRADAR = 45;

    protected function diasDesdeVerificacao(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->data_verificacao
            ? (int) $this->data_verificacao->startOfDay()->diffInDays(Carbon::today())
            : null);
    }

    protected function estaFresca(): Attribute
    {
        return Attribute::get(fn (): bool => $this->dias_desde_verificacao !== null
            && $this->dias_desde_verificacao <= self::DIAS_ATE_DEGRADAR);
    }

    /** 'fresca' | 'desatualizada' | 'sem_data'. */
    protected function nivelFrescor(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->dias_desde_verificacao === null) {
                return 'sem_data';
            }

            return $this->esta_fresca ? 'fresca' : 'desatualizada';
        });
    }

    #[Scope]
    protected function frescas(Builder $query): void
    {
        $query->where('data_verificacao', '>=', Carbon::today()->subDays(self::DIAS_ATE_DEGRADAR));
    }

    #[Scope]
    protected function desatualizadas(Builder $query): void
    {
        $query->where('data_verificacao', '<', Carbon::today()->subDays(self::DIAS_ATE_DEGRADAR));
    }
}
