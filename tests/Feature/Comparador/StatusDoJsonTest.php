<?php

namespace Tests\Feature\Comparador;

use App\Enums\FonteTipo;
use App\Enums\StatusMarca;
use App\Enums\StatusPublicacao;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoOperacao;
use App\Models\Adquirente;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use App\Motor\CatalogoDoComparador;
use App\Support\Saude\StatusDoJson;
use Database\Seeders\DimensoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Pedido do Everton em 17/09/2026, no mesmo dia em que o botão de gerar o
 * JSON nasceu: um alerta ao lado dele avisando se há mudança pendente.
 *
 * A comparação é por CONTEÚDO (regenera o catálogo e compara com o arquivo,
 * ignorando só `gerado_em`), não por `updated_at` de tabela — de propósito,
 * para editar uma taxa em rascunho (o trabalho normal de curadoria das
 * etapas 17 em diante) não acender o alerta à toa.
 */
class StatusDoJsonTest extends TestCase
{
    use RefreshDatabase;

    private string $caminho;

    private ?string $bytesOriginais = null;

    private TaxaDivulgada $taxaPublicada;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caminho = public_path('dados/comparador.json');

        if (File::exists($this->caminho)) {
            $this->bytesOriginais = File::get($this->caminho);
        }

        $this->seed(DimensoesSeeder::class);

        $adquirente = Adquirente::create(['nome' => 'Adquirente Teste', 'slug' => 'adquirente-teste-json']);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Teste JSON',
            'slug' => 'marca-teste-json',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $plano = Plano::create([
            'marca_id' => $marca->getKey(),
            'nome' => 'Padrão',
            'slug' => 'padrao-json',
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'status' => 'ativo',
        ]);

        $visaMaster = GrupoBandeira::where('codigo', GrupoBandeira::VISA_MASTER)->value('id');
        $naHora = PrazoRecebimento::where('codigo', PrazoRecebimento::NA_HORA)->value('id');

        $this->taxaPublicada = TaxaDivulgada::create([
            'plano_id' => $plano->getKey(),
            'tipo_operacao' => TipoOperacao::Debito,
            'grupo_bandeira_id' => $visaMaster,
            'parcelas' => 1,
            'prazo_recebimento_id' => $naHora,
            'percentual' => 1.50,
            'valor_fixo' => 0,
            'url_fonte' => 'https://exemplo.com/taxas',
            'fonte_tipo' => FonteTipo::SiteOficial,
            'data_verificacao' => now()->toDateString(),
            'status' => StatusPublicacao::Publicado,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->bytesOriginais !== null) {
            File::put($this->caminho, $this->bytesOriginais);
        } elseif (File::exists($this->caminho)) {
            File::delete($this->caminho);
        }

        parent::tearDown();
    }

    private function gerarArquivoAgora(): void
    {
        $dados = app(CatalogoDoComparador::class)->montar(incluirRascunhos: false);

        File::ensureDirectoryExists(dirname($this->caminho));
        File::put($this->caminho, json_encode($dados));

        StatusDoJson::esquecer();
    }

    public function test_sem_arquivo_nenhum_esta_desatualizado(): void
    {
        if (File::exists($this->caminho)) {
            File::delete($this->caminho);
        }

        $this->assertTrue(StatusDoJson::desatualizado());
        $this->assertNull(StatusDoJson::geradoEm());
    }

    public function test_logo_depois_de_gerar_nao_esta_desatualizado(): void
    {
        $this->gerarArquivoAgora();

        $this->assertFalse(StatusDoJson::desatualizado());
        $this->assertNotNull(StatusDoJson::geradoEm());
    }

    public function test_editar_a_taxa_publicada_marca_como_desatualizado(): void
    {
        $this->gerarArquivoAgora();
        $this->assertFalse(StatusDoJson::desatualizado());

        $this->taxaPublicada->update(['percentual' => 9.99]);
        StatusDoJson::esquecer();

        $this->assertTrue(StatusDoJson::desatualizado());
    }

    public function test_despublicar_a_taxa_tambem_marca_como_desatualizado(): void
    {
        $this->gerarArquivoAgora();
        $this->assertFalse(StatusDoJson::desatualizado());

        $this->taxaPublicada->update(['status' => StatusPublicacao::Rascunho]);
        StatusDoJson::esquecer();

        $this->assertTrue(StatusDoJson::desatualizado());
    }

    /**
     * O ponto central do pedido: editar rascunho (o trabalho de curadoria
     * do dia a dia, nas marcas que ainda não fecharam) não pode acender o
     * alerta — nada ali está no arquivo publicado de qualquer forma.
     */
    public function test_criar_e_editar_taxa_em_rascunho_nao_marca_como_desatualizado(): void
    {
        $this->gerarArquivoAgora();
        $this->assertFalse(StatusDoJson::desatualizado());

        $rascunho = TaxaDivulgada::create([
            'plano_id' => $this->taxaPublicada->plano_id,
            'tipo_operacao' => TipoOperacao::CreditoAvista,
            'grupo_bandeira_id' => $this->taxaPublicada->grupo_bandeira_id,
            'parcelas' => 1,
            'prazo_recebimento_id' => $this->taxaPublicada->prazo_recebimento_id,
            'percentual' => 3.20,
            'valor_fixo' => 0,
            'url_fonte' => 'https://exemplo.com/taxas',
            'fonte_tipo' => FonteTipo::SiteOficial,
            'data_verificacao' => now()->toDateString(),
            'status' => StatusPublicacao::Rascunho,
        ]);
        StatusDoJson::esquecer();

        $this->assertFalse(StatusDoJson::desatualizado());

        $rascunho->update(['percentual' => 4.44]);
        StatusDoJson::esquecer();

        $this->assertFalse(StatusDoJson::desatualizado());
    }

    public function test_esquecer_nao_precisa_esperar_o_cache_de_um_minuto(): void
    {
        // Sem gerar o arquivo, já começa desatualizado — geramos e
        // conferimos que o esquecer() dentro de gerarArquivoAgora() já
        // reflete o estado novo na mesma requisição, sem esperar TTL.
        $this->assertTrue(StatusDoJson::desatualizado());

        $this->gerarArquivoAgora();

        $this->assertFalse(StatusDoJson::desatualizado());
    }
}
