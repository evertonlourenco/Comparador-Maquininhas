<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Etapa 13. "Mudanca" e o monitor relatando um trecho que mudou numa
 * url_fonte, com o resumo do Gemini. "Falha" e o monitor avisando que nao
 * conseguiu ler a fonte — nunca fica em silencio quando isso acontece.
 */
enum TipoDeteccaoDeMudanca: string implements HasLabel
{
    case Mudanca = 'mudanca';
    case Falha = 'falha';

    public function getLabel(): string
    {
        return match ($this) {
            self::Mudanca => 'Mudança detectada',
            self::Falha => 'Falha ao coletar',
        };
    }
}
