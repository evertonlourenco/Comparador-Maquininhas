<?php

namespace App\Motor;

use App\Support\Dinheiro;

/**
 * O motor de calculo (etapa 05).
 *
 * Come um catalogo em array puro - o mesmo array que vira o JSON estatico da
 * regra 9 - e devolve array puro. Nao toca em Eloquent, nao consulta banco e
 * nao le o relogio: tudo que muda o resultado entra por parametro. E o que
 * torna possivel a implementacao gemea em JavaScript
 * (resources/js/comparador/motor.mjs) receber exatamente a mesma entrada e ser
 * comparada caso a caso (ParidadeDoMotorTest).
 *
 * As tres contas, na ordem da regra 6:
 *   custo da venda    = percentual da taxa + valor_fixo, na chave da regra 1
 *   custo da conta    = mensalidade + saque + TED + Pix + (antecipacao avulsa)
 *   custo do aparelho = aluguel + adesao amortizada
 *
 * O que ele nao faz, em nenhuma hipotese: estimar. Faltou a taxa daquele
 * prazo, o preco do aparelho ou a mensalidade, o resultado sai com estado
 * INCOMPLETO e a lista do que falta - nunca com zero no lugar.
 */
final class MotorDeCalculo
{
    /**
     * Amortizacao da adesao: 12 meses.
     *
     * A adesao e custo unico e o comparativo e mensal, entao ela precisa de um
     * divisor. 12 nao e numero escolhido a esmo: e o parcelamento que as
     * proprias marcas oferecem para a adesao - a InfinitePay publica "R$ 199,00
     * a vista ou 12x de R$ 16,58" na mesma pagina de onde a carga tirou o
     * preco. Dividir por 12 responde a pergunta que o lojista faz de fato,
     * que e quanto sai por mes no primeiro ano.
     *
     * O horizonte e campo do cenario justamente por ser convencao e nao lei:
     * quem quiser comparar em 24 meses muda o numero e ve o efeito. Em todo
     * resultado vao juntos o valor amortizado e a adesao cheia, para que a
     * conta nunca fique escondida atras do divisor.
     */
    public const HORIZONTE_PADRAO = Cenario::HORIZONTE_PADRAO;

    public function calcular(array $catalogo, Cenario $cenario): array
    {
        $itens = [];

        foreach ($catalogo['marcas'] as $marca) {
            $planos = $this->planosElegiveis($marca, $cenario);

            // Regra 4: marca sem nenhum plano elegivel nao some do resultado.
            if ($planos === []) {
                $itens[] = $this->semDadoPublicado($marca, $cenario);

                continue;
            }

            foreach ($planos as $plano) {
                $itens[] = $this->avaliarPlano($catalogo, $marca, $plano, $cenario);
            }
        }

        return [
            'cenario' => $cenario->paraArray(),
            'catalogo' => [
                'versao' => $catalogo['versao'] ?? null,
                'gerado_em' => $catalogo['gerado_em'] ?? null,
                'contem_rascunhos' => $catalogo['contem_rascunhos'] ?? false,
            ],
            'itens' => $this->ordenar(array_map($this->formatar(...), $itens)),
        ];
    }

    /**
     * O primitivo da regra 6: quanto custa uma venda naquela taxa.
     *
     * Devolve custo nulo quando a taxa cobra valor fixo por transacao e o
     * cenario nao informou a quantidade - o motor nao inventa o numero de
     * vendas para fazer a conta fechar.
     */
    public function custoDaVenda(array $taxa, float $valorMensal, ?int $quantidadeMensal): array
    {
        $percentual = (float) $taxa['percentual'];
        $valorFixo = (float) $taxa['valor_fixo'];

        $custoPercentual = Dinheiro::arredondar($valorMensal * $percentual / 100);

        if ($valorFixo == 0.0) {
            return [
                'percentual' => $percentual,
                'custo_percentual' => $custoPercentual,
                'custo_fixo' => 0.0,
                'custo' => $custoPercentual,
                'falta' => null,
            ];
        }

        if ($quantidadeMensal === null) {
            return [
                'percentual' => $percentual,
                'custo_percentual' => $custoPercentual,
                'custo_fixo' => null,
                'custo' => null,
                'falta' => 'quantidade de transações no mês (a taxa cobra valor fixo por venda)',
            ];
        }

        $custoFixo = Dinheiro::arredondar($quantidadeMensal * $valorFixo);

        return [
            'percentual' => $percentual,
            'custo_percentual' => $custoPercentual,
            'custo_fixo' => $custoFixo,
            // As partes ja arredondadas somam exatamente o total exibido.
            'custo' => Dinheiro::arredondar($custoPercentual + $custoFixo),
            'falta' => null,
        ];
    }

    /**
     * Regra 3 + scope Plano::paraFaturamento. So o enquadramento automatico
     * filtra por faixa de faturamento; escolhido e negociado sempre aparecem,
     * porque neles quem decide e o lojista, nao o faturamento do mes passado.
     */
    private function planosElegiveis(array $marca, Cenario $cenario): array
    {
        return array_values(array_filter($marca['planos'], function (array $plano) use ($cenario): bool {
            if ($plano['tipo_enquadramento'] !== 'automatico') {
                return true;
            }

            $minimo = $plano['faturamento_min'];
            $maximo = $plano['faturamento_max'];

            return ($minimo === null || $cenario->faturamentoMensal >= $minimo)
                && ($maximo === null || $cenario->faturamentoMensal <= $maximo);
        }));
    }

