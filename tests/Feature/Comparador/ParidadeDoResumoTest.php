<?php

namespace Tests\Feature\Comparador;

use App\Motor\CatalogoDoComparador;
use App\Motor\Cenario;
use App\Motor\MotorDeCalculo;
use App\Motor\ResumoDoComparador;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\Support\CatalogoDeTeste;
use Tests\Support\CenariosDeBorda;
use Tests\TestCase;

/**
 * Etapa 07: a tela do comparador nao le o resultado cru do motor - ela le os
 * quatro numeros de App\Motor\ResumoDoComparador (custo mensal recorrente,
 * taxa efetiva combinada, custo inicial com e sem cupom, quanto sobra no mes).
 * Como o comparador roda no navegador (regra 9), esses quatro numeros existem
 * duas vezes, e este teste e o que impede as duas de divergirem.
 *
 * Ele e o irmao de ParidadeDoMotorTest e roda sobre exatamente os mesmos casos
 * de borda da etapa 05 - Tests\Support\CenariosDeBorda -, de proposito: os
 * cinco estados da regra 4, o cupom vencido, o meio centavo que separa round()
 * do PHP de Math.round() do JavaScript, a promocao de entrada do Ton e os
 * percentuais de quatro casas da carga real. Se um caso novo entrar naquela
 * lista, ele passa a ser cobrado aqui tambem sem ninguem precisar lembrar.
 *
 * A cadeia refeita no Node e a inteira (motor e depois resumo), porque e o que
 * o navegador faz. Um campo divergente no caminho `.itens[3].custos` acusa o
 * motor; em `.itens[3].comparacao` ou `.resumo`, acusa esta camada.
 */
class ParidadeDoResumoTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_resumo_em_javascript_da_o_mesmo_resultado_do_resumo_em_php(): void
    {
        $node = $this->node();

        $this->seed(DatabaseSeeder::class);

        $catalogos = [
            'sintetico' => CatalogoDeTeste::montar(),
            // Regra 10: a carga esta toda em rascunho. Aqui interessa a
            // aritmetica sobre numeros reais, nao o que ja foi aprovado.
            'real' => app(CatalogoDoComparador::class)->montar(incluirRascunhos: true),
        ];

        $motor = new MotorDeCalculo;
        $resumo = new ResumoDoComparador;
        $casos = [];

        foreach (CenariosDeBorda::todos() as $nome => [$catalogo, $dados]) {
            $casos[] = [
                'nome' => $nome,
                'catalogo' => $catalogo,
                'cenario' => $dados,
                'esperado' => $resumo->resumir(
                    $motor->calcular($catalogos[$catalogo], Cenario::deArray($dados)),
                ),
            ];
        }

        $arquivo = storage_path('framework/testing/paridade-do-resumo.json');
        File::ensureDirectoryExists(dirname($arquivo));
        File::put($arquivo, json_encode(
            ['catalogos' => $catalogos, 'casos' => $casos],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $resultado = Process::path(base_path())
            ->run([$node, 'scripts/verifica-resumo-js.mjs', $arquivo]);

        File::delete($arquivo);

        $this->assertSame(
            0,
            $resultado->exitCode(),
            "O resumo em JavaScript divergiu do resumo em PHP.\n"
            .$resultado->errorOutput()."\n".$resultado->output(),
        );

        $this->assertStringContainsString(count($casos).' caso(s) conferem', $resultado->output());
    }

    /**
     * Os quatro numeros da tela, conferidos a mao no catalogo sintetico.
     *
     * A paridade acima garante que os dois idiomas concordam; ela nao garante
     * que os dois estao certos. Este caso fixa o valor.
     *
     * Alfa cobra 1,00% em debito e 3,15% em credito a vista em D+1, nao tem
     * mensalidade, vende o aparelho por R$ 199,00 e tem cupom de R$ 50,00.
     * Epsilon cobra menos na venda (0,50% e 2,00%) e cobra R$ 49,90 de
     * mensalidade - e por isso as duas taxas efetivas do resumo existem: na
     * Alfa elas coincidem, na Epsilon a combinada e mais que o dobro da taxa
     * das vendas. Um comparador que mostrasse so a das vendas apontaria para
     * a Epsilon.
     */
    public function test_os_quatro_numeros_da_tela_batem_com_a_conta_feita_a_mao(): void
    {
        $catalogo = CatalogoDeTeste::montar();

        $resultado = (new ResumoDoComparador)->resumir((new MotorDeCalculo)->calcular($catalogo, Cenario::deArray([
            'faturamento_mensal' => '4.000,00',
            'hoje' => '2026-09-08',
            'prazo' => 'd_1',
            'vendas' => [
                ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                    'valor_mensal' => '1.000,00', 'quantidade_mensal' => 50],
                ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                    'valor_mensal' => '2.000,00', 'quantidade_mensal' => 40],
            ],
        ])));

        $alfa = $this->itemDe($resultado, 'Alfa');
        $comparacao = $alfa['comparacao'];

        // Vendas: 10,00 + 63,00. Conta: sem mensalidade e sem servico usado.
        // Aparelho: comprado (aluguel nulo vira zero, com aviso), adesao de
        // R$ 199,00 menos o cupom de R$ 50,00, dividida por 12 = R$ 12,42.
        $this->assertSame('calculado', $alfa['estado']);
        $this->assertSame(73.0, $comparacao['custo_mensal_recorrente']);
        $this->assertSame(12.42, $comparacao['adesao_amortizada']);
        $this->assertSame(85.42, $comparacao['custo_mensal_total']);

        // Taxa efetiva combinada sobre os R$ 3.000,00 que passam na
        // maquininha - nao sobre os R$ 4.000,00 de faturamento.
        $this->assertSame(3000.0, $comparacao['volume_vendido']);
        $this->assertSame(2.43, $comparacao['taxa_efetiva_combinada']);
        $this->assertSame('2,43%', $comparacao['formatado']['taxa_efetiva_combinada']);

        // Custo inicial: regra 5, os dois precos lado a lado.
        $this->assertSame(199.0, $comparacao['custo_inicial']['sem_cupom']);
        $this->assertSame(149.0, $comparacao['custo_inicial']['com_cupom']);
        $this->assertSame(50.0, $comparacao['custo_inicial']['economia']);
        $this->assertSame('ALFA50', $comparacao['custo_inicial']['cupom']);
        // A marca parcela em 10x; a amortizacao do motor e em 12 meses. Os
        // dois numeros vivem lado a lado e nunca somam (etapa 05).
        $this->assertSame(10, $comparacao['custo_inicial']['parcelas_oferecidas']);

        // Quanto sobra: 4.000,00 - 85,42.
        $this->assertSame(3914.58, $comparacao['sobra_no_mes']);
        $this->assertSame('R$ 3.914,58', $comparacao['formatado']['sobra_no_mes']);

        // O que nao passa na maquininha nao custa taxa nenhuma, e aparece.
        $this->assertSame(1000.0, $resultado['resumo']['fora_da_maquininha']);

        // Epsilon: 5,00 + 40,00 de venda e R$ 49,90 de mensalidade. A taxa das
        // vendas e 1,50%; a combinada, que e a que o lojista paga de fato, e
        // 3,16%.
        $epsilon = $this->itemDe($resultado, 'Epsilon')['comparacao'];

        $this->assertSame(94.9, $epsilon['custo_mensal_recorrente']);
        $this->assertSame(1.5, $epsilon['taxa_efetiva_das_vendas']);
        $this->assertSame(3.16, $epsilon['taxa_efetiva_combinada']);
        // Sem cupom cadastrado, os dois precos iniciais sao o mesmo - e o
        // preco promocional do aparelho e o que vale.
        $this->assertSame(60.0, $epsilon['custo_inicial']['sem_cupom']);
        $this->assertSame(60.0, $epsilon['custo_inicial']['com_cupom']);
        $this->assertFalse($epsilon['custo_inicial']['tem_cupom']);
    }

    /**
     * Regra 4 no formato do resumo: um numero que nao e exato, nao e
     * permanente ou nao fecha nao pode ter o nome do numero que e - nem aqui,
     * como nao tem no motor.
     */
    public function test_a_chave_do_numero_muda_de_nome_junto_com_o_estado(): void
    {
        $catalogo = CatalogoDeTeste::montar();

        $resultado = (new ResumoDoComparador)->resumir((new MotorDeCalculo)->calcular($catalogo, Cenario::deArray([
            'faturamento_mensal' => '3.000,00',
            'hoje' => '2026-09-08',
            'prazo' => 'd_1',
            'vendas' => [
                ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                    'valor_mensal' => '1.000,00', 'quantidade_mensal' => 50],
                ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                    'valor_mensal' => '2.000,00', 'quantidade_mensal' => 40],
            ],
        ])));

        $porEstado = [];

        foreach ($resultado['itens'] as $item) {
            $porEstado[$item['estado']] ??= $item;
        }

        $this->assertArrayHasKey('custo_mensal_recorrente', $porEstado['calculado']['comparacao']);

        $this->assertArrayHasKey(
            'custo_mensal_recorrente_promocional',
            $porEstado['promocional']['comparacao'],
        );
        $this->assertArrayNotHasKey('custo_mensal_recorrente', $porEstado['promocional']['comparacao']);

        // Faixa reportada nunca ganha valor unico: as tres pontas, ou nada.
        $faixa = $porEstado['faixa_reportada']['comparacao'];
        $this->assertArrayNotHasKey('custo_mensal_recorrente', $faixa);
        $this->assertArrayNotHasKey('taxa_efetiva_combinada', $faixa);
        foreach (['minimo', 'mediana', 'maximo'] as $ponta) {
            $this->assertArrayHasKey('custo_mensal_recorrente_'.$ponta, $faixa);
        }
        foreach (['minima', 'mediana', 'maxima'] as $ponta) {
            $this->assertArrayHasKey('taxa_efetiva_combinada_'.$ponta, $faixa);
        }

        // Marca sem dado publicado nao ganha numero nenhum: zero seria mentira.
        $this->assertNull($porEstado['sem_dado_publicado']['comparacao']);

        // O estado incompleto pede um cenario que peca o que a marca nao
        // publica. A Beta nao vende parcelado em nenhum prazo.
        $comParcelado = (new ResumoDoComparador)->resumir((new MotorDeCalculo)->calcular($catalogo, Cenario::deArray([
            'faturamento_mensal' => '3.000,00',
            'hoje' => '2026-09-08',
            'prazo' => 'd_1',
            'vendas' => [
                ['tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master', 'parcelas' => 6,
                    'valor_mensal' => '2.000,00', 'quantidade_mensal' => 20],
            ],
        ])));

        $incompleto = null;

        foreach ($comParcelado['itens'] as $item) {
            if ($item['estado'] === 'incompleto') {
                $incompleto = $item['comparacao'];

                break;
            }
        }

        $this->assertNotNull($incompleto, 'Nenhum item incompleto no cenario com parcelado.');
        $this->assertArrayHasKey('custo_mensal_recorrente_parcial', $incompleto);
        $this->assertArrayNotHasKey('custo_mensal_recorrente', $incompleto);
    }

    /**
     * Melhor e pior saem so do bloco ranqueavel. Uma promocao de 30 dias ou
     * uma mediana de relatos nunca podem aparecer como "a melhor opcao".
     */
    public function test_melhor_e_pior_so_olham_para_o_bloco_calculado(): void
    {
        $catalogo = CatalogoDeTeste::montar();

        $resultado = (new ResumoDoComparador)->resumir((new MotorDeCalculo)->calcular($catalogo, Cenario::deArray([
            'faturamento_mensal' => '3.000,00',
            'hoje' => '2026-09-08',
            'prazo' => 'd_1',
            'vendas' => [
                ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                    'valor_mensal' => '1.000,00', 'quantidade_mensal' => 50],
            ],
        ])));

        $resumo = $resultado['resumo'];
        $ranqueaveis = array_values(array_filter(
            $resultado['itens'],
            fn (array $item): bool => $item['estado'] === 'calculado',
        ));

        $this->assertGreaterThanOrEqual(2, count($ranqueaveis));
        $this->assertSame($ranqueaveis[0]['marca']['nome'], $resumo['melhor']['marca']);
        $this->assertSame(end($ranqueaveis)['marca']['nome'], $resumo['pior']['marca']);

        // Etapa 20: o ranking e a diferenca olham o custo recorrente (sem a
        // adesao amortizada), nao o total.
        $this->assertLessThanOrEqual(
            $resumo['pior']['custo_mensal_recorrente'],
            $resumo['melhor']['custo_mensal_recorrente'],
        );

        $this->assertSame(
            round($resumo['pior']['custo_mensal_recorrente'] - $resumo['melhor']['custo_mensal_recorrente'], 2),
            $resumo['diferenca_mensal'],
        );

        // O horizonte do cenario, e nao um 12 escondido no codigo.
        $this->assertSame(
            round($resumo['diferenca_mensal'] * $resumo['horizonte_meses'], 2),
            $resumo['diferenca_no_horizonte'],
        );
    }

    private function itemDe(array $resultado, string $marca): array
    {
        foreach ($resultado['itens'] as $item) {
            if ($item['marca']['nome'] === $marca && $item['estado'] === 'calculado') {
                return $item;
            }
        }

        $this->fail("Nenhum item calculado da marca {$marca} no resultado.");
    }

    /** Sem Node nao ha o que comparar - e um teste que passa em silencio mente. */
    private function node(): string
    {
        foreach (['node', '/opt/homebrew/bin/node', '/usr/local/bin/node'] as $candidato) {
            if (Process::run(['which', $candidato])->successful() || is_executable($candidato)) {
                return $candidato;
            }
        }

        $this->markTestSkipped(
            'Node nao encontrado no PATH. O teste de paridade do resumo (etapa 07) so roda com '
            .'Node instalado.'
        );
    }
}
