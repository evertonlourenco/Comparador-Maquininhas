<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Regra 3: plano e entidade propria, com tipo de enquadramento.
 */
enum TipoEnquadramento: string implements HasLabel
{
    case Automatico = 'automatico';
    case Escolhido = 'escolhido';
    case Negociado = 'negociado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Automatico => 'Automatico (faixa de faturamento define)',
            self::Escolhido => 'Escolhido (lojista opta e assume compromisso)',
            self::Negociado => 'Negociado',
        };
    }

    /** So o enquadramento automatico usa faixa de faturamento. */
    public function usaFaixaDeFaturamento(): bool
    {
        return $this === self::Automatico;
    }
}
