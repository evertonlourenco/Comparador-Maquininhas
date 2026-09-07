<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Regra 10: nenhum dado coletado vai ao ar sem aprovacao humana no admin.
 */
enum StatusPublicacao: string implements HasLabel
{
    case Rascunho = 'rascunho';
    case Publicado = 'publicado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Publicado => 'Publicado',
        };
    }
}
