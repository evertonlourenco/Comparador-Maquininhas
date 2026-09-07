<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoDesconto: string implements HasLabel
{
    case Percentual = 'percentual';
    case Valor = 'valor';

    public function getLabel(): string
    {
        return match ($this) {
            self::Percentual => 'Percentual',
            self::Valor => 'Valor em reais',
        };
    }
}
