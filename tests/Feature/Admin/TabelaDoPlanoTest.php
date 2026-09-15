<?php

namespace Tests\Feature\Admin;

use App\Enums\FonteTipo;
use App\Enums\StatusMarca;
use App\Enums\StatusPublicacao;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoOperacao;
use App\Filament\Resources\TaxaDivulgadas\Pages\TabelaDoPlano;
use App\Models\Adquirente;
use App\Models\Bandeira;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use App\Models\User;
use Database\Seeders\BandeirasSeeder;
use Database\Seeders\DimensoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 17, pedido do Everton: editar taxa por taxa era o maior atrito do
 * dia a dia. Esta tela substitui isso por uma grade única por plano,
 * pré-preenchida, com o nome do plano editável junto.
 */
class TabelaDoPlanoTest extends TestCase
{
    use RefreshDatabase;

    private Plano $plano;

    private int $visaMaster;

    private int $demais;

    private int $naHora;

    private int $d1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->seed(DimensoesSeeder::class);
        $this->seed(BandeirasSeeder::class);

        $adquirente = Adquirente::create(['nome' => 'Adquirente Teste', 'slug' => 'adquirente-teste']);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Teste',
            'slug' => 'marca-teste',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $this->plano = Plano::create([
            'marca_id' => $marca->getKey(),
            'nome' => 'Padrão',
            'slug' => 'padrao',
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'status' => 'ativo',
        ]);

        $this->visaMaster = GrupoBandeira::where('codigo', GrupoBandeira::VISA_MASTER)->value('id');
        $this->demais = GrupoBandeira::where('codigo', GrupoBandeira::DEMAIS)->value('id');
        $this->naHora = PrazoRecebimento::where('codigo', PrazoRecebimento::NA_HORA)->value('id');
        $this->d1 = PrazoRecebimento::where('codigo', PrazoRecebimento::D1)->value('id');

        $marca->bandeiras()->attach([
            Bandeira::where('slug', 'visa')->value('id') => ['grupo_bandeira_id' => $this->visaMaster],
            Bandeira::where('slug', 'elo')->value('id') => ['grupo_bandeira_id' => $this->demais],
        ]);

