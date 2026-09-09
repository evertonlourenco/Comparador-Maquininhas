<?php

namespace App\Motor;

use App\Support\Dinheiro;

/**
 * Os quatro numeros que a tela do comparador pede (etapa 07), derivados do
 * resultado do motor.
 *
 * Por que e uma camada separada, e nao mais campos dentro de MotorDeCalculo:
 * o motor responde "quanto custa", que e pergunta de dominio; isto responde
 * "como esse custo se le na tela", que e pergunta de comparacao. Separado, o
 * motor da etapa 05 continua sendo a fonte da verdade do custo e nenhuma
 * mudanca de layout precisa mexer nele.
 *
 * O que e derivado, e de onde:
 *
 *   custo mensal recorrente = total mensal - adesao amortizada
 *       O que continua saindo do caixa todo mes depois que a adesao acabar de
 *       ser paga. E o numero que o lojista compara com o aluguel da loja.
 *
 *   taxa efetiva combinada  = custo mensal recorrente / volume vendido x 100
 *       O mix de debito, credito e Pix colapsado em um percentual so - com
 *       mensalidade e aluguel dentro, porque eles saem do caixa do mesmo
 *       jeito. Vai acompanhada de taxa_efetiva_das_vendas, que e so o
 *       percentual das taxas, para que a diferenca entre as duas seja
 *       visivel em vez de discutivel.
 *
 *   custo inicial           = a adesao, sem cupom e com cupom (regra 5)
 *       Duas linhas, nunca uma: o desconto do link de afiliado so existe se
 *       der para ver o preco de onde ele saiu.
 *
 *   quanto sobra no mes     = faturamento - total mensal
 *       Usa o total, e nao o recorrente, porque a adesao e dinheiro que sai
 *       de verdade no primeiro ano. O horizonte da amortizacao anda junto no
 *       resultado, entao o divisor nunca fica escondido.
 *
 * O nome de cada chave muda com o estado, exatamente como no motor: um numero
 * que nao e permanente (promocional), nao e exato (faixa reportada) ou nao
 * fecha (incompleto) nao pode ter o nome do numero que e. Quem exibir tem de
 * olhar para o estado antes de achar a chave.
 *
 * O gemeo em JavaScript e resources/js/comparador/resumo.mjs, e
 * tests/Feature/Comparador/ParidadeDoResumoTest.php cobra que os dois
 * concordem - nos mesmos casos de borda da etapa 05.
 */
final class ResumoDoComparador
{
    /**
     * Recebe a saida de MotorDeCalculo::calcular() e devolve a mesma
     * estrutura com 'comparacao' em cada item e 'resumo' no topo.
     */
    public function resumir(array $resultado): array
    {
        $cenario = $resultado['cenario'];
        $faturamento = Dinheiro::arredondar((float) $cenario['faturamento_mensal']);

        $volume = Dinheiro::arredondar(array_sum(array_map(
            fn (array $venda): float => (float) $venda['valor_mensal'],
            $cenario['vendas'],
        )));

        $itens = array_map(
            fn (array $item): array => [
                ...$item,
                'comparacao' => $this->comparacao($item, $volume, $faturamento),
            ],
            $resultado['itens'],
        );

        return [
            ...$resultado,
            'itens' => $itens,
            'resumo' => $this->resumo($itens, $cenario, $volume, $faturamento),
        ];
    }

    /**
     * Os quatro numeros de um item. Nulo quando o item nao tem numero nenhum
     * (regra 4: marca sem dado publicado aparece sem valor, e zero seria
     * mentira).
     */
    private function comparacao(array $item, float $volume, float $faturamento): ?array
    {
        $estado = EstadoDoResultado::from($item['estado']);

        if ($estado === EstadoDoResultado::SemDadoPublicado) {
            return null;
        }

        $base = [
            'volume_vendido' => $volume,
            'custo_inicial' => $this->custoInicial($item),
        ];

        if ($estado === EstadoDoResultado::FaixaReportada) {
            return [...$base, ...$this->pontasDaFaixa($item, $volume, $faturamento)];
        }

        $custos = $item['custos'];
        $sufixo = $this->sufixo($estado, $custos);
        $total = (float) $custos['total_mensal'.$sufixo];
        $adesaoPorMes = Dinheiro::arredondar((float) ($item['adesao']['por_mes'] ?? 0.0));
        $recorrente = Dinheiro::arredondar($total - $adesaoPorMes);

        $reais = [
            'custo_mensal_recorrente'.$sufixo => $recorrente,
            'custo_mensal_total'.$sufixo => $total,
            'adesao_amortizada'.$sufixo => $adesaoPorMes,
            'sobra_no_mes'.$sufixo => Dinheiro::arredondar($faturamento - $total),
        ];

        $percentuais = [
            'taxa_efetiva_combinada'.$sufixo => $this->percentualSobre($recorrente, $volume),
            'taxa_efetiva_das_vendas'.$sufixo => $this->percentualSobre((float) $custos['vendas'], $volume),
        ];

        return [
            ...$base,
            'chave' => $sufixo,
            ...$reais,
            ...$percentuais,
            'formatado' => $this->formatar($reais, $percentuais),
        ];
    }

