<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Regra 3: plano e entidade propria, com tipo de enquadramento.
 */
enum TipoEnquadramento: string implements HasLabel
{
    case Automatico = 'automatico';
    case Escolhido = 'escolhido';
    case Negociado = 'negociado';

    /**
     * Tabela de entrada, com prazo para acabar. O lojista cai nela sozinho ao
     * ativar a maquininha e sai dela sozinho quando o limite estoura - 30 dias
     * ou um teto de volume processado, o que vier antes.
     *
     * E um enquadramento e nao um "plano em promocao" porque e assim que ele
     * funciona: nao se escolhe, nao se negocia e nao depende do faturamento do
     * mes anterior. E o unico dos quatro que expira.
     */
    case Promocional = 'promocional';

    public function getLabel(): string
    {
        return match ($this) {
            self::Automatico => 'Automatico (faixa de faturamento define)',
            self::Escolhido => 'Escolhido (lojista opta e assume compromisso)',
            self::Negociado => 'Negociado',
            self::Promocional => 'Promocional (tabela de entrada, com prazo para acabar)',
        };
    }

    /** Para badge de tabela, onde o rotulo longo nao cabe. */
    public function rotuloCurto(): string
    {
        return match ($this) {
            self::Automatico => 'Automático',
            self::Escolhido => 'Escolhido',
            self::Negociado => 'Negociado',
            self::Promocional => 'Promocional',
        };
    }

    /**
     * O motor nunca ranqueia um plano promocional junto dos permanentes:
     * o numero dele e verdadeiro e tem prazo de validade.
     */
    public function ehTemporario(): bool
    {
        return $this === self::Promocional;
    }

    /**
     * So o enquadramento automatico usa faixa de faturamento.
     *
     * O promocional em particular nao usa: o teto dele e de volume processado
     * durante a promocao, que e outra coisa, e mora em
     * promocional_valor_processado.
     */
    public function usaFaixaDeFaturamento(): bool
    {
        return $this === self::Automatico;
    }
}
