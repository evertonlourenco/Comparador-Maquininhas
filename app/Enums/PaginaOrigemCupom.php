<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** De onde o clique saiu, para o rastreamento da etapa 09. */
enum PaginaOrigemCupom: string implements HasLabel
{
    case PaginaDeMarca = 'marca';
    case ListagemDeCupons = 'cupons';
    case PaginaDeCupom = 'cupom_marca';
    // Etapa 20 (bloco B): o cartao do resultado, via /ir/{marca}.
    case Comparador = 'comparador';

    public function getLabel(): string
    {
        return match ($this) {
            self::PaginaDeMarca => 'Página da marca',
            self::ListagemDeCupons => 'Listagem de cupons',
            self::PaginaDeCupom => 'Página do cupom',
            self::Comparador => 'Comparador',
        };
    }
}