    /**
     * Regra 4, classe B: as tres pontas da faixa, e nenhuma chave que possa
     * ser lida como valor unico. O sufixo acompanha o genero da palavra em
     * portugues - custo_..._minimo, taxa_..._minima -, que e o mesmo cuidado
     * que faz percentual_mediana nao se chamar percentual.
     */
    private function pontasDaFaixa(array $item, float $volume, float $faturamento): array
    {
        $faixa = $item['custos_faixa'];
        $adesaoPorMes = Dinheiro::arredondar((float) ($item['adesao']['por_mes'] ?? 0.0));
        $reais = [];
        $percentuais = [];

        foreach ([['minimo', 'minima'], ['mediana', 'mediana'], ['maximo', 'maxima']] as [$ponta, $feminino]) {
            $total = (float) $faixa['total_mensal_'.$ponta];
            $recorrente = Dinheiro::arredondar($total - $adesaoPorMes);

            $reais['custo_mensal_recorrente_'.$ponta] = $recorrente;
            $reais['custo_mensal_total_'.$ponta] = $total;
            $reais['sobra_no_mes_'.$feminino] = Dinheiro::arredondar($faturamento - $total);

            $percentuais['taxa_efetiva_combinada_'.$feminino] = $this->percentualSobre($recorrente, $volume);
            $percentuais['taxa_efetiva_das_vendas_'.$feminino] = $this->percentualSobre(
                (float) $faixa['vendas_'.$ponta],
                $volume,
            );
        }

        $reais['adesao_amortizada'] = $adesaoPorMes;

        return [
            'chave' => '_faixa',
            ...$reais,
            ...$percentuais,
            'formatado' => $this->formatar($reais, $percentuais),
        ];
    }

    /**
     * Regra 5: o cupom desconta a adesao, e por isso os dois precos andam
     * juntos. Sem aparelho resolvido nao ha custo inicial - nulo, nunca zero.
     */
    private function custoInicial(array $item): ?array
    {
        $adesao = $item['adesao'] ?? null;

        if ($adesao === null || $adesao['vigente'] === null) {
            return null;
        }

        $semCupom = (float) $adesao['vigente'];
        $comCupom = (float) $adesao['valor_final'];
        $economia = (float) $adesao['desconto_do_cupom'];

        return [
            'sem_cupom' => $semCupom,
            'com_cupom' => $comCupom,
            'economia' => $economia,
            'tem_cupom' => $economia > 0.0,
            'cupom' => $item['cupom']['codigo'] ?? null,
            'parcelas_oferecidas' => $adesao['parcelas_oferecidas'],
            'parcela_da_marca' => $adesao['parcela_da_marca'],
            'formatado' => [
                'sem_cupom' => Dinheiro::real($semCupom),
                'com_cupom' => Dinheiro::real($comCupom),
                'economia' => Dinheiro::real($economia),
                'parcela_da_marca' => $item['formatado']['adesao']['parcela_da_marca'] ?? null,
            ],
        ];
    }

