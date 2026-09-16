<?php

namespace Tests\Feature\Admin;

use App\Enums\FonteTipo;
use App\Enums\StatusPublicacao;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoOperacao;
use App\Filament\Resources\Planos\Pages\ListPlanos;
use App\Models\Adquirente;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use App\Models\User;
use Database\Seeders\DimensoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do Everton, sessão de 16/09/2026: um jeito fácil de ver, na
 * listagem de Planos, quais já têm taxa publicada e quais ainda não -
 * sem precisar abrir a Tabela do Plano um por um.
 */
class PlanosTableTest extends TestCase
{
    use RefreshDatabase;

    private int $naHora;

    private int $visaMaster;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->seed(DimensoesSeeder::class);

        $this->naHora = PrazoRecebimento::where('codigo', PrazoRecebimento::NA_HORA)->value('id');
        $this->visaMaster = GrupoBandeira::where('codigo', GrupoBandeira::VISA_MASTER)->value('id');
    }

    private function novoPlano(string $nome): Plano
    {
        $adquirente = Adquirente::create(['nome' => "Adquirente {$nome}", 'slug' => "adquirente-{$nome}"]);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => "Marca {$nome}",
            'slug' => "marca-{$nome}",
            'publica_tabela' => true,
            'status' => 'ativa',
        ]);

        return Plano::create([
            'marca_id' => $marca->getKey(),
            'nome' => 'Padrão',
            'slug' => "padrao-{$nome}",
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'status' => 'ativo',
        ]);
    }

    private function novaTaxa(Plano $plano, StatusPublicacao $status, int $parcelas = 1): TaxaDivulgada
    {
        return TaxaDivulgada::create([
            'plano_id' => $plano->getKey(),
            'tipo_operacao' => $parcelas === 1 ? TipoOperacao::CreditoAvista : TipoOperacao::CreditoParcelado,
            'grupo_bandeira_id' => $this->visaMaster,
            'parcelas' => $parcelas,
            'prazo_recebimento_id' => $this->naHora,
            'percentual' => 2.5,
            'valor_fixo' => 0,
            'url_fonte' => 'https://exemplo.com/taxas',
            'fonte_tipo' => FonteTipo::SiteOficial,
            'data_verificacao' => now()->toDateString(),
            'status' => $status,
        ]);
    }

    public function test_plano_sem_taxa_mostra_sem_taxa_cadastrada(): void
    {
        $plano = $this->novoPlano('vazio');

        Livewire::test(ListPlanos::class)
            ->assertOk()
            ->assertTableColumnStateSet('situacao_taxas', 'Sem taxa cadastrada', record: $plano);
    }

    public function test_plano_parcialmente_publicado_mostra_a_contagem(): void
    {
        $plano = $this->novoPlano('parcial');
        $this->novaTaxa($plano, StatusPublicacao::Publicado, parcelas: 1);
        $this->novaTaxa($plano, StatusPublicacao::Rascunho, parcelas: 2);

        Livewire::test(ListPlanos::class)
            ->assertTableColumnStateSet('situacao_taxas', '1/2 publicadas', record: $plano);
    }

    public function test_plano_totalmente_publicado_mostra_a_contagem_cheia(): void
    {
        $plano = $this->novoPlano('completo');
        $this->novaTaxa($plano, StatusPublicacao::Publicado, parcelas: 1);
        $this->novaTaxa($plano, StatusPublicacao::Publicado, parcelas: 2);

        Livewire::test(ListPlanos::class)
            ->assertTableColumnStateSet('situacao_taxas', '2/2 publicadas', record: $plano);
    }

    public function test_filtro_com_taxa_em_rascunho_so_traz_planos_com_pendencia(): void
    {
        $comPendencia = $this->novoPlano('pendente');
        $this->novaTaxa($comPendencia, StatusPublicacao::Rascunho);

        $semPendencia = $this->novoPlano('em-dia');
        $this->novaTaxa($semPendencia, StatusPublicacao::Publicado);

        Livewire::test(ListPlanos::class)
            ->filterTable('com_taxa_em_rascunho')
            ->assertCanSeeTableRecords([$comPendencia])
            ->assertCanNotSeeTableRecords([$semPendencia]);
    }

    public function test_filtro_sem_taxa_cadastrada_so_traz_planos_vazios(): void
    {
        $vazio = $this->novoPlano('sem-taxa');

        $comTaxa = $this->novoPlano('com-taxa');
        $this->novaTaxa($comTaxa, StatusPublicacao::Publicado);

        Livewire::test(ListPlanos::class)
            ->filterTable('sem_taxa_cadastrada')
            ->assertCanSeeTableRecords([$vazio])
            ->assertCanNotSeeTableRecords([$comTaxa]);
    }
}
