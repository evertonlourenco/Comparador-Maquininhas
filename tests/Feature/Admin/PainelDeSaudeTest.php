<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\BancoWidget;
use App\Filament\Widgets\CliquesCupomChart;
use App\Filament\Widgets\CliquesCupomPorCupomTable;
use App\Filament\Widgets\CliquesCupomPorMarcaTable;
use App\Filament\Widgets\FrescorWidget;
use App\Filament\Widgets\OperacaoWidget;
use App\Filament\Widgets\PainelInicial;
use App\Filament\Widgets\TrafegoWidget;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 16: cada widget do painel de saúde é lazy por padrão no Filament
 * (App\Filament\Widgets\Widget::isLazy() = true) — o dashboard inteiro só
 * mostra placeholders de carregamento no primeiro render, e o conteúdo real
 * vem de um segundo request que o navegador dispara sozinho (wire:init),
 * nunca disparado dentro de um `Livewire::test(Dashboard::class)`. Por isso
 * o smoke test do dashboard só confere que ele monta (sem exceção na
 * descoberta/registro dos widgets), e o conteúdo de cada cartão é testado
 * testando o widget diretamente como componente raiz.
 */
class PainelDeSaudeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Sem isto, App\Support\Saude\StatusDeOperacao::certificadoSsl()
        // tentaria uma conexão TLS de verdade contra o host de APP_URL — em
        // CI isso falha rápido (sem DNS), mas numa máquina com Herd rodando
        // o teste passaria a depender do proxy TLS local estar de pé. O
        // caminho "sem dado" é o único que este teste quer garantir.
        config(['saude.dominio_ssl' => null]);

        $this->actingAs(User::factory()->create());
    }

    public function test_o_dashboard_monta_com_todos_os_widgets_registrados(): void
    {
        Livewire::test(Dashboard::class)->assertOk();
    }

    public function test_painel_inicial_mostra_o_alerta_de_link_quebrado(): void
    {
        Livewire::test(PainelInicial::class)
            ->assertOk()
            ->assertSee('Links de afiliado quebrados');
    }

    public function test_operacao_mostra_sem_dado_quando_nada_esta_configurado(): void
    {
        Livewire::test(OperacaoWidget::class)
            ->assertOk()
            ->assertSee('Backup diário')
            ->assertSee('Certificado SSL')
            ->assertSee('Sem dado');
    }

    public function test_frescor_mostra_as_faixas_de_idade(): void
    {
        Livewire::test(FrescorWidget::class)
            ->assertOk()
            ->assertSee('31 a 45 dias')
            ->assertSee('Mais de 90 dias');
    }

    public function test_banco_mostra_tamanho_por_tabela_e_contagem_por_entidade(): void
    {
        Livewire::test(BancoWidget::class)
            ->assertOk()
            ->assertSee('Tamanho por tabela')
            ->assertSee('Contagem por entidade')
            ->assertSee('Marcas');
    }

    public function test_trafego_mostra_aviso_honesto_sem_token_da_cloudflare(): void
    {
        Livewire::test(TrafegoWidget::class)
            ->assertOk()
            ->assertSee('Sem dado')
            ->assertSee('CLOUDFLARE_API_TOKEN');
    }

    public function test_cliques_por_dia_monta_sem_eventos(): void
    {
        Livewire::test(CliquesCupomChart::class)->assertOk();
    }

    public function test_cliques_por_marca_monta_sem_eventos(): void
    {
        Livewire::test(CliquesCupomPorMarcaTable::class)->assertOk();
    }

    public function test_cliques_por_cupom_monta_sem_eventos(): void
    {
        Livewire::test(CliquesCupomPorCupomTable::class)->assertOk();
    }
}
