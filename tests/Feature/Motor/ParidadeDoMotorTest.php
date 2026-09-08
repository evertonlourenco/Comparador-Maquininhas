<?php

namespace Tests\Feature\Motor;

use App\Motor\CatalogoDoComparador;
use App\Motor\Cenario;
use App\Motor\MotorDeCalculo;
use App\Support\Dinheiro;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\Support\CatalogoDeTeste;
use Tests\TestCase;

/**
 * Etapa 05, decisao 2: a formula vive em PHP e em JavaScript, e este teste e o
 * que impede as duas de divergirem.
 *
 * Por que duplicar. A regra 9 manda o comparador rodar no navegador sobre um
 * JSON estatico. As entradas do lojista formam um espaco continuo - faturamento
 * em reais, mix de vendas, parcelas, horizonte -, entao nao ha como o JSON
 * carregar resultado pronto para toda combinacao sem congelar a granularidade
 * das perguntas. A aritmetica tem de rodar no navegador. E o PHP precisa da
 * mesma conta para o painel, para os testes de valor conhecido e para o
 * gerador saber o que esta exportando.
 *
 * Como se segura a duplicacao. O motor em PHP e a fonte da verdade: ele produz
 * o resultado esperado de cada caso, o arquivo de casos vai para o Node, e o
 * motor em JavaScript e cobrado campo a campo - inclusive nas strings ja
 * formatadas em pt-BR, que e onde round() e Math.round() discordariam.
 *
 * Os casos cobrem o catalogo sintetico (valores conhecidos, todos os quatro
 * estados da regra 4) e a carga real (964 taxas, onde estao os numeros feios).
 */
class ParidadeDoMotorTest extends TestCase
{
    use RefreshDatabase;

    public function test_o_motor_em_javascript_da_o_mesmo_resultado_do_motor_em_php(): void
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
        $casos = [];

        foreach ($this->cenarios() as $nome => [$catalogo, $dados]) {
            $cenario = Cenario::deArray($dados);

            $casos[] = [
                'nome' => $nome,
                'catalogo' => $catalogo,
                'cenario' => $dados,
                'esperado' => $motor->calcular($catalogos[$catalogo], $cenario),
            ];
        }

