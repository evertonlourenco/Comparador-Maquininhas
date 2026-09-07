<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Regra 8: nenhuma taxa entra sem fonte e data de verificacao.
 */
enum FonteTipo: string implements HasLabel
{
    case SiteOficial = 'site_oficial';
    case TabelaPdf = 'tabela_pdf';
    case Contrato = 'contrato';
    case Atendimento = 'atendimento';
    case Imprensa = 'imprensa';
    case RelatoUsuario = 'relato_usuario';

    public function getLabel(): string
    {
        return match ($this) {
            self::SiteOficial => 'Site oficial',
            self::TabelaPdf => 'Tabela em PDF',
            self::Contrato => 'Contrato',
            self::Atendimento => 'Atendimento da marca',
            self::Imprensa => 'Imprensa',
            self::RelatoUsuario => 'Relato de usuario',
        };
    }
}
