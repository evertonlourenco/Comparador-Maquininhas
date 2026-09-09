<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Vale-refeicao nao aparece aqui: e um grupo de bandeiras (voucher) operado
 * como debito. Ver App\Enums e a tabela grupos_bandeiras.
 */
enum TipoOperacao: string implements HasLabel
{
    case Debito = 'debito';
    case CreditoAvista = 'credito_avista';
    case CreditoParcelado = 'credito_parcelado';
    case Pix = 'pix';

    public function getLabel(): string
    {
        return match ($this) {
            self::Debito => 'Débito',
            self::CreditoAvista => 'Crédito à vista',
            self::CreditoParcelado => 'Crédito parcelado',
            self::Pix => 'Pix',
        };
    }

    /** Regra 2: parcelas sao inteiro de 1 a 21; so o parcelado passa de 1. */
    public function permiteParcelamento(): bool
    {
        return $this === self::CreditoParcelado;
    }

    public function parcelaMinima(): int
    {
        return $this->permiteParcelamento() ? 2 : 1;
    }

    public function parcelaMaxima(): int
    {
        return $this->permiteParcelamento() ? 21 : 1;
    }
}
