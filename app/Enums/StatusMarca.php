<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum StatusMarca: string implements HasLabel
{
    case Ativa = 'ativa';
    case Pausada = 'pausada';
    case Descontinuada = 'descontinuada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Ativa => 'Ativa',
            self::Pausada => 'Pausada',
            self::Descontinuada => 'Descontinuada',
        };
    }
}
