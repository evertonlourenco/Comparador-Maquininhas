<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IncideSobre: string implements HasLabel
{
    case Adesao = 'adesao';
    case Equipamento = 'equipamento';

    public function getLabel(): string
    {
        return match ($this) {
            self::Adesao => 'Adesao',
            self::Equipamento => 'Equipamento',
        };
    }
}