    /**
     * Mesma avaliacao de um plano que `calcular()` faz por dentro, exposta
     * para `App\Support\Saude\CompletudeDaMarca` (etapa 19): a checagem de
     * completude precisa saber o que falta num plano especifico, ignorando o
     * filtro de faturamento de `planosElegiveis()` — a pergunta ali nao e "o
     * lojista de hoje pode usar este plano", e "este plano, quando alguem cair
     * nele, tem tudo que precisa". Zero logica nova: e o mesmo caminho que o
     * comparador publico usa, entao "falta dado" aqui e exatamente "falta
     * dado" que apareceria na tela.
     */
    public function avaliarPlanoParaCompletude(array $catalogo, array $marca, array $plano, Cenario $cenario): array
    {
        return $this->avaliarPlano($catalogo, $marca, $plano, $cenario);
    }

    private function avaliarPlano(array $catalogo, array $marca, array $plano, Cenario $cenario): array
    {
        // Regra 4: um plano e avaliado por uma classe de dado so. Havendo taxa
        // divulgada, e ela - faixa reportada nunca completa buraco de tabela
        // publicada, senao o total misturaria as duas num numero unico.
        if ($plano['taxas'] !== []) {
            return $this->avaliarComTaxasDivulgadas($catalogo, $marca, $plano, $cenario);
        }

        if ($plano['faixas'] !== []) {
            return $this->avaliarComFaixaReportada($catalogo, $marca, $plano, $cenario);
        }

        return [
            ...$this->esqueleto($marca, $plano, $cenario),
            'estado' => EstadoDoResultado::SemDadoPublicado->value,
            'motivo' => 'O plano não tem nenhuma taxa publicada nem faixa reportada.',
        ];
    }

