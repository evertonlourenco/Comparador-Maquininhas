<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Etapa 10: o segmento do lojista que preenche /enviar-proposta. Mesmas oito
 * chaves e rotulos de resources/js/comparador/segmentos.mjs (etapa 07) — um
 * so vocabulario de segmento no site inteiro, ainda que os dois nao
 * compartilhem tabela: la e palpite de mix de vendas, aqui e o dado que o
 * proprio lojista informa sobre o negocio dele.
 */
enum SegmentoNegocio: string implements HasLabel
{
    case Padaria = 'padaria';
    case Salao = 'salao';
    case Roupas = 'roupas';
    case FoodTruck = 'food_truck';
    case Feira = 'feira';
    case Delivery = 'delivery';
    case Oficina = 'oficina';
    case Outro = 'outro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Padaria => 'Padaria',
            self::Salao => 'Salão de beleza',
            self::Roupas => 'Loja de roupas',
            self::FoodTruck => 'Food truck',
            self::Feira => 'Feira',
            self::Delivery => 'Delivery',
            self::Oficina => 'Oficina',
            self::Outro => 'Outro',
        };
    }
}
