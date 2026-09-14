<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Etapa 13: as tres categorias de fonte que o monitor de mudancas
 * acompanha, cada uma com frequencia propria definida nos workflows do
 * repositorio do monitor (nao aqui): equipamento_cupom e tabela_taxas mais
 * seguido, contrato_credenciamento uma vez por semana.
 */
enum CategoriaFonteMonitorada: string implements HasLabel
{
    case TabelaTaxas = 'tabela_taxas';
    case EquipamentoCupom = 'equipamento_cupom';
    case ContratoCredenciamento = 'contrato_credenciamento';

    public function getLabel(): string
    {
        return match ($this) {
            self::TabelaTaxas => 'Tabela de taxas',
            self::EquipamentoCupom => 'Equipamento / cupom',
            self::ContratoCredenciamento => 'Contrato de credenciamento (PDF)',
        };
    }
}
