<?php

namespace Tests\Feature\Motor;

use App\Models\Marca;
use App\Models\Plano;
use App\Models\TaxaDivulgada;
use App\Motor\CatalogoDoComparador;
use App\Motor\Cenario;
use App\Motor\EstadoDoResultado;
use App\Motor\MotorDeCalculo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * O motor come array puro e nao sabe o que e Eloquent - o que e bom para
 * testar, mas cria o risco de o array divergir do banco. Estes testes cobram
 * que as regras que existem em duas implementacoes (o scope de enquadramento e
 * o trait de frescor) concordem entre banco e motor sobre a carga real.
 */
class MotorSobreACargaRealTest extends TestCase
{
    use RefreshDatabase;

    private array $catalogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Regra 10: a carga inteira esta em rascunho. Sem incluir rascunho o
        // catalogo sairia vazio, e nao haveria o que conferir aqui.
        $this->catalogo = app(CatalogoDoComparador::class)->montar(incluirRascunhos: true);
    }

    public function test_o_catalogo_publicado_respeita_a_aprovacao_humana(): void
    {
        $publicado = app(CatalogoDoComparador::class)->montar(incluirRascunhos: false);

        $taxas = collect($publicado['marcas'])->flatMap(fn (array $m) => $m['planos'])
            ->sum(fn (array $p): int => count($p['taxas']));

        $this->assertSame(0, $taxas, 'Nada em rascunho pode vazar para o JSON de produção (regra 10).');
        $this->assertFalse($publicado['contem_rascunhos']);
        $this->assertTrue($this->catalogo['contem_rascunhos']);
    }

    public function test_nenhuma_marca_ativa_some_do_resultado(): void
    {
        $resultado = $this->calcular(10000.0);

        $nomes = collect($resultado['itens'])->pluck('marca.nome')->unique()->sort()->values();

        $this->assertEquals(
            Marca::ativas()->orderBy('nome')->pluck('nome')->sort()->values(),
            $nomes,
            'Regra 4: marca sem taxa não some do resultado.',
        );
    }

    public function test_marca_que_nao_publica_tabela_sai_como_sem_dado_e_nunca_como_zero(): void
    {
        $resultado = $this->calcular(10000.0);

        foreach (Marca::ativas()->where('publica_tabela', false)->pluck('nome') as $nome) {
            $itens = collect($resultado['itens'])->where('marca.nome', $nome);

            $this->assertTrue($itens->isNotEmpty(), "A marca {$nome} sumiu do resultado.");

            foreach ($itens as $item) {
                $this->assertNotSame(EstadoDoResultado::Calculado->value, $item['estado']);
                $this->assertNull($item['custos'], "{$nome} não pode ter custo calculado sem publicar tabela.");
            }
        }
    }

    /**
     * O motor reimplementa a condicao do scope Plano::paraFaturamento sobre o
     * array. Se as duas versoes divergirem, o comparador mostra plano que o
     * lojista nao pode contratar.
     */
    public function test_o_enquadramento_do_motor_bate_com_o_scope_do_model(): void
    {
        foreach ([1500.0, 3000.0, 7000.0, 25000.0, 90000.0] as $faturamento) {
            $doMotor = collect($this->calcular($faturamento)['itens'])
                ->pluck('plano.id')->filter()->sort()->values()->all();

            $doBanco = Plano::query()->ativos()->paraFaturamento($faturamento)->pluck('id')
                ->merge(Plano::query()->ativos()->where('tipo_enquadramento', '!=', 'automatico')->pluck('id'))
                ->sort()->values()->all();

            $this->assertSame($doBanco, $doMotor, "Faturamento de R$ {$faturamento}.");
        }
    }

    /**
     * Regra 8: o selo degrada sozinho. O trait calcula sobre o model; o motor
     * calcula sobre a data crua do JSON, contra o "hoje" do cenario. Os dois
     * tem de dar o mesmo dia.
     */
    public function test_o_frescor_do_motor_bate_com_o_trait(): void
    {
        $taxa = TaxaDivulgada::query()->orderBy('data_verificacao')->firstOrFail();
        $hoje = Carbon::today();

        $resultado = $this->calcular(10000.0, $hoje->toDateString());

        $item = collect($resultado['itens'])
            ->first(fn (array $i): bool => $i['frescor']['data_verificacao'] === $taxa->data_verificacao->toDateString());

        $this->assertNotNull($item, 'Nenhum item usou a taxa mais antiga da carga.');
        $this->assertSame($taxa->dias_desde_verificacao, $item['frescor']['dias']);
        $this->assertSame($taxa->nivel_frescor, $item['frescor']['nivel']);
    }

    /**
     * A carga da etapa 04 nao registrou mensalidade de Ton nem de PagBank, e
     * nao vinculou aparelho a plano em SumUp. O motor precisa dizer isso, e
     * nao completar com zero - e esta e a lista que o painel tem de fechar
     * antes de o comparador ir ao ar.
     */
    public function test_o_que_falta_na_carga_aparece_como_falta_e_nao_como_zero(): void
    {
        $resultado = $this->calcular(10000.0);

        $ton = collect($resultado['itens'])->firstWhere('marca.nome', 'Ton');

        $this->assertSame(EstadoDoResultado::Incompleto->value, $ton['estado']);
        $this->assertContains('mensalidade do plano', $ton['faltando']);
        $this->assertArrayNotHasKey('total_mensal', $ton['custos']);
        $this->assertArrayHasKey('total_mensal_parcial', $ton['custos']);
    }

    public function test_a_infinitepay_fecha_o_cenario_com_a_carga_atual(): void
    {
        $item = collect($this->calcular(10000.0)['itens'])->firstWhere('marca.nome', 'InfinitePay');

        $this->assertSame(EstadoDoResultado::Calculado->value, $item['estado']);

        // Adesao de R$ 199,00 sem cupom cadastrado, em 12 meses: R$ 16,58.
        $this->assertSame(199.0, $item['adesao']['valor_final']);
        $this->assertSame(16.58, $item['adesao']['por_mes']);
    }

    private function calcular(float $faturamento, ?string $hoje = null): array
    {
        return (new MotorDeCalculo)->calcular($this->catalogo, Cenario::deArray([
            'faturamento_mensal' => $faturamento,
            'hoje' => $hoje ?? '2026-09-20',
            'prazo' => 'd_1',
            'vendas' => [
                ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                    'valor_mensal' => '4.000,00', 'quantidade_mensal' => 200],
                ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                    'valor_mensal' => '4.000,00', 'quantidade_mensal' => 100],
            ],
        ]));
    }
}