        TaxaDivulgada::create([
            'plano_id' => $this->plano->getKey(),
            'tipo_operacao' => TipoOperacao::Debito,
            'grupo_bandeira_id' => $this->visaMaster,
            'parcelas' => 1,
            'prazo_recebimento_id' => $this->naHora,
            'percentual' => 1.50,
            'valor_fixo' => 0,
            'url_fonte' => 'https://exemplo.com/taxas',
            'fonte_tipo' => FonteTipo::SiteOficial,
            'data_verificacao' => now()->subDays(10)->toDateString(),
            'status' => StatusPublicacao::Rascunho,
        ]);
    }

    public function test_a_pagina_monta_e_pre_preenche_a_celula_existente(): void
    {
        Livewire::test(TabelaDoPlano::class, ['plano' => $this->plano])
            ->assertOk()
            ->assertSet('data.nome', 'Padrão')
            ->assertSet("data.cells.{$this->naHora}.{$this->visaMaster}.debito", '1.5000');
    }

    /** Só os grupos que a marca de fato usa aparecem - não todos que existem no banco. */
    public function test_so_mostra_os_grupos_de_bandeira_da_marca(): void
    {
        $grupoIds = Livewire::test(TabelaDoPlano::class, ['plano' => $this->plano])
            ->instance()->grupos->pluck('id')->all();

        $this->assertContains($this->visaMaster, $grupoIds);
        $this->assertContains($this->demais, $grupoIds);

        $voucher = GrupoBandeira::where('codigo', GrupoBandeira::VOUCHER)->value('id');
        $this->assertNotContains($voucher, $grupoIds);
    }

    public function test_editar_uma_celula_grava_com_a_fonte_do_bloco_comum(): void
    {
        Livewire::test(TabelaDoPlano::class, ['plano' => $this->plano])
            ->set('data.url_fonte', 'https://exemplo.com/nova-fonte')
            ->set('data.fonte_tipo', FonteTipo::SiteOficial->value)
            ->set('data.data_verificacao', now()->toDateString())
            ->set('data.status', StatusPublicacao::Publicado->value)
            ->set("data.cells.{$this->naHora}.{$this->visaMaster}.parcelas.2", '4.10')
            ->call('salvar');

        $criada = TaxaDivulgada::where([
            'plano_id' => $this->plano->getKey(),
            'tipo_operacao' => TipoOperacao::CreditoParcelado,
            'grupo_bandeira_id' => $this->visaMaster,
            'parcelas' => 2,
            'prazo_recebimento_id' => $this->naHora,
        ])->first();

        $this->assertNotNull($criada, 'A célula nova (2x) devia ter sido criada.');
        $this->assertSame('4.1000', $criada->percentual);
        $this->assertSame('https://exemplo.com/nova-fonte', $criada->url_fonte);
    }

    public function test_celula_que_nao_mudou_nao_e_tocada(): void
    {
        Livewire::test(TabelaDoPlano::class, ['plano' => $this->plano])
            ->set('data.url_fonte', 'https://exemplo.com/nova-fonte-nao-deve-aparecer')
            ->set('data.fonte_tipo', FonteTipo::SiteOficial->value)
            ->set('data.data_verificacao', now()->toDateString())
            ->set('data.status', StatusPublicacao::Publicado->value)
            // Mexe só numa célula nova (débito não existia em D+1); a de
            // na_hora/visa_master/débito, já existente, fica como estava.
            ->set("data.cells.{$this->d1}.{$this->visaMaster}.debito", '2.00')
            ->call('salvar');

        $original = TaxaDivulgada::where([
            'plano_id' => $this->plano->getKey(),
            'tipo_operacao' => TipoOperacao::Debito,
            'grupo_bandeira_id' => $this->visaMaster,
            'parcelas' => 1,
            'prazo_recebimento_id' => $this->naHora,
        ])->first();

        $this->assertSame('1.5000', $original->percentual);
        $this->assertSame('https://exemplo.com/taxas', $original->url_fonte);
        $this->assertSame(StatusPublicacao::Rascunho, $original->status);
    }

    public function test_limpar_uma_celula_que_tinha_valor_apaga_a_taxa(): void
    {
        Livewire::test(TabelaDoPlano::class, ['plano' => $this->plano])
            ->set('data.url_fonte', 'https://exemplo.com/taxas')
            ->set('data.fonte_tipo', FonteTipo::SiteOficial->value)
            ->set('data.data_verificacao', now()->toDateString())
            ->set('data.status', StatusPublicacao::Rascunho->value)
            ->set("data.cells.{$this->naHora}.{$this->visaMaster}.debito", null)
            ->call('salvar');

        $this->assertDatabaseMissing('taxas_divulgadas', [
            'plano_id' => $this->plano->getKey(),
            'tipo_operacao' => TipoOperacao::Debito->value,
            'grupo_bandeira_id' => $this->visaMaster,
            'prazo_recebimento_id' => $this->naHora,
        ]);
    }

    public function test_editar_o_nome_do_plano_junto_da_tabela(): void
    {
        Livewire::test(TabelaDoPlano::class, ['plano' => $this->plano])
            ->set('data.nome', 'Nome Novo Que a Marca Deu')
            ->set('data.url_fonte', 'https://exemplo.com/taxas')
            ->set('data.fonte_tipo', FonteTipo::SiteOficial->value)
            ->set('data.data_verificacao', now()->toDateString())
            ->set('data.status', StatusPublicacao::Rascunho->value)
            ->call('salvar');

        $this->assertSame('Nome Novo Que a Marca Deu', $this->plano->fresh()->nome);
    }

    /** Pedido do Everton: publicar o plano inteiro sem passar pela listagem. */
    public function test_publicar_tudo_muda_o_status_de_todas_as_taxas_do_plano(): void
    {
        // Uma segunda taxa, também em rascunho, pra confirmar que a ação
        // pega todo mundo - não só a primeira linha.
        TaxaDivulgada::create([
            'plano_id' => $this->plano->getKey(),
            'tipo_operacao' => TipoOperacao::CreditoAvista,
            'grupo_bandeira_id' => $this->demais,
            'parcelas' => 1,
            'prazo_recebimento_id' => $this->d1,
            'percentual' => 3.20,
            'valor_fixo' => 0,
            'url_fonte' => 'https://exemplo.com/taxas',
            'fonte_tipo' => FonteTipo::SiteOficial,
            'data_verificacao' => now()->toDateString(),
            'status' => StatusPublicacao::Rascunho,
        ]);

        Livewire::test(TabelaDoPlano::class, ['plano' => $this->plano])
            ->callAction('publicarTudo');

        $this->assertSame(
            0,
            TaxaDivulgada::where('plano_id', $this->plano->getKey())
                ->where('status', StatusPublicacao::Rascunho)
                ->count(),
        );
        $this->assertSame(
            2,
            TaxaDivulgada::where('plano_id', $this->plano->getKey())
                ->where('status', StatusPublicacao::Publicado)
                ->count(),
        );
    }

    /** Pedido do Everton: "e se eu receber a tabela direto da marca?" - sem URL pública. */
    public function test_celula_pode_ser_gravada_so_com_descricao_da_fonte_sem_url(): void
    {
        Livewire::test(TabelaDoPlano::class, ['plano' => $this->plano])
            ->set('data.url_fonte', null)
            ->set('data.fonte_descricao', 'PDF enviado por e-mail pelo gerente de contas em 15/09/2026')
            ->set('data.fonte_tipo', FonteTipo::Atendimento->value)
            ->set('data.data_verificacao', now()->toDateString())
            ->set('data.status', StatusPublicacao::Rascunho->value)
            ->set("data.cells.{$this->naHora}.{$this->demais}.debito", '2.10')
            ->call('salvar')
            ->assertHasNoErrors();

        $criada = TaxaDivulgada::where([
            'plano_id' => $this->plano->getKey(),
            'tipo_operacao' => TipoOperacao::Debito->value,
            'grupo_bandeira_id' => $this->demais,
            'prazo_recebimento_id' => $this->naHora,
        ])->first();

        $this->assertNotNull($criada);
        $this->assertNull($criada->url_fonte);
        $this->assertSame('PDF enviado por e-mail pelo gerente de contas em 15/09/2026', $criada->fonte_descricao);
    }
}