    private function avaliarComTaxasDivulgadas(array $catalogo, array $marca, array $plano, Cenario $cenario): array
    {
        $faltando = [];
        $avisos = [];
        $linhas = [];
        $prazosUsados = [];
        $datasDeVerificacao = [];

        foreach ($cenario->vendas as $venda) {
            $linha = $this->resolverLinha($catalogo, $plano, $venda, $cenario);
            $linhas[] = $linha;

            if ($linha['falta'] !== null) {
                $faltando[] = $linha['falta'];

                continue;
            }

            $prazosUsados[$linha['prazo']] = true;
            $datasDeVerificacao[] = $linha['data_verificacao'];
        }

        if (count($prazosUsados) > 1) {
            // Pelo nome de exibicao da dimensao, e nao pelo codigo: este aviso
            // sai direto na tela do lojista (etapa 07), e "d_1, na_hora" nao
            // quer dizer nada para quem tem uma padaria.
            $nomes = array_map(
                fn (string $codigo): string => $catalogo['prazos'][$codigo]['nome'] ?? $codigo,
                array_keys($prazosUsados),
            );

            $avisos[] = 'Este plano foi comparado usando mais de um prazo de recebimento ('
                .implode(', ', $nomes).'). Confira se a marca vende essa combinação.';
        }

        $conta = $this->custoDaConta($plano);
        $aparelho = $this->custoDoAparelho($marca, $plano, $cenario);
        $antecipacao = $this->custoDaAntecipacaoAvulsa($catalogo, $plano, $cenario, $linhas);

        $faltando = [...$faltando, ...$conta['faltando'], ...$aparelho['faltando'], ...$antecipacao['faltando']];
        $avisos = [...$avisos, ...$conta['avisos'], ...$aparelho['avisos'], ...$antecipacao['avisos']];

        // Etapa 05: taxa publicada pode vir condicionada - o Pix a 0% que so
        // vale com a chave ativada no aplicativo, por exemplo. A condicao anda
        // colada no numero, nunca sai como nota de rodape opcional.
        foreach ($linhas as $linha) {
            if (($linha['condicao'] ?? null) !== null) {
                $avisos[] = $linha['condicao'];
            }
        }

        $custoVendas = Dinheiro::arredondar(array_sum(array_map(
            fn (array $l): float => $l['custo'] ?? 0.0,
            $linhas,
        )));

        $total = Dinheiro::arredondar($custoVendas + $conta['custo'] + $aparelho['custo'] + $antecipacao['custo']);
        $completo = $faltando === [];

        // O plano promocional tem estado proprio, e nao "calculado": o numero
        // dele e verdadeiro e tem prazo de validade. Misturado aos permanentes
        // ele ganharia a comparacao com um preco que dura 30 dias.
        $promocional = $plano['tipo_enquadramento'] === 'promocional';

        $estado = match (true) {
            $promocional => EstadoDoResultado::Promocional,
            $completo => EstadoDoResultado::Calculado,
            default => EstadoDoResultado::Incompleto,
        };

        $chaveDoTotal = match (true) {
            $promocional && $completo => 'total_mensal_promocional',
            $promocional => 'total_mensal_promocional_parcial',
            $completo => 'total_mensal',
            default => 'total_mensal_parcial',
        };

        return [
            ...$this->esqueleto($marca, $plano, $cenario),
            'estado' => $estado->value,
            'motivo' => match (true) {
                $promocional => $this->motivoDaPromocao($plano),
                $completo => null,
                default => 'Falta dado para fechar este cenário.',
            },
            'promocao' => $promocional ? $this->promocao($marca, $plano, $cenario) : null,
            'prazos_usados' => array_keys($prazosUsados),
            'equipamento' => $aparelho['equipamento'],
            'adesao' => $aparelho['adesao'],
            'cupom' => $aparelho['cupom'],
            'custos' => [
                'vendas' => $custoVendas,
                'conta' => $conta['custo'],
                'aparelho' => $aparelho['custo'],
                'antecipacao_avulsa' => $antecipacao['custo'],
                // O total so se chama total_mensal quando nao falta nada e o
                // plano e permanente. Sendo parcial ou promocional ele muda de
                // nome, pelo mesmo motivo que faixas_reportadas nao tem coluna
                // "percentual": para que ninguem leia como fechado, ou como
                // permanente, um numero que nao e.
                $chaveDoTotal => $total,
            ],
            'vendas' => $linhas,
            'conta' => $conta['itens'],
            'frescor' => $this->frescor($datasDeVerificacao, $catalogo, $cenario),
            'faltando' => array_values(array_unique($faltando)),
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /**
     * Regra 4, classe B. Nao existe chave total_mensal aqui: o custo de venda
     * sai como faixa, e so como faixa. Quem consumir o resultado tem de
     * escolher explicitamente minimo, mediana ou maximo - nao ha numero unico
     * para exibir por engano ao lado de uma taxa publicada.
     */
    private function avaliarComFaixaReportada(array $catalogo, array $marca, array $plano, Cenario $cenario): array
    {
        $faltando = [];
        $linhas = [];
        $datasDeVerificacao = [];
        $soma = ['minimo' => 0.0, 'mediana' => 0.0, 'maximo' => 0.0];

        foreach ($cenario->vendas as $venda) {
            $faixa = $this->faixaDaLinha($plano, $venda, $cenario);

            if ($faixa === null) {
                $falta = 'faixa reportada para '.$venda->rotulo($catalogo['grupos']);
                $faltando[] = $falta;
                $linhas[] = ['venda' => $venda->paraArray(), 'falta' => $falta];

                continue;
            }

            $custos = [];

            foreach (['minimo', 'mediana', 'maximo'] as $ponta) {
                $custos[$ponta] = Dinheiro::arredondar($venda->valorMensal * (float) $faixa[$ponta] / 100);
                $soma[$ponta] += $custos[$ponta];
            }

            $datasDeVerificacao[] = $faixa['data_verificacao'];
            $linhas[] = [
                'venda' => $venda->paraArray(),
                'prazo' => $faixa['prazo'],
                'percentual_minimo' => (float) $faixa['minimo'],
                'percentual_mediana' => (float) $faixa['mediana'],
                'percentual_maximo' => (float) $faixa['maximo'],
                'n_relatos' => $faixa['n_relatos'],
                'custo_minimo' => $custos['minimo'],
                'custo_mediana' => $custos['mediana'],
                'custo_maximo' => $custos['maximo'],
                'falta' => null,
            ];
        }

        $conta = $this->custoDaConta($plano);
        $aparelho = $this->custoDoAparelho($marca, $plano, $cenario);
        $fixo = $conta['custo'] + $aparelho['custo'];

        return [
            ...$this->esqueleto($marca, $plano, $cenario),
            'estado' => EstadoDoResultado::FaixaReportada->value,
            'motivo' => 'Esta marca não publica tabela. O custo vem de relatos de lojistas e é uma faixa, '
                .'não um valor exato.',
            'equipamento' => $aparelho['equipamento'],
            'adesao' => $aparelho['adesao'],
            'cupom' => $aparelho['cupom'],
            'custos_faixa' => [
                'vendas_minimo' => Dinheiro::arredondar($soma['minimo']),
                'vendas_mediana' => Dinheiro::arredondar($soma['mediana']),
                'vendas_maximo' => Dinheiro::arredondar($soma['maximo']),
                'conta' => $conta['custo'],
                'aparelho' => $aparelho['custo'],
                'total_mensal_minimo' => Dinheiro::arredondar($soma['minimo'] + $fixo),
                'total_mensal_mediana' => Dinheiro::arredondar($soma['mediana'] + $fixo),
                'total_mensal_maximo' => Dinheiro::arredondar($soma['maximo'] + $fixo),
            ],
            'vendas' => $linhas,
            'conta' => $conta['itens'],
            'frescor' => $this->frescor($datasDeVerificacao, $catalogo, $cenario),
            'faltando' => array_values(array_unique([...$faltando, ...$conta['faltando'], ...$aparelho['faltando']])),
            'avisos' => array_values(array_unique([...$conta['avisos'], ...$aparelho['avisos']])),
        ];
    }

    /** Regra 4: a marca aparece, com o motivo, e sem numero nenhum. */
    private function semDadoPublicado(array $marca, Cenario $cenario): array
    {
        $motivo = $marca['planos'] === []
            ? ($marca['publica_tabela']
                ? 'A marca ainda não tem nenhum plano cadastrado.'
                : 'A marca não publica tabela de taxas e ainda não tem faixa reportada por lojistas.')
            : 'Nenhum plano desta marca atende ao faturamento informado.';

        return [
            ...$this->esqueleto($marca, null, $cenario),
            'estado' => EstadoDoResultado::SemDadoPublicado->value,
            'motivo' => $motivo,
        ];
    }

    /** Campos comuns a todo item, inclusive aos que nao tem numero. */
    private function esqueleto(array $marca, ?array $plano, Cenario $cenario): array
    {
        return [
            'marca' => [
                'id' => $marca['id'],
                'nome' => $marca['nome'],
                'slug' => $marca['slug'],
                'site_url' => $marca['site_url'],
                'logo_url' => $marca['logo_url'],
                'publica_tabela' => $marca['publica_tabela'],
                'adquirente' => $marca['adquirente'],
                'reclame_aqui' => $marca['reclame_aqui'],
            ],
            'plano' => $plano === null ? null : [
                'id' => $plano['id'],
                'nome' => $plano['nome'],
                'slug' => $plano['slug'],
                'tipo_enquadramento' => $plano['tipo_enquadramento'],
            ],
            'enquadramento' => $plano === null ? null : $this->enquadramento($plano),
            'horizonte_meses' => $cenario->horizonteMeses,
            'prazos_usados' => [],
            'promocao' => null,
            'equipamento' => null,
            'adesao' => null,
            'cupom' => null,
            'custos' => null,
            'custos_faixa' => null,
            'vendas' => [],
            'conta' => [],
            'frescor' => ['nivel' => 'sem_data', 'data_verificacao' => null, 'dias' => null],
            'faltando' => [],
            'avisos' => [],
        ];
    }

    /**
     * O aviso que precisa acompanhar todo numero promocional. Os dois limites
     * valem em disjuncao: o que vier antes acaba com a promocao.
     */
    private function motivoDaPromocao(array $plano): string
    {
        $promocao = $plano['promocao'] ?? null;
        $limites = [];

        if (($promocao['dias'] ?? null) !== null) {
            $limites[] = $promocao['dias'].' dias';
        }

        if (($promocao['valor_processado'] ?? null) !== null) {
            $limites[] = Dinheiro::real($promocao['valor_processado']).' processados';
        }

        if ($limites === []) {
            return 'Tabela de entrada, por tempo limitado. A marca não publicou o prazo exato.';
        }

        return 'Tabela de entrada: vale por '.implode(' ou até ', $limites)
            .', o que vier antes. Depois disso o preço muda.';
    }

    /**
     * O bloco da promocao, com o plano em que o lojista cai quando ela acaba.
     *
     * Sem sucessor declarado, o sucessor e o plano de enquadramento automatico
     * da propria marca que atende ao faturamento informado - que e como o Ton
     * funciona. Nao havendo nenhum, sai nulo: melhor dizer que nao se sabe do
     * que apontar para o plano errado.
     */
    private function promocao(array $marca, array $plano, Cenario $cenario): array
    {
        $promocao = $plano['promocao'] ?? ['dias' => null, 'valor_processado' => null, 'sucessor_id' => null];
        $sucessor = null;

        foreach ($this->planosElegiveis($marca, $cenario) as $candidato) {
            $declarado = $promocao['sucessor_id'] !== null && $candidato['id'] === $promocao['sucessor_id'];
            $automatico = $promocao['sucessor_id'] === null && $candidato['tipo_enquadramento'] === 'automatico';

            if ($declarado || $automatico) {
                $sucessor = ['id' => $candidato['id'], 'nome' => $candidato['nome'], 'slug' => $candidato['slug']];

                break;
            }
        }

        return [
            'dias' => $promocao['dias'],
            'valor_processado' => $promocao['valor_processado'],
            'sucessor' => $sucessor,
        ];
    }

    private function enquadramento(array $plano): array
    {
        return [
            'tipo' => $plano['tipo_enquadramento'],
            'aviso' => match ($plano['tipo_enquadramento']) {
                'escolhido' => 'Plano de adesão opcional: o lojista escolhe e assume o compromisso de volume.',
                'negociado' => 'Plano negociado caso a caso. O percentual publicado é referência, não garantia.',
                'promocional' => 'Tabela de entrada: o lojista cai nela sozinho ao ativar a maquininha e sai '
                    .'dela sozinho quando o limite estoura.',
                default => null,
            },
        ];
    }

    /**
     * Resolve a quinta dimensao da chave (o prazo) para uma linha de venda.
     *
     * Com prazo informado no cenario, e aquele ou nada - se o plano nao vende
     * debito na hora, o resultado diz que falta, nao troca por outro prazo. Sem
     * prazo informado, o motor escolhe o mais barato entre os que o plano
     * oferece, ja somando a antecipacao avulsa quando ela estiver ligada -
     * senao escolher "o mais barato" premiaria o prazo longo e cobraria a
     * antecipacao depois.
     */
    private function resolverLinha(array $catalogo, array $plano, VendaDoCenario $venda, Cenario $cenario): array
    {
        $candidatas = array_values(array_filter(
            $plano['taxas'],
            fn (array $taxa): bool => $taxa['tipo_operacao'] === $venda->tipoOperacao->value
                && $taxa['grupo'] === $venda->grupo
                && $taxa['parcelas'] === $venda->parcelas
                && ($cenario->prazo === null || $taxa['prazo'] === $cenario->prazo),
        ));

        if ($candidatas === []) {
            return [
                'venda' => $venda->paraArray(),
                'prazo' => null,
                'custo' => null,
                'falta' => 'taxa de '.$venda->rotulo($catalogo['grupos'])
                    .($cenario->prazo === null
                        ? ''
                        : ' no prazo '.($catalogo['prazos'][$cenario->prazo]['nome'] ?? $cenario->prazo)),
            ];
        }

        $melhor = null;

        foreach ($candidatas as $taxa) {
            $custo = $this->custoDaVenda($taxa, $venda->valorMensal, $venda->quantidadeMensal);
            $comparavel = ($custo['custo'] ?? 0.0)
                + $this->antecipacaoDaLinha($catalogo, $plano, $venda, $taxa, $custo['custo'] ?? 0.0, $cenario)['custo'];

            // Desempate estavel: a ordem do prazo na dimensao curada. Sem isso,
            // PHP e JavaScript poderiam escolher taxas diferentes de custo igual.
            $ordem = $catalogo['prazos'][$taxa['prazo']]['ordem'] ?? 0;

            if ($melhor === null || $comparavel < $melhor['comparavel']
                || ($comparavel === $melhor['comparavel'] && $ordem < $melhor['ordem'])) {
                $melhor = ['taxa' => $taxa, 'custo' => $custo, 'comparavel' => $comparavel, 'ordem' => $ordem];
            }
        }

        $taxa = $melhor['taxa'];
        $custo = $melhor['custo'];

        return [
            'venda' => $venda->paraArray(),
            'prazo' => $taxa['prazo'],
            'percentual' => $custo['percentual'],
            'valor_fixo' => (float) $taxa['valor_fixo'],
            'custo_percentual' => $custo['custo_percentual'],
            'custo_fixo' => $custo['custo_fixo'],
            'custo' => $custo['custo'],
            'condicao' => $taxa['condicao'] ?? null,
            'data_verificacao' => $taxa['data_verificacao'],
            'falta' => $custo['falta'] === null ? null : $venda->rotulo($catalogo['grupos']).': falta '.$custo['falta'],
        ];
    }

    private function faixaDaLinha(array $plano, VendaDoCenario $venda, Cenario $cenario): ?array
    {
        foreach ($plano['faixas'] as $faixa) {
            if ($faixa['tipo_operacao'] === $venda->tipoOperacao->value
                && $faixa['grupo'] === $venda->grupo
                && $faixa['parcelas'] === $venda->parcelas
                && ($cenario->prazo === null || $faixa['prazo'] === $cenario->prazo)) {
                return $faixa;
            }
        }

        return null;
    }

    /**
     * Custo da conta (regra 6). Mensalidade e o unico custo de conta que o
     * Maquina Certa compara.
     *
     * Decisao do Everton em 17/09/2026, revendo a etapa 19: tarifa de saque,
     * de TED e de Pix (recebido ou enviado) sao custos da CONTA DIGITAL da
     * adquirente - e o lojista nao e obrigado a usa-la. Ele pode cadastrar a
     * conta do proprio banco pra receber o que a maquininha processa, e nesse
     * caso nenhuma dessas tarifas se aplica. O Maquina Certa compara so o que
     * e inescapavel pra quem usa a maquininha: taxa de venda, custo de
     * adesao/aluguel do aparelho e mensalidade do plano (quando existe). Os
     * quatro campos ficaram vestigiais no schema (`planos.tarifa_saque`,
     * `tarifa_ted`, `tarifa_pix_recebimento`, `tarifa_pix_envio`) e no
     * `Cenario` (`saquesMensais`, `tedsMensais`, `pixEnviosMensais`) -
     * mantidos por nao terem custo de manter, mas o motor nao le mais nenhum
     * deles. `App\Support\Saude\CompletudeDaMarca` reusa este mesmo metodo
     * pra decidir o que falta numa marca, entao a mudanca vale nos dois
     * lugares de graca, sem tocar em nenhum dos dois.
     */
    private function custoDaConta(array $plano): array
    {
        $conta = $plano['conta'];
        $faltando = [];
        $itens = [];
        $total = 0.0;

        if ($conta['mensalidade'] === null) {
            $faltando[] = 'mensalidade do plano';
        } else {
            $mensalidade = Dinheiro::arredondar((float) $conta['mensalidade']);
            $total += $mensalidade;
            $itens['mensalidade'] = $mensalidade;
        }

        return [
            'custo' => Dinheiro::arredondar($total),
            'itens' => $itens,
            'faltando' => $faltando,
            'avisos' => [],
        ];
    }

    /**
     * Custo do aparelho: aluguel mensal + adesao amortizada no horizonte.
     *
     * A adesao e do par equipamento+plano, entao a escolha do aparelho e parte
     * do calculo. Sem aparelho indicado no cenario, o motor pega o mais barato
     * no horizonte - que e a comparacao que o lojista faria.
     *
     * Sobre preco nulo: a linha equipamento_plano existir ja significa que a
     * marca vende aquele aparelho naquele plano. Quando um dos precos esta
     * preenchido e o outro nao, o vazio e a marca dizendo que aquela forma de
     * cobranca nao existe ali - Ton e InfinitePay vendem o aparelho e deixam
     * aluguel_mensal nulo, com a observacao "sem aluguel: o aparelho e
     * comprado". Nesse caso o nulo entra como zero e um aviso registra isso.
     * Quando nenhum dos precos e conhecido - o caso da SumUp, que publica so o
     * valor da parcela - nao ha o que somar, e o resultado diz que falta.
     */
    private function custoDoAparelho(array $marca, array $plano, Cenario $cenario): array
    {
        $vazio = [
            'custo' => 0.0, 'equipamento' => null, 'adesao' => null, 'cupom' => null,
            'faltando' => [], 'avisos' => [],
        ];

        if ($plano['equipamentos'] === []) {
            return [...$vazio, 'faltando' => ['equipamento vinculado a este plano']];
        }

        $candidatos = $plano['equipamentos'];

        if ($cenario->equipamentoId !== null) {
            $candidatos = array_values(array_filter(
                $candidatos,
                fn (array $e): bool => $e['id'] === $cenario->equipamentoId,
            ));

            if ($candidatos === []) {
                return [...$vazio, 'faltando' => ['o equipamento escolhido não é vendido neste plano']];
            }
        }

        $cupom = $cenario->aplicarCupom ? $this->cupomVigente($marca, $cenario) : null;
        $melhor = null;

        foreach ($candidatos as $equipamento) {
            $orcamento = $this->orcamentoDoAparelho($equipamento, $cupom, $cenario);

            if ($orcamento === null) {
                continue;
            }

            if ($melhor === null || $orcamento['custo'] < $melhor['custo']
                || ($orcamento['custo'] === $melhor['custo'] && $equipamento['id'] < $melhor['equipamento']['id'])) {
                $melhor = $orcamento;
            }
        }

        if ($melhor === null) {
            return [...$vazio, 'faltando' => ['preço de adesão ou aluguel do aparelho']];
        }

        return $melhor;
    }

    private function orcamentoDoAparelho(array $equipamento, ?array $cupom, Cenario $cenario): ?array
    {
        $adesaoVigente = $equipamento['preco_adesao_promocional'] ?? $equipamento['preco_adesao'];
        $aluguel = $equipamento['aluguel_mensal'];

        if ($adesaoVigente === null && $aluguel === null) {
            return null;
        }

        $avisos = [];

        if ($adesaoVigente === null) {
            $avisos[] = "O aparelho {$equipamento['nome']} está cadastrado sem preço de adesão: "
                .'a comparação considerou apenas o aluguel.';
        }

        if ($aluguel === null) {
            $avisos[] = "O aparelho {$equipamento['nome']} está cadastrado sem aluguel mensal: "
                .'a comparação considerou o aparelho como compra.';
        }

        $adesaoCheia = (float) ($adesaoVigente ?? 0.0);
        $aluguelMensal = Dinheiro::arredondar((float) ($aluguel ?? 0.0));

        // Regra 5: o cupom desconta a adesao. Nunca o percentual da taxa.
        // Etapa 17: cupom com valor nao informado (desconto real, mas sem
        // numero - PagBank e Mercado Pago) nao entra na conta: 0 mentiria
        // "sem desconto", quando o certo e "desconto existe, sem numero".
        $desconto = 0.0;

        if ($cupom !== null && $cupom['valor'] !== null
            && ($cupom['equipamento_id'] === null || $cupom['equipamento_id'] === $equipamento['id'])) {
            $desconto = $cupom['tipo_desconto'] === 'percentual'
                ? Dinheiro::arredondar($adesaoCheia * (float) $cupom['valor'] / 100)
                : Dinheiro::arredondar((float) $cupom['valor']);

            $desconto = min($desconto, $adesaoCheia);
        }

        $adesaoFinal = Dinheiro::arredondar($adesaoCheia - $desconto);
        $amortizada = Dinheiro::arredondar($adesaoFinal / $cenario->horizonteMeses);

        return [
            'custo' => Dinheiro::arredondar($aluguelMensal + $amortizada),
            'equipamento' => [
                'id' => $equipamento['id'],
                'nome' => $equipamento['nome'],
                'slug' => $equipamento['slug'],
                'aluguel_mensal' => $aluguelMensal,
            ],
            'adesao' => [
                'preco_cheio' => Dinheiro::doBanco($equipamento['preco_adesao']),
                'preco_promocional' => Dinheiro::doBanco($equipamento['preco_adesao_promocional']),
                'vigente' => $adesaoVigente === null ? null : $adesaoCheia,
                'desconto_do_cupom' => $desconto,
                'valor_final' => $adesaoFinal,
                'amortizada_em_meses' => $cenario->horizonteMeses,
                // Quantas vezes sem juros a marca parcela a adesao, e quanto da
                // a parcela dela. Nulo e "a marca nao declarou", nunca "e a
                // vista". Nao confundir com por_mes logo abaixo: aquilo e
                // criterio nosso de comparacao, isto e oferta da marca.
                'parcelas_oferecidas' => $equipamento['parcelas_adesao'] ?? null,
                'parcela_da_marca' => ($equipamento['parcelas_adesao'] ?? null)
                    ? Dinheiro::arredondar($adesaoFinal / $equipamento['parcelas_adesao'])
                    : null,
                // Arredondar a parcela ao centavo faz o produto por 12 nao
                // reconstituir a adesao exata (199,00 / 12 = 16,58; 16,58 x 12
                // = 198,96). Por isso valor_final anda junto no resultado.
                'por_mes' => $amortizada,
            ],
            'cupom' => $desconto > 0.0 ? $cupom : null,
            'faltando' => [],
            'avisos' => $avisos,
        ];
    }

    /** Regra 5: cupom vence sozinho. Vigencia e conferida contra o "hoje" do cenario. */
    private function cupomVigente(array $marca, Cenario $cenario): ?array
    {
        foreach ($marca['cupons'] as $cupom) {
            if ($cupom['valido_de'] <= $cenario->hoje && $cupom['valido_ate'] >= $cenario->hoje) {
                return $cupom;
            }
        }

        return null;
    }

    /**
     * Etapa 05, decisao 3. A antecipacao automatica ja esta dentro do
     * percentual quando o prazo declara antecipacao_embutida - nesses casos o
     * motor nao soma nada e registra o aviso. A antecipacao avulsa so incide
     * sobre o que ainda nao foi antecipado.
     */
    private function custoDaAntecipacaoAvulsa(array $catalogo, array $plano, Cenario $cenario, array $linhas): array
    {
        if (! $cenario->antecipacaoAvulsa) {
            return ['custo' => 0.0, 'faltando' => [], 'avisos' => [], 'itens' => []];
        }

        $total = 0.0;
        $faltando = [];
        $avisos = [];
        $itens = [];

        foreach ($cenario->vendas as $indice => $venda) {
            $linha = $linhas[$indice] ?? null;

            if ($linha === null || $linha['falta'] !== null || $linha['prazo'] === null) {
                continue;
            }

            $prazo = $catalogo['prazos'][$linha['prazo']];

            if ($prazo['antecipacao_embutida']) {
                $avisos[] = 'Antecipação não foi cobrada em '.$venda->rotulo($catalogo['grupos']).': o percentual do prazo "'
                    .$prazo['nome'].'" já embute o adiantamento.';

                continue;
            }

            if ($plano['conta']['taxa_antecipacao_mensal'] === null) {
                $faltando[] = 'taxa de antecipação avulsa do plano';

                continue;
            }

            $custo = $this->antecipacaoDaLinha(
                $catalogo, $plano, $venda,
                ['prazo' => $linha['prazo']],
                $linha['custo'] ?? 0.0,
                $cenario,
            );

            $total += $custo['custo'];
            $itens[] = ['venda' => $venda->rotulo($catalogo['grupos']), 'meses' => $custo['meses'], 'custo' => $custo['custo']];
        }

        return [
            'custo' => Dinheiro::arredondar($total),
            'faltando' => $faltando,
            'avisos' => $avisos,
            'itens' => $itens,
        ];
    }

    /**
     * Antecipacao avulsa de uma linha, em reais no mes.
     *
     * Incide sobre o que sobra a receber (valor da venda menos o custo da
     * taxa), pelo numero de meses que o recebivel espera. Os meses vem da
     * dimensao: dias/30, ou (parcelas + 1) / 2 quando cada parcela cai no mes
     * dela - a media dos meses de espera das n parcelas de mesmo valor.
     */
    private function antecipacaoDaLinha(
        array $catalogo,
        array $plano,
        VendaDoCenario $venda,
        array $taxa,
        float $custoDaLinha,
        Cenario $cenario,
    ): array {
        $mensal = $plano['conta']['taxa_antecipacao_mensal'];

        if (! $cenario->antecipacaoAvulsa || $mensal === null) {
            return ['custo' => 0.0, 'meses' => 0.0];
        }

        $prazo = $catalogo['prazos'][$taxa['prazo']] ?? null;

        if ($prazo === null || $prazo['antecipacao_embutida']) {
            return ['custo' => 0.0, 'meses' => 0.0];
        }

        $meses = $prazo['dias'] !== null
            ? $prazo['dias'] / 30
            : ($venda->parcelas + 1) / 2;

        $base = $venda->valorMensal - $custoDaLinha;

        return [
            'custo' => Dinheiro::arredondar($base * (float) $mensal / 100 * $meses),
            'meses' => $meses,
        ];
    }

    /**
     * Regra 8: o selo de frescor viaja no resultado, nao so na pagina.
     *
     * E recalculado contra o "hoje" do cenario, e nao gravado na geracao do
     * JSON: o JSON e estatico e os dias passam. Um arquivo gerado ha 60 dias
     * tem de dizer "desatualizada" sozinho.
     *
     * O nivel do plano e o pior entre as taxas usadas - basta uma velha para o
     * conjunto nao ser fresco.
     */
    private function frescor(array $datas, array $catalogo, Cenario $cenario): array
    {
        $datas = array_values(array_filter($datas, fn (?string $d): bool => $d !== null && $d !== ''));

        if ($datas === []) {
            return ['nivel' => 'sem_data', 'data_verificacao' => null, 'dias' => null];
        }

        $maisAntiga = min($datas);
        $dias = self::diferencaEmDias($maisAntiga, $cenario->hoje);
        $limite = $catalogo['dias_ate_degradar'];

        return [
            'nivel' => $dias <= $limite ? 'fresca' : 'desatualizada',
            'data_verificacao' => $maisAntiga,
            'dias' => $dias,
        ];
    }

    /** Dias inteiros entre duas datas Y-m-d, sem fuso no meio do caminho. */
    public static function diferencaEmDias(string $de, string $ate): int
    {
        $inicio = strtotime($de.' 00:00:00 UTC');
        $fim = strtotime($ate.' 00:00:00 UTC');

        return (int) (($fim - $inicio) / 86400);
    }

    /**
     * Regra 11: a volta para pt-BR acontece na saida do motor, e nao na
     * pagina. Se cada tela formatasse por conta propria, uma delas escreveria
     * 2,5% em vez de 2,50% mais cedo ou mais tarde.
     *
     * Os numeros crus continuam no resultado, ao lado dos formatados: quem
     * precisa ordenar, somar ou testar usa o numero; quem exibe usa a string.
     */
    private function formatar(array $item): array
    {
        $custos = $item['custos'];
        $faixa = $item['custos_faixa'];
        $adesao = $item['adesao'];

        $item['vendas'] = array_map(function (array $linha): array {
            if (array_key_exists('percentual', $linha)) {
                $linha['percentual_formatado'] = Dinheiro::percentual($linha['percentual']);
            }

            if (array_key_exists('percentual_mediana', $linha)) {
                $linha['percentual_mediana_formatado'] = Dinheiro::percentual($linha['percentual_mediana']);
                $linha['percentual_minimo_formatado'] = Dinheiro::percentual($linha['percentual_minimo']);
                $linha['percentual_maximo_formatado'] = Dinheiro::percentual($linha['percentual_maximo']);
            }

            $linha['custo_formatado'] = isset($linha['custo']) ? Dinheiro::real($linha['custo']) : null;

            return $linha;
        }, $item['vendas']);

        $item['formatado'] = [
            'total_mensal' => isset($custos['total_mensal']) ? Dinheiro::real($custos['total_mensal']) : null,
            'total_mensal_parcial' => isset($custos['total_mensal_parcial'])
                ? Dinheiro::real($custos['total_mensal_parcial'])
                : null,
            'total_mensal_promocional' => isset($custos['total_mensal_promocional'])
                ? Dinheiro::real($custos['total_mensal_promocional'])
                : null,
            'total_mensal_promocional_parcial' => isset($custos['total_mensal_promocional_parcial'])
                ? Dinheiro::real($custos['total_mensal_promocional_parcial'])
                : null,
            'vendas' => $custos === null ? null : Dinheiro::real($custos['vendas']),
            'conta' => $custos === null ? null : Dinheiro::real($custos['conta']),
            'aparelho' => $custos === null ? null : Dinheiro::real($custos['aparelho']),
            'antecipacao_avulsa' => $custos === null ? null : Dinheiro::real($custos['antecipacao_avulsa']),
            // Faixa reportada nunca ganha uma string unica de total (regra 4).
            'faixa' => $faixa === null ? null : [
                'total_mensal_minimo' => Dinheiro::real($faixa['total_mensal_minimo']),
                'total_mensal_mediana' => Dinheiro::real($faixa['total_mensal_mediana']),
                'total_mensal_maximo' => Dinheiro::real($faixa['total_mensal_maximo']),
            ],
            'adesao' => $adesao === null ? null : [
                'valor_final' => Dinheiro::real($adesao['valor_final']),
                'por_mes' => Dinheiro::real($adesao['por_mes']),
                'desconto_do_cupom' => Dinheiro::real($adesao['desconto_do_cupom']),
                // "12x de R$ 16,58" - o jeito como a marca vende a adesao.
                'parcela_da_marca' => $adesao['parcela_da_marca'] === null
                    ? null
                    : $adesao['parcelas_oferecidas'].'x de '.Dinheiro::real($adesao['parcela_da_marca']),
            ],
            'frescor' => [
                'data_verificacao' => Dinheiro::data($item['frescor']['data_verificacao']),
                'dias' => $item['frescor']['dias'],
            ],
        ];

        return $item;
    }

    /**
     * Regra 4 de novo, agora na ordenacao: so o bloco CALCULADO e ranqueado por
     * preco. Faixa reportada, incompleto e sem dado publicado vem depois, em
     * blocos proprios - assim uma mediana de relatos nunca aparece disputando
     * a primeira posicao com um numero publicado.
     */
    private function ordenar(array $itens): array
    {
        usort($itens, function (array $a, array $b): int {
            $ordemA = EstadoDoResultado::from($a['estado'])->ordem();
            $ordemB = EstadoDoResultado::from($b['estado'])->ordem();

            if ($ordemA !== $ordemB) {
                return $ordemA <=> $ordemB;
            }

            $chaveA = $this->chaveDeOrdenacao($a);
            $chaveB = $this->chaveDeOrdenacao($b);

            return $chaveA <=> $chaveB
                ?: strcmp($a['marca']['nome'], $b['marca']['nome'])
                ?: (($a['plano']['id'] ?? 0) <=> ($b['plano']['id'] ?? 0));
        });

        return $itens;
    }

    private function chaveDeOrdenacao(array $item): float
    {
        return match (EstadoDoResultado::from($item['estado'])) {
            EstadoDoResultado::Calculado => (float) $item['custos']['total_mensal'],
            EstadoDoResultado::Promocional => (float) ($item['custos']['total_mensal_promocional']
                ?? $item['custos']['total_mensal_promocional_parcial']),
            EstadoDoResultado::Incompleto => (float) $item['custos']['total_mensal_parcial'],
            EstadoDoResultado::FaixaReportada => (float) $item['custos_faixa']['total_mensal_mediana'],
            EstadoDoResultado::SemDadoPublicado => 0.0,
        };
    }
}
