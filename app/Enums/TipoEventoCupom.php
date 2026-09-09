<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipoEventoCupom: string implements HasLabel
{
    case UsarCupom = 'usar_cupom';
    case CopiarCodigo = 'copiar_codigo';

    public function getLabel(): string
    {
        return match ($this) {
            self::UsarCupom => 'Usar cupom',
            self::CopiarCodigo => 'Copiar código',
        };
    }
}
