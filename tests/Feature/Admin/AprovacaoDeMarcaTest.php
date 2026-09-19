<?php

namespace Tests\Feature\Admin;

use App\Enums\FonteTipo;
use App\Enums\StatusItem;
use App\Enums\StatusMarca;
use App\Enums\StatusPublicacao;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoOperacao;
use App\Filament\Resources\Marcas\Pages\ListMarcas;
use App\Models\Adquirente;
use App\Models\Equipamento;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use App\Models\User;
use App\Motor\CatalogoDoComparador;
use App\Support\Saude\CompletudeDaMarca;
use Database\Seeders\DimensoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A trava da marca (etapa 19, 17/09/2026): nasceu de um erro real — a etapa
 * 17 foi dada como "completa" para marcas com mensalidade nula, e isso só
 * foi notado depois de pronto para o lançamento. Daqui pra frente, nenhuma
 * marca com dado publicado entra no JSON sem "aprovada_em" preenchido E sem
 * fechar conta nas quatro formas de pagamento — ver
 * App\Support\Saude\CompletudeDaMarca e
 * App\Motor\CatalogoDoComparador::apenasAprovadasECompletas().
 */
class AprovacaoDeMarcaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DimensoesSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    /**
     * Revisão do mesmo dia (17/09/2026): a trava deixou de abrir exceção
     * para "sem dado publicado" — o Everton pediu o mesmo critério pra toda
     * marca, sem exceção. Uma marca vazia nunca fecha `completude`
     * (`Nenhum plano cadastrado.`), então nunca é aprovável, e por isso
     * nunca aparece — igual a qualquer outra marca incompleta.
     */
    public function test_marca_sem_taxa_nenhuma_tambem_fica_invisivel(): void
    {
        $marca = $this->criarMarcaVazia();

        $this->assertTrue(
            CompletudeDaMarca::avaliar($marca)['pendencias'] !== [],
            'Marca sem plano deveria ter pendência (nenhum plano cadastrado).',
        );

        $catalogo = app(CatalogoDoComparador::class)->montar(incluirRascunhos: false);
        $slugsNoJson = collect($catalogo['marcas'])->pluck('slug')->all();

        $this->assertNotContains(
            $marca->slug,
            $slugsNoJson,
            'Sem exceção: marca sem plano nenhum também precisa de aprovação para aparecer, e nunca fecha completude sem plano.',
        );
    }

    public function test_marca_com_taxa_incompleta_nao_aparece_no_json_mesmo_aprovada(): void
    {
        $marca = $this->criarMarcaComUmaTaxaSo();
        $marca->update(['aprovada_em' => now()]);

        $completude = CompletudeDaMarca::avaliar($marca);
        $this->assertFalse($completude['completa']);
        $this->assertNotEmpty($completude['pendencias']);

        // Achado do Everton em 18/09/2026: um plano com taxa mas sem
        // equipamento gerava DUAS pendências pro mesmo fato (a checagem
        // própria desta classe e o "faltando" que o motor já devolve via
        // custoDoAparelho()). Confere que sobrou só uma.
        $mencoesAEquipamento = collect($completude['pendencias'])
            ->filter(fn (string $p): bool => str_contains($p, 'equipamento'))
            ->count();
        $this->assertSame(1, $mencoesAEquipamento, 'A pendência de equipamento não pode aparecer duplicada.');

        $catalogo = app(CatalogoDoComparador::class)->montar(incluirRascunhos: false);
        $slugsNoJson = collect($catalogo['marcas'])->pluck('slug')->all();

        $this->assertNotContains(
            $marca->slug,
            $slugsNoJson,
            'Marca com taxa publicada mas incompleta (sem mensalidade, sem Pix...) nunca pode aparecer no JSON, aprovada ou não.',
        );
    }

    public function test_marca_completa_mas_nao_aprovada_nao_aparece_no_json(): void
    {
        $marca = $this->criarMarcaCompleta();
        // Sem aprovada_em de proposito.

        $this->assertTrue(CompletudeDaMarca::avaliar($marca)['completa']);

        $catalogo = app(CatalogoDoComparador::class)->montar(incluirRascunhos: false);
        $slugsNoJson = collect($catalogo['marcas'])->pluck('slug')->all();

        $this->assertNotContains($marca->slug, $slugsNoJson, 'Completa não é suficiente — precisa do clique em "Aprovar marca".');
    }

    public function test_marca_sem_nota_do_reclame_aqui_nao_fecha_completude_nem_aparece_no_json(): void
    {
        $marca = $this->criarMarcaCompleta();
        $marca->update(['aprovada_em' => now(), 'reclame_aqui_nota' => null]);

        $completude = CompletudeDaMarca::avaliar($marca);

        $this->assertFalse($completude['completa']);
        $this->assertContains('Nota do Reclame Aqui não preenchida.', $completude['pendencias']);

        $slugs = collect(app(CatalogoDoComparador::class)->montar(incluirRascunhos: false)['marcas'])->pluck('slug')->all();
        $this->assertNotContains($marca->slug, $slugs);
    }

    public function test_marca_sem_perfil_ou_sem_nota_no_reclame_aqui_fecha_completude_com_a_situacao_informada(): void
    {
        $marca = $this->criarMarcaCompleta();

        foreach (['sem_perfil', 'sem_nota'] as $situacao) {
            $marca->update(['reclame_aqui_situacao' => $situacao, 'reclame_aqui_nota' => null]);

            $this->assertTrue(CompletudeDaMarca::avaliar($marca->fresh())['completa'], $situacao);
        }
    }

    public function test_nota_sem_data_de_consulta_tambem_e_pendencia(): void
    {
        $marca = $this->criarMarcaCompleta();
        $marca->update(['reclame_aqui_consultado_em' => null]);

        $this->assertContains(
            'Data da consulta da nota do Reclame Aqui não preenchida.',
            CompletudeDaMarca::avaliar($marca)['pendencias'],
        );
    }

    public function test_marca_completa_e_aprovada_aparece_no_json(): void
    {
        $marca = $this->criarMarcaCompleta();
        $marca->update(['aprovada_em' => now()]);

        $catalogo = app(CatalogoDoComparador::class)->montar(incluirRascunhos: false);
        $slugsNoJson = collect($catalogo['marcas'])->pluck('slug')->all();

        $this->assertContains($marca->slug, $slugsNoJson);
    }

    public function test_botao_aprovar_marca_fica_desabilitado_se_incompleta(): void
    {
        $marca = $this->criarMarcaComUmaTaxaSo();

        Livewire::test(ListMarcas::class)
            ->assertTableActionExists('aprovarMarca')
            ->assertTableActionDisabled('aprovarMarca', $marca);
    }

    public function test_clicar_em_aprovar_marca_aprova_quando_completa(): void
    {
        $marca = $this->criarMarcaCompleta();

        Livewire::test(ListMarcas::class)
            ->assertTableActionEnabled('aprovarMarca', $marca)
            ->callTableAction('aprovarMarca', $marca);

        $this->assertNotNull($marca->fresh()->aprovada_em);
    }

    public function test_clicar_de_novo_numa_marca_aprovada_revoga(): void
    {
        $marca = $this->criarMarcaCompleta();
        $marca->update(['aprovada_em' => now()->subDay()]);

        Livewire::test(ListMarcas::class)
            ->callTableAction('aprovarMarca', $marca);

        $this->assertNull($marca->fresh()->aprovada_em);
    }

    private function criarMarcaVazia(): Marca
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Vazia', 'slug' => 'adquirente-vazia-'.uniqid()]);

        return Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Vazia',
            'slug' => 'marca-vazia-'.uniqid(),
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);
    }

    private function criarMarcaComUmaTaxaSo(): Marca
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Incompleta', 'slug' => 'adquirente-incompleta-'.uniqid()]);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Incompleta',
            'slug' => 'marca-incompleta-'.uniqid(),
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $plano = Plano::create([
            'marca_id' => $marca->getKey(),
            'nome' => 'Padrão',
            'slug' => 'padrao-incompleta-'.uniqid(),
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            // mensalidade de proposito nula — a pendencia central deste teste.
            'status' => 'ativo',
        ]);

        TaxaDivulgada::create([
            'plano_id' => $plano->getKey(),
            'tipo_operacao' => TipoOperacao::Debito,
            'grupo_bandeira_id' => GrupoBandeira::where('codigo', GrupoBandeira::VISA_MASTER)->value('id'),
            'parcelas' => 1,
            'prazo_recebimento_id' => PrazoRecebimento::where('codigo', PrazoRecebimento::NA_HORA)->value('id'),
            'percentual' => 1.50,
            'valor_fixo' => 0,
            'url_fonte' => 'https://exemplo.com/taxas',
            'fonte_tipo' => FonteTipo::SiteOficial,
            'data_verificacao' => now()->toDateString(),
            'status' => StatusPublicacao::Publicado,
        ]);

        return $marca;
    }

    private function criarMarcaCompleta(): Marca
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Completa', 'slug' => 'adquirente-completa-'.uniqid()]);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Completa',
            'slug' => 'marca-completa-'.uniqid(),
            'logo_path' => 'marcas/logos/teste.webp',
            'reclame_aqui_nota' => 8.4,
            'reclame_aqui_consultado_em' => now()->toDateString(),
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $plano = Plano::create([
            'marca_id' => $marca->getKey(),
            'nome' => 'Padrão',
            'slug' => 'padrao-completa-'.uniqid(),
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'mensalidade' => 0,
            'tarifa_pix_recebimento' => 0,
            'status' => 'ativo',
        ]);

        $equipamento = Equipamento::create([
            'marca_id' => $marca->getKey(),
            'nome' => 'Maquininha Completa',
            'slug' => 'maquininha-completa-'.uniqid(),
            'tipo' => 'smart',
            'imagem_path' => 'equipamentos/teste.webp',
            'status' => StatusItem::Ativo,
        ]);

        $plano->equipamentos()->attach($equipamento->getKey(), [
            'preco_adesao' => 100.00,
            'parcelas_adesao' => 12,
            'status' => StatusItem::Ativo->value,
        ]);

        $visaMaster = GrupoBandeira::where('codigo', GrupoBandeira::VISA_MASTER)->value('id');
        $naHora = PrazoRecebimento::where('codigo', PrazoRecebimento::NA_HORA)->value('id');

        foreach ([
            [TipoOperacao::Debito, 'visa_master', $visaMaster, 1, 1.50],
            [TipoOperacao::CreditoAvista, 'visa_master', $visaMaster, 1, 1.50],
            [TipoOperacao::CreditoParcelado, 'visa_master', $visaMaster, 3, 3.90],
        ] as [$tipo, , $grupoId, $parcelas, $percentual]) {
            TaxaDivulgada::create([
                'plano_id' => $plano->getKey(),
                'tipo_operacao' => $tipo,
                'grupo_bandeira_id' => $grupoId,
                'parcelas' => $parcelas,
                'prazo_recebimento_id' => $naHora,
                'percentual' => $percentual,
                'valor_fixo' => 0,
                'url_fonte' => 'https://exemplo.com/taxas',
                'fonte_tipo' => FonteTipo::SiteOficial,
                'data_verificacao' => now()->toDateString(),
                'status' => StatusPublicacao::Publicado,
            ]);
        }

        TaxaDivulgada::create([
            'plano_id' => $plano->getKey(),
            'tipo_operacao' => TipoOperacao::Pix,
            'grupo_bandeira_id' => GrupoBandeira::where('codigo', GrupoBandeira::PIX)->value('id'),
            'parcelas' => 1,
            'prazo_recebimento_id' => $naHora,
            'percentual' => 0.49,
            'valor_fixo' => 0,
            'url_fonte' => 'https://exemplo.com/taxas',
            'fonte_tipo' => FonteTipo::SiteOficial,
            'data_verificacao' => now()->toDateString(),
            'status' => StatusPublicacao::Publicado,
        ]);

        return $marca;
    }
}
