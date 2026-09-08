<?php

namespace Tests\Feature\Dominio;

use App\Enums\TipoOperacao;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use Database\Seeders\DatabaseSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * As duas decisoes de dimensao que a etapa 04 deixou em aberto e a etapa 05
 * fechou: onde mora a taxa de Pix (decisao 1) e quem declara que o percentual
 * ja embute antecipacao (decisao 3).
 */
class PixEAntecipacaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_o_grupo_do_pix_existe_e_e_curado(): void
    {
        $pix = GrupoBandeira::where('codigo', GrupoBandeira::PIX)->sole();

        $this->assertTrue($pix->ehDePix());
        $this->assertTrue($pix->estaReservado(), 'Renomear ou apagar o grupo pix quebraria as guardas do model.');
    }

    public function test_o_grupo_do_pix_nunca_agrupa_bandeira(): void
    {
        $pix = GrupoBandeira::where('codigo', GrupoBandeira::PIX)->sole();

        $this->assertSame(
            0,
            DB::table('bandeira_marca')->where('grupo_bandeira_id', $pix->getKey())->count(),
            'O grupo pix e tecnico: nenhuma bandeira cai nele.',
        );

        $this->assertFalse(
            GrupoBandeira::query()->deCartao()->where('codigo', GrupoBandeira::PIX)->exists(),
            'O escopo deCartao e o que impede o grupo pix de aparecer no cadastro de bandeiras.',
        );
    }

    public function test_taxa_de_pix_fora_do_grupo_do_pix_e_recusada(): void
    {
        $this->expectException(DomainException::class);

        TaxaDivulgada::create([
            ...$this->chave(GrupoBandeira::VISA_MASTER),
            'tipo_operacao' => TipoOperacao::Pix,
        ]);
    }

    public function test_taxa_de_cartao_no_grupo_do_pix_e_recusada(): void
    {
        $this->expectException(DomainException::class);

        TaxaDivulgada::create([
            ...$this->chave(GrupoBandeira::PIX),
            'tipo_operacao' => TipoOperacao::Debito,
        ]);
    }

    public function test_a_carga_do_pix_entrou_no_grupo_certo(): void
    {
        $doPix = TaxaDivulgada::doTipo(TipoOperacao::Pix)->get();

        $this->assertGreaterThan(0, $doPix->count(), 'A etapa 05 destravou a carga de Pix.');

        $grupoDoPix = GrupoBandeira::where('codigo', GrupoBandeira::PIX)->value('id');
        $naHora = PrazoRecebimento::where('codigo', PrazoRecebimento::NA_HORA)->value('id');

        foreach ($doPix as $taxa) {
            $this->assertSame($grupoDoPix, $taxa->grupo_bandeira_id);
            $this->assertSame($naHora, $taxa->prazo_recebimento_id);
            $this->assertSame(1, $taxa->parcelas, 'Pix nao parcela.');
        }

        // InfinitePay publica Pix a 0% nas quatro faixas de faturamento.
        $infinitepay = Marca::where('slug', 'infinitepay')->sole();

        $this->assertSame(4, $doPix->where('marca_id', $infinitepay->getKey())->count());
        $this->assertSame(
            ['0.0000'],
            $doPix->where('marca_id', $infinitepay->getKey())->pluck('percentual')->unique()->values()->all(),
        );
    }

    public function test_so_os_prazos_curtos_embutem_antecipacao(): void
    {
        $embute = [
            PrazoRecebimento::NA_HORA => true,
            PrazoRecebimento::D1 => true,
            PrazoRecebimento::D14 => true,
            PrazoRecebimento::D30 => false,
            PrazoRecebimento::PARCELA_A_PARCELA => false,
        ];

        foreach ($embute as $codigo => $esperado) {
            $prazo = PrazoRecebimento::where('codigo', $codigo)->sole();

            $this->assertSame($esperado, $prazo->embuteAntecipacao(), "Prazo {$codigo}.");
        }
    }

    public function test_meses_de_antecipacao_avulsa_por_prazo(): void
    {
        $d30 = PrazoRecebimento::where('codigo', PrazoRecebimento::D30)->sole();
        $parcelaAParcela = PrazoRecebimento::where('codigo', PrazoRecebimento::PARCELA_A_PARCELA)->sole();

        // 30 dias / 30 = 1 mes, que e a unidade de taxa_antecipacao_mensal.
        $this->assertSame(1.0, $d30->mesesDeAntecipacao(1));

        // Parcela a parcela: a parcela i cai no mes i, entao a venda inteira
        // custa (n + 1) / 2 meses equivalentes. 1x da 1; 12x da 6,5.
        $this->assertSame(1.0, $parcelaAParcela->mesesDeAntecipacao(1));
        $this->assertSame(6.5, $parcelaAParcela->mesesDeAntecipacao(12));
    }

    /** Chave minima e valida de uma taxa, para os testes de guarda. */
    private function chave(string $grupo): array
    {
        $plano = Marca::where('slug', 'infinitepay')->sole()->planos()->firstOrFail();

        return [
            'plano_id' => $plano->getKey(),
            'grupo_bandeira_id' => GrupoBandeira::where('codigo', $grupo)->value('id'),
            'prazo_recebimento_id' => PrazoRecebimento::where('codigo', PrazoRecebimento::D14)->value('id'),
            'parcelas' => 1,
            'percentual' => 1.0,
            'valor_fixo' => 0,
            'url_fonte' => 'https://exemplo.test/taxas',
            'fonte_tipo' => 'site_oficial',
            'data_verificacao' => '2026-09-08',
        ];
    }
}