    /**
     * O topo da tela: quem ganhou, quem perdeu e quanto separa os dois.
     *
     * Regra 4 outra vez - so o bloco CALCULADO entra aqui. Promocao, faixa
     * reportada e incompleto tem bloco proprio na pagina e nunca melhor nem
     * pior, porque nao correram a mesma corrida.
     */
    private function resumo(array $itens, array $cenario, float $volume, float $faturamento): array
    {
        $quantidades = [];

        foreach (EstadoDoResultado::cases() as $estado) {
            $quantidades[$estado->value] = count(array_filter(
                $itens,
                fn (array $item): bool => $item['estado'] === $estado->value,
            ));
        }

        $ranqueaveis = array_values(array_filter(
            $itens,
            fn (array $item): bool => $item['estado'] === EstadoDoResultado::Calculado->value,
        ));

        // O motor ja entregou os itens ordenados por total_mensal crescente,
        // com desempate estavel. Reordenar aqui seria arriscar discordar dele.
        $melhor = $ranqueaveis === [] ? null : $this->extremo($ranqueaveis[0]);
        $pior = count($ranqueaveis) < 2 ? null : $this->extremo($ranqueaveis[count($ranqueaveis) - 1]);

        $diferenca = $melhor === null || $pior === null
            ? null
            : Dinheiro::arredondar($pior['custo_mensal_total'] - $melhor['custo_mensal_total']);

        $horizonte = (int) $cenario['horizonte_meses'];

        return [
            'faturamento_mensal' => $faturamento,
            'volume_vendido' => $volume,
            // O que nao passa na maquininha (dinheiro, principalmente) nao
            // custa taxa nenhuma, e por isso precisa aparecer: sem ele a
            // "taxa efetiva" pareceria incidir sobre o faturamento inteiro.
            'fora_da_maquininha' => Dinheiro::arredondar($faturamento - $volume),
            'horizonte_meses' => $horizonte,
            'quantidades' => $quantidades,
            'melhor' => $melhor,
            'pior' => $pior,
            'diferenca_mensal' => $diferenca,
            'diferenca_no_horizonte' => $diferenca === null
                ? null
                : Dinheiro::arredondar($diferenca * $horizonte),
            'formatado' => [
                'faturamento_mensal' => Dinheiro::real($faturamento),
                'volume_vendido' => Dinheiro::real($volume),
                'fora_da_maquininha' => Dinheiro::real(Dinheiro::arredondar($faturamento - $volume)),
                'diferenca_mensal' => $diferenca === null ? null : Dinheiro::real($diferenca),
                'diferenca_no_horizonte' => $diferenca === null
                    ? null
                    : Dinheiro::real(Dinheiro::arredondar($diferenca * $horizonte)),
            ],
        ];
    }

    /** O cartao curto do melhor e do pior, para o topo da pagina. */
    private function extremo(array $item): array
    {
        $comparacao = $item['comparacao'];

        return [
            'marca' => $item['marca']['nome'],
            'marca_slug' => $item['marca']['slug'],
            'plano' => $item['plano']['nome'] ?? null,
            'plano_id' => $item['plano']['id'] ?? null,
            'custo_mensal_total' => $comparacao['custo_mensal_total'],
            'custo_mensal_recorrente' => $comparacao['custo_mensal_recorrente'],
            'taxa_efetiva_combinada' => $comparacao['taxa_efetiva_combinada'],
            'sobra_no_mes' => $comparacao['sobra_no_mes'],
            'formatado' => [
                'custo_mensal_total' => Dinheiro::real($comparacao['custo_mensal_total']),
                'custo_mensal_recorrente' => Dinheiro::real($comparacao['custo_mensal_recorrente']),
                'taxa_efetiva_combinada' => $comparacao['taxa_efetiva_combinada'] === null
                    ? null
                    : Dinheiro::percentual($comparacao['taxa_efetiva_combinada']),
                'sobra_no_mes' => Dinheiro::real($comparacao['sobra_no_mes']),
            ],
        ];
    }

    /**
     * Volume zero nao vira taxa efetiva zero: sem nada passando na maquininha
     * a divisao nao existe, e um "0,00%" ali seria a mentira mais confortavel
     * da tela.
     */
    private function percentualSobre(float $custo, float $volume): ?float
    {
        return $volume <= 0.0 ? null : Dinheiro::arredondar($custo / $volume * 100);
    }

    /**
     * Regra 11 na saida, como no motor: dinheiro com R$ e percentual com duas
     * casas, formatados aqui e nao em cada tela.
     */
    private function formatar(array $reais, array $percentuais): array
    {
        $saida = [];

        foreach ($reais as $chave => $valor) {
            $saida[$chave] = Dinheiro::real($valor);
        }

        foreach ($percentuais as $chave => $valor) {
            $saida[$chave] = $valor === null ? null : Dinheiro::percentual($valor);
        }

        return $saida;
    }

    /** O mesmo sufixo que o motor usou na chave do total. */
    private function sufixo(EstadoDoResultado $estado, array $custos): string
    {
        return match (true) {
            $estado === EstadoDoResultado::Promocional
                && array_key_exists('total_mensal_promocional_parcial', $custos) => '_promocional_parcial',
            $estado === EstadoDoResultado::Promocional => '_promocional',
            $estado === EstadoDoResultado::Incompleto => '_parcial',
            default => '',
        };
    }
}