        $arquivo = storage_path('framework/testing/paridade-do-motor.json');
        File::ensureDirectoryExists(dirname($arquivo));
        File::put($arquivo, json_encode(
            ['catalogos' => $catalogos, 'casos' => $casos, 'centavos' => $this->tabelaDeCentavos()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $resultado = Process::path(base_path())
            ->run([$node, 'scripts/verifica-motor-js.mjs', $arquivo]);

        File::delete($arquivo);

        $this->assertSame(
            0,
            $resultado->exitCode(),
            "O motor em JavaScript divergiu do motor em PHP.\n"
            .$resultado->errorOutput()."\n".$resultado->output(),
        );

        $this->assertStringContainsString(count($casos).' caso(s) conferem', $resultado->output());
    }

    /**
     * @return array<string, array{0: string, 1: array}>
     */
    private function cenarios(): array
    {
        $vendasBasicas = [
            ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                'valor_mensal' => '1.000,00', 'quantidade_mensal' => 50],
            ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                'valor_mensal' => '2.000,00', 'quantidade_mensal' => 40],
        ];

        return [
            // Sinteticos: cobrem os quatro estados da regra 4 e os dois lados
            // da decisao 3.
            'sintetico: cenario fechado com cupom' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => 'd_1', 'vendas' => $vendasBasicas,
            ]],
            'sintetico: motor escolhe o prazo mais barato' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => null, 'vendas' => $vendasBasicas,
            ]],
            'sintetico: antecipacao ja embutida no percentual' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => 'd_1', 'antecipacao_avulsa' => true, 'vendas' => $vendasBasicas,
            ]],
            'sintetico: antecipacao avulsa parcela a parcela' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => 'parcela_a_parcela', 'antecipacao_avulsa' => true,
                'vendas' => [['tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master',
                    'parcelas' => 6, 'valor_mensal' => '2.000,00', 'quantidade_mensal' => 20]],
            ]],
            'sintetico: valor fixo sem quantidade informada' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => 'parcela_a_parcela',
                'vendas' => [['tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master',
                    'parcelas' => 6, 'valor_mensal' => '2.000,00']],
            ]],
            'sintetico: Pix e o grupo tecnico' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08', 'prazo' => null,
                'vendas' => [['tipo_operacao' => 'pix', 'valor_mensal' => '500,00', 'quantidade_mensal' => 30]],
            ]],
            'sintetico: cupom vencido e frescor degradado' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-10-17',
                'prazo' => 'd_1', 'vendas' => $vendasBasicas, 'saques_mensais' => 3,
                'teds_mensais' => 2, 'pix_envios_mensais' => 7,
            ]],
            // R$ 107,00 a 2,50% da R$ 2,675 exatos - o desempate que separa
            // round() do PHP de Math.round() do JavaScript. Sem um caso assim
            // a paridade passaria mesmo com o arredondamento errado de um lado.
            'sintetico: meio centavo no percentual da taxa' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08', 'prazo' => 'd_1',
                'vendas' => [['tipo_operacao' => 'debito', 'grupo' => 'demais', 'parcelas' => 1,
                    'valor_mensal' => '107,00', 'quantidade_mensal' => 3]],
            ]],
            'sintetico: horizonte de 24 meses' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08', 'prazo' => 'd_1',
                'horizonte_meses' => 24, 'vendas' => $vendasBasicas,
            ]],

            // Reais: percentuais de quatro casas e valores que nao caem
            // redondos no centavo.
            'real: faturamento de 10 mil em 1 dia util' => ['real', [
                'faturamento_mensal' => '10.000,00', 'hoje' => '2026-09-20', 'prazo' => 'd_1',
                'vendas' => [
                    ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '4.317,49', 'quantidade_mensal' => 213],
                    ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '3.982,51', 'quantidade_mensal' => 97],
                    ['tipo_operacao' => 'credito_parcelado', 'grupo' => 'demais', 'parcelas' => 6,
                        'valor_mensal' => '1.700,00', 'quantidade_mensal' => 11],
                ],
            ]],
            'real: sem prazo pedido, com antecipacao avulsa' => ['real', [
                'faturamento_mensal' => '45.000,00', 'hoje' => '2026-09-20', 'prazo' => null,
                'antecipacao_avulsa' => true, 'saques_mensais' => 4, 'teds_mensais' => 1,
                'vendas' => [
                    ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '20.000,00', 'quantidade_mensal' => 640],
                    ['tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master', 'parcelas' => 12,
                        'valor_mensal' => '25.000,00', 'quantidade_mensal' => 130],
                ],
            ]],
            // A promocao de entrada do Ton, que e onde os dois motores tem de
            // concordar tanto no estado quanto no texto do aviso de validade.
            'real: promocao de entrada do Ton' => ['real', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-20', 'prazo' => 'd_1',
                'vendas' => [
                    ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '1.500,00', 'quantidade_mensal' => 80],
                    ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '1.500,00', 'quantidade_mensal' => 40],
                ],
            ]],
            'real: Pix da InfinitePay' => ['real', [
                'faturamento_mensal' => '9.999,99', 'hoje' => '2026-09-20', 'prazo' => 'na_hora',
                'vendas' => [['tipo_operacao' => 'pix', 'valor_mensal' => '2.345,67', 'quantidade_mensal' => 88]],
            ]],
            'real: faturamento alto, todos os 21x do Ton' => ['real', [
                'faturamento_mensal' => '120.000,00', 'hoje' => '2026-11-30', 'prazo' => 'na_hora',
                'vendas' => [['tipo_operacao' => 'credito_parcelado', 'grupo' => 'demais', 'parcelas' => 21,
                    'valor_mensal' => '33.333,33', 'quantidade_mensal' => 7]],
            ]],
        ];
    }

    /**
     * O desempate de meio centavo, direto no primitivo.
     *
     * Os cenarios acima exercitam o motor inteiro, mas so encostam no meio
     * centavo por coincidencia. Esta tabela encosta de proposito: sao os
     * valores em que round() do PHP e Math.round() do JavaScript dao respostas
     * diferentes, mais alguns vizinhos para garantir que a correcao nao
     * empurrou o arredondamento inteiro para o lado errado.
     *
     * @return list<array{valor: float, casas: int, esperado: float, formatado: string}>
     */
    private function tabelaDeCentavos(): array
    {
        $valores = [
            [2.675, 2], [1.005, 2], [0.145, 2], [-2.675, 2], [8.615, 2],
            [1234.565, 2], [0.005, 2], [2.674, 2], [2.676, 2],
            [31.5, 2], [0.0, 2], [1234567.891, 2],
            [3.14159265, 4], [2.5, 0], [1.5, 0],
        ];

        return array_map(fn (array $par): array => [
            'valor' => $par[0],
            'casas' => $par[1],
            'esperado' => Dinheiro::arredondar($par[0], $par[1]),
            'formatado' => Dinheiro::numero($par[0], $par[1]),
        ], $valores);
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
            'Node nao encontrado no PATH. O teste de paridade entre o motor em PHP e o motor em '
            .'JavaScript (etapa 05, decisao 2) so roda com Node instalado.'
        );
    }
}
