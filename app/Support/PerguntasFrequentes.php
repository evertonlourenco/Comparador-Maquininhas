<?php

namespace App\Support;

/**
 * As 10 perguntas frequentes do Máquina Certa (etapa 20, bloco F, 18/09/2026).
 *
 * Conteúdo fixo, não dado de catálogo — por isso mora aqui como código, não
 * como tabela. Fonte única entre a página própria (/perguntas-frequentes, as
 * 10) e o bloco do fim da home (as 4 marcadas como `destaque`).
 *
 * Separada da metodologia de propósito: a metodologia responde "posso
 * confiar nos números?" (de onde vem cada taxa); esta página responde "como
 * eu contrato e o que acontece depois?" — perguntas e buscas diferentes.
 *
 * `resposta` é HTML curto (pode conter `<a>`), renderizado com `{!! !!}` na
 * página e reduzido a texto puro (`strip_tags`) para o `FAQPage` em
 * schema.org — regra 6 do domínio vale também para texto: nada aqui afirma
 * algo que o próprio texto da marca ou a regra do motor não sustente. As
 * perguntas 6 e 7 variam de marca para marca e por isso ficam deliberadamente
 * gerais, sem inventar uma resposta única que não valeria para todas.
 */
final class PerguntasFrequentes
{
    /** @return list<array{pergunta: string, resposta: string, destaque: bool}> */
    public static function todas(): array
    {
        $metodologia = route('metodologia');
        $cupons = route('cupons.index');

        return [
            [
                'pergunta' => 'A taxa pelo link do Máquina Certa é a mesma do site oficial?',
                'resposta' => 'Sim, sempre. A taxa que você vê aqui é exatamente a mesma taxa '
                    .'publicada na página oficial da marca — nunca existe uma taxa paralela, maior '
                    .'ou menor, por vir do nosso link. O que um cupom de desconto muda, quando a '
                    .'marca tem um, é só o custo da adesão do aparelho, nunca o percentual cobrado '
                    .'por venda. Veja mais em '
                    ."<a href=\"{$metodologia}#s-comissao\" class=\"text-link underline underline-offset-4 hover:no-underline\">como ganhamos dinheiro</a>.",
                'destaque' => true,
            ],
            [
                'pergunta' => 'Como uso o cupom de desconto?',
                'resposta' => 'Depende da marca. Em algumas, o desconto entra sozinho ao clicar em '
                    .'"Contratar" no cartão do resultado ou na página da marca — o link já carrega '
                    .'o cupom aplicado. Em outras, você copia o código mostrado ao lado do cupom '
                    .'(botão "Copiar código") e cola no checkout do site oficial, no campo de '
                    .'cupom. Nos dois casos, a taxa continua sendo a mesma do site oficial — o '
                    .'cupom desconta só a adesão. Veja o código e a condição de cada marca em '
                    ."<a href=\"{$cupons}\" class=\"text-link underline underline-offset-4 hover:no-underline\">Cupons</a>.",
                'destaque' => false,
            ],
            [
                'pergunta' => 'O Máquina Certa cobra alguma coisa? Como vocês ganham dinheiro?',
                'resposta' => 'Nada. Comparar é gratuito, sem cadastro. Ganhamos uma comissão da '
                    .'própria maquininha quando alguém contrata por um dos nossos links — isso não '
                    .'muda a taxa que você paga (é a mesma do site oficial) nem a ordem do '
                    .'resultado: o comparador ranqueia sempre pelo menor custo mensal calculado, e '
                    .'uma marca que paga comissão maior não sobe de posição por isso.',
                'destaque' => true,
            ],
            [
                'pergunta' => 'Qual a diferença entre receber na hora e em 1 dia útil?',
                'resposta' => 'É o prazo em que o dinheiro da venda cai na sua conta. "Na hora" cai '
                    .'no momento da venda; "Em 1 dia útil" (D+1) cai no dia útil seguinte. Como '
                    .'antecipar o recebível tem custo, a taxa de "na hora" costuma ser mais alta do '
                    .'que a de D+1 na mesma marca e no mesmo plano — é o preço de receber mais '
                    .'rápido. No passo 3 do comparador, escolher "Tanto faz" deixa cada marca usar '
                    .'o prazo que fecha a conta mais barata para o seu cenário.',
                'destaque' => false,
            ],
            [
                'pergunta' => 'Taxa promocional: o que acontece quando o período acaba?',
                'resposta' => 'A taxa promocional (tabela de entrada) vale só por um tempo limitado '
                    .'— geralmente 30 dias ou até um teto de valor processado, o que vier primeiro, '
                    .'conforme a própria marca declara. Terminado esse período, a conta passa '
                    .'automaticamente para o plano permanente da marca, com a taxa regular dele. No '
                    .'cartão do resultado, o selo "Tem tabela de entrada por tempo limitado" abre '
                    .'um resumo com os dois limites e o plano para o qual você migra depois.',
                'destaque' => true,
            ],
            [
                'pergunta' => 'A maquininha fica comigo? O que acontece se quebrar ou se eu cancelar?',
                'resposta' => 'Isso é condição do contrato de cada marca, e varia: algumas '
                    .'emprestam o aparelho enquanto sua conta estiver ativa (comodato), outras '
                    .'vendem o aparelho de fato. O valor de adesão mostrado aqui é o que a própria '
                    .'marca cobra por isso, com ou sem cupom. Para saber o que acontece em caso de '
                    .'defeito ou cancelamento, confira o contrato ou fale com o suporte da marca '
                    .'antes de contratar — ainda não temos esse detalhe padronizado para todas.',
                'destaque' => false,
            ],
            [
                'pergunta' => 'Preciso de CNPJ ou dá para usar CPF?',
                'resposta' => 'A maioria das marcas do comparador aceita cadastro por CPF, '
                    .'incluindo autônomo sem MEI — não só CNPJ. Mas as condições (taxa, limite de '
                    .'recebimento, documentos pedidos) podem variar entre CPF e CNPJ dentro da '
                    .'mesma marca, e isso muda de marca para marca. Confirme no cadastro da marca '
                    .'escolhida antes de contratar.',
                'destaque' => false,
            ],
            [
                'pergunta' => 'Tem mensalidade ou aluguel?',
                'resposta' => 'Também varia por marca e por plano. Algumas não cobram nada além da '
                    .'taxa por venda; outras cobram mensalidade fixa, ou aluguel mensal do '
                    .'aparelho. Na tabela de taxas de cada marca, e no resultado do comparador, '
                    .'você vê exatamente o que aquele plano cobra — quando o campo não aparece, é '
                    .'porque a marca não publica mensalidade nenhuma para esse plano.',
                'destaque' => false,
            ],
            [
                'pergunta' => 'O Pix na maquininha é grátis?',
                'resposta' => 'Depende da marca — algumas cobram 0% no Pix, outras cobram um '
                    .'percentual real. Quando o 0% depende de alguma condição (como ativar uma '
                    .'opção no aplicativo da marca), isso aparece junto do número, no ícone "?" ao '
                    .'lado da taxa de Pix, no cartão do resultado ou na tabela da marca.',
                'destaque' => true,
            ],
            [
                'pergunta' => 'Por que algumas marcas aparecem com faixa de taxa em vez de número exato?',
                'resposta' => 'Cielo, Rede, GetNet e Stone não publicam uma tabela de taxas aberta '
                    .'ao público — o preço delas sai só numa proposta comercial, negociada caso a '
                    .'caso. Sem uma página oficial para citar como fonte, não fingimos ter um '
                    .'número exato: mostramos a faixa (mínimo, mediana e máximo) do que lojistas '
                    .'reais relataram ter recebido, com o número de relatos ao lado. Veja mais em '
                    ."<a href=\"{$metodologia}#s-faixa\" class=\"text-link underline underline-offset-4 hover:no-underline\">Metodologia</a>.",
                'destaque' => false,
            ],
        ];
    }

    /** @return list<array{pergunta: string, resposta: string, destaque: bool}> */
    public static function destaque(): array
    {
        return array_values(array_filter(
            self::todas(),
            static fn (array $p): bool => $p['destaque'],
        ));
    }

    /** O FAQPage de schema.org, com resposta reduzida a texto puro. */
    public static function schema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $p): array => [
                '@type' => 'Question',
                'name' => $p['pergunta'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => trim(preg_replace('/\s+/', ' ', strip_tags($p['resposta']))),
                ],
            ], self::todas()),
        ];
    }
}
