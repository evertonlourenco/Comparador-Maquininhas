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
     * Etapa 17 fechou a mensalidade de Ton/PagBank e o preco dos aparelhos da
     * SumUp - mas o PagBank ainda tem um buraco real: o equipamento so esta
     * vinculado ao plano "Super Max", nunca ao "Taxas iniciais", que e quem
     * carrega as 74 taxas. O motor precisa dizer isso, e nao completar com
     * zero.
     */
    public function test_o_que_falta_na_carga_aparece_como_falta_e_nao_como_zero(): void
    {
        $resultado = $this->calcular(10000.0);

        $pagbank = collect($resultado['itens'])
            ->firstWhere('plano.nome', 'Taxas iniciais');

        $this->assertSame(EstadoDoResultado::Incompleto->value, $pagbank['estado']);
        $this->assertContains('equipamento vinculado a este plano', $pagbank['faltando']);
        $this->assertArrayNotHasKey('total_mensal', $pagbank['custos']);
        $this->assertArrayHasKey('total_mensal_parcial', $pagbank['custos']);
    }

    public function test_a_infinitepay_fecha_o_cenario_com_a_carga_atual(): void
    {
        $item = collect($this->calcular(10000.0)['itens'])->firstWhere('marca.nome', 'InfinitePay');

        $this->assertSame(EstadoDoResultado::Calculado->value, $item['estado']);

        // Adesao de R$ 199,00 sem cupom cadastrado, em 12 meses: R$ 16,58.
        $this->assertSame(199.0, $item['adesao']['valor_final']);
        $this->assertSame(16.58, $item['adesao']['por_mes']);
    }

    /**
     * O "Periodo Promocional" do Ton vale 30 dias ou ate R$ 5.000,00
     * processados. Ele entrou na etapa 04 como enquadramento automatico de
     * R$ 2 mil a R$ 5 mil, e assim o motor o ranqueava contra planos
     * permanentes - com percentual de entrada, ele ganhava de todo mundo,
     * inclusive do plano regular do proprio Ton. Vender por permanente uma
     * taxa de 30 dias e o risco de CDC que a regra 6 existe para evitar.
     */
    public function test_a_promocao_de_entrada_nao_disputa_com_preco_permanente(): void
    {
        // R$ 3.000,00 e a faixa em que a promocao do Ton aparecia ranqueada.
        $itens = collect($this->calcular(3000.0)['itens']);

        $promocao = $itens->firstWhere('plano.nome', 'Período Promocional');

        $this->assertNotNull($promocao, 'A promoção não pode sumir: ela existe e o lojista vai cair nela.');
        $this->assertSame(EstadoDoResultado::Promocional->value, $promocao['estado']);

        // O numero existe, mas nao com o nome do numero permanente.
        $this->assertArrayNotHasKey('total_mensal', $promocao['custos']);
        $this->assertStringContainsString('30 dias', $promocao['motivo']);
        $this->assertStringContainsString('R$ 5.000,00 processados', $promocao['motivo']);

        // E ela diz em qual plano o lojista cai quando a promocao acaba.
        $this->assertSame('De R$ 3 mil a R$ 6 mil', $promocao['promocao']['sucessor']['nome']);

        // Nenhum item promocional aparece antes de um permanente.
        $ordens = $itens->map(fn (array $i): int => EstadoDoResultado::from($i['estado'])->ordem());
        $this->assertSame($ordens->sort()->values()->all(), $ordens->values()->all());

        $primeiroPermanente = $itens->search(fn (array $i): bool => $i['estado'] === EstadoDoResultado::Calculado->value);
        $posicaoDaPromocao = $itens->search(fn (array $i): bool => $i['estado'] === EstadoDoResultado::Promocional->value);

        $this->assertLessThan($posicaoDaPromocao, $primeiroPermanente);
    }

    /**
     * A promocao deixou de usar as colunas de faixa de faturamento, que nunca
     * foram dela: os R$ 5 mil sao teto de volume processado.
     */
    public function test_a_promocao_nao_ocupa_as_colunas_de_faixa_de_faturamento(): void
    {
        // Etapa 17: o Mercado Pago tambem ganhou plano promocional, entao
        // "promocional" deixou de ser exclusividade do Ton - escopar por
        // marca em vez de sole() sobre a tabela inteira.
        $promocao = Plano::promocionais()
            ->whereHas('marca', fn ($q) => $q->where('slug', 'ton'))
            ->sole();

        $this->assertNull($promocao->faturamento_min);
        $this->assertNull($promocao->faturamento_max);
        $this->assertSame(30, $promocao->promocional_dias);
        $this->assertSame('5000.00', $promocao->promocional_valor_processado);

        // E some do scope de enquadramento automatico, que so lista permanentes.
        $this->assertNotContains(
            $promocao->getKey(),
            Plano::query()->paraFaturamento(3000.0)->pluck('id')->all(),
        );
    }

    /**
     * A InfinitePay parcela a adesao em 12x sem juros - dado que ja estava na
     * observacao da carga da etapa 04 e virou campo. O motor precisa separar
     * essa parcela real da amortizacao que ele proprio faz para comparar.
     */
    public function test_a_parcela_da_marca_nao_se_confunde_com_a_amortizacao_do_motor(): void
    {
        $item = collect($this->calcular(10000.0)['itens'])->firstWhere('marca.nome', 'InfinitePay');

        $this->assertSame(12, $item['adesao']['parcelas_oferecidas']);
        $this->assertSame('12x de R$ 16,58', $item['formatado']['adesao']['parcela_da_marca']);
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
