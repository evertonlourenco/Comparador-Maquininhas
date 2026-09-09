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
use Tests\Support\CenariosDeBorda;
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

        foreach (CenariosDeBorda::todos() as $nome => [$catalogo, $dados]) {
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
