<?php

namespace App\Motor;

/**
 * Regra 4: taxa divulgada e faixa reportada nunca se misturam num mesmo
 * numero, e marca sem taxa nao some do resultado nem aparece como taxa zero.
 * Por isso o estado e do resultado inteiro, e nao um detalhe de exibicao:
 * quem consome o motor e obrigado a olhar para ele antes de ler um valor.
 *
 * Os quatro sao excludentes e ordenados: o comparador so ranqueia CALCULADO;
 * os demais aparecem em blocos proprios, com o motivo a vista.
 */
enum EstadoDoResultado: string
{
    /** Tudo que o cenario pediu tem taxa divulgada. E o unico estado ranqueavel. */
    case Calculado = 'calculado';

    /**
     * O plano tem dado publicado, mas falta alguma peca que este cenario
     * especifico exige - uma taxa naquele prazo, o preco do aparelho, a
     * mensalidade. O total sai como parcial e a lista de faltas vai junto.
     */
    case Incompleto = 'incompleto';

    /**
     * O plano so tem faixa reportada. O custo sai como faixa (minimo, mediana,
     * maximo) e nunca como numero unico - nao existe a chave total_mensal
     * neste estado, do mesmo jeito que nao existe coluna "percentual" em
     * faixas_reportadas.
     */
    case FaixaReportada = 'faixa_reportada';

    /**
     * A marca nao publica tabela e ainda nao tem faixa reportada. Aparece no
     * resultado com o motivo, sem numero nenhum. Zero aqui seria mentira.
     */
    case SemDadoPublicado = 'sem_dado_publicado';

    public function ehRanqueavel(): bool
    {
        return $this === self::Calculado;
    }

    /** Ordem dos blocos no resultado. */
    public function ordem(): int
    {
        return match ($this) {
            self::Calculado => 0,
            self::FaixaReportada => 1,
            self::Incompleto => 2,
            self::SemDadoPublicado => 3,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Calculado => 'Calculado',
            self::Incompleto => 'Falta dado para este cenário',
            self::FaixaReportada => 'Faixa reportada por lojistas',
            self::SemDadoPublicado => 'Sem dado publicado',
        };
    }
}
