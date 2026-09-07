<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StatusItem: string implements HasLabel
{
    case Ativo = 'ativo';
    case Pausado = 'pausado';
    case Descontinuado = 'descontinuado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Pausado => 'Pausado',
            self::Descontinuado => 'Descontinuado',
        };
    }
}
