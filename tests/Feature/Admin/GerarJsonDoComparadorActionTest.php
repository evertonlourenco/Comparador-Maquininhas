<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 18 (achado usando o manual, 17/09/2026): o Everton copiou o comando
 * SSH do manual e o terminal travou esperando o fecha-aspas do comando
 * (comando longo, aspa de fechamento perdida na cópia). Este botão chama o
 * mesmo `comparador:gerar-json` em processo, sem SSH e sem aspa para errar —
 * ver App\Filament\Actions\GerarJsonDoComparadorAction. Fica no Dashboard e
 * em toda tela onde uma taxa é aprovada (TabelaDoPlano, Lançamento em Lote,
 * as duas listagens de Taxas).
 */
class GerarJsonDoComparadorActionTest extends TestCase
{
    use RefreshDatabase;

    private string $caminho;

    private ?string $bytesOriginais = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->caminho = public_path('dados/comparador.json');

        if (File::exists($this->caminho)) {
            $this->bytesOriginais = File::get($this->caminho);
        }
    }

    protected function tearDown(): void
    {
        // O arquivo é gitignored e regenerado a qualquer momento, mas
        // devolver o estado de antes evita que rodar a suíte localmente
        // deixe o comparador.json do dev mexido sem necessidade.
        if ($this->bytesOriginais !== null) {
            File::put($this->caminho, $this->bytesOriginais);
        } elseif (File::exists($this->caminho)) {
            File::delete($this->caminho);
        }

        parent::tearDown();
    }

    public function test_o_botao_existe_no_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Dashboard::class)
            ->assertActionExists('gerarJsonDoComparador');
    }

    public function test_clicar_no_botao_regenera_o_arquivo_e_avisa_o_admin(): void
    {
        $this->actingAs(User::factory()->create());

        File::ensureDirectoryExists(dirname($this->caminho));
        File::put($this->caminho, json_encode(['marcas' => [], 'gerado_em' => 'valor-antigo-de-teste']));

        Livewire::test(Dashboard::class)
            ->callAction('gerarJsonDoComparador')
            ->assertNotified();

        $this->assertTrue(File::exists($this->caminho));

        $dados = json_decode(File::get($this->caminho), true);
        $this->assertArrayHasKey('marcas', $dados);
        $this->assertNotSame(
            'valor-antigo-de-teste',
            $dados['gerado_em'],
            'O comando real grava um timestamp ISO 8601 em "gerado_em" — se ainda for o valor antigo, o arquivo não foi regenerado de verdade.',
        );
    }
}
