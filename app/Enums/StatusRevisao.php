<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Etapa 10: fila de revisao humana das duas tabelas de staging
 * (propostas_recebidas, relatos_taxa_incorreta). So marca que alguem ja
 * olhou — nunca aciona publicacao nenhuma sozinho (regra 10).
 */
enum StatusRevisao: string implements HasLabel
{
    case Pendente = 'pendente';
    case Revisado = 'revisado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Revisado => 'Revisado',
        };
    }
}
