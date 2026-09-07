<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoEquipamento: string implements HasLabel
{
    case Pos = 'pos';
    case Smart = 'smart';
    case PinPad = 'pin_pad';
    case TapCelular = 'tap_celular';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pos => 'POS',
            self::Smart => 'Smart',
            self::PinPad => 'Pin pad',
            self::TapCelular => 'Tap no celular',
        };
    }
}
