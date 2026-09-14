<?php

namespace Tests\Feature\Api;

use App\Enums\StatusItem;
use App\Enums\StatusMarca;
use App\Models\Adquirente;
use App\Models\Cupom;
use App\Models\Marca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Etapa 13: /api/monitor/* — a única porta de entrada do repositório Node
 * separado do monitor de mudanças. Ver App\Http\Controllers\Api\MonitorController
 * e App\Http\Middleware\AutenticaMonitor.
 */
class MonitorApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'token-de-teste-do-monitor';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.monitor.token' => self::TOKEN]);
    }

    private function criarMarca(): Marca
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Teste', 'slug' => 'adquirente-teste-'.uniqid()]);

        return Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Teste',
            'slug' => 'marca-teste-'.uniqid(),
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);
    }

    public function test_sem_token_e_rejeitado(): void
    {
        $this->postJson('/api/monitor/deteccoes', [])->assertStatus(401);
        $this->getJson('/api/monitor/resumo-semanal')->assertStatus(401);
    }

    public function test_token_errado_e_rejeitado(): void
    {
        $this->withToken('token-errado')
            ->getJson('/api/monitor/resumo-semanal')
            ->assertStatus(401);
    }

    public function test_registra_mudanca_e_resolve_link_da_marca(): void
    {
        $marca = $this->criarMarca();

        $resposta = $this->withToken(self::TOKEN)->postJson('/api/monitor/deteccoes', [
            'fonte_id' => 'marca-teste-tabela-taxas',
            'marca_slug' => $marca->slug,
            'categoria' => 'tabela_taxas',
            'tipo' => 'mudanca',
            'url' => 'https://exemplo.com/taxas',
            'resumo' => 'A taxa de débito subiu de 1,20% para 1,35%.',
            'trecho_alterado' => '-Débito: 1,20%\n+Débito: 1,35%',
            'hash_anterior' => str_repeat('a', 64),
            'hash_novo' => str_repeat('b', 64),
        ]);

        $resposta->assertCreated()->assertJsonStructure(['id', 'link_admin']);
        $this->assertStringContainsString((string) $marca->id, $resposta->json('link_admin'));

        $this->assertDatabaseHas('deteccoes_de_mudanca', [
            'marca_id' => $marca->id,
            'fonte_id' => 'marca-teste-tabela-taxas',
            'tipo' => 'mudanca',
            'status' => 'pendente',
        ]);
    }

    public function test_registra_falha_sem_marca_reconhecida(): void
    {
        $resposta = $this->withToken(self::TOKEN)->postJson('/api/monitor/deteccoes', [
            'fonte_id' => 'stone-contrato-credenciamento',
            'marca_slug' => 'nao-existe',
            'categoria' => 'contrato_credenciamento',
            'tipo' => 'falha',
            'url' => 'https://exemplo.com/contrato.pdf',
            'mensagem_erro' => 'HTTP 403 — provável bloqueio de WAF.',
        ]);

        $resposta->assertCreated()->assertJson(['link_admin' => null]);

        $this->assertDatabaseHas('deteccoes_de_mudanca', [
            'fonte_id' => 'stone-contrato-credenciamento',
            'marca_id' => null,
            'tipo' => 'falha',
            'mensagem_erro' => 'HTTP 403 — provável bloqueio de WAF.',
        ]);
    }

    public function test_mudanca_sem_resumo_e_rejeitada(): void
    {
        $this->withToken(self::TOKEN)->postJson('/api/monitor/deteccoes', [
            'fonte_id' => 'x',
            'categoria' => 'tabela_taxas',
            'tipo' => 'mudanca',
            'url' => 'https://exemplo.com',
        ])->assertStatus(422);
    }

    public function test_resumo_semanal_traz_os_dois_numeros(): void
    {
        $marca = $this->criarMarca();

        Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'TESTE10',
            'tipo_desconto' => 'percentual',
            'incide_sobre' => 'adesao',
            'valor' => 10,
            'valido_de' => now()->subDay(),
            'valido_ate' => now()->addDays(3),
            'link_afiliado' => 'https://exemplo.com/afiliado',
            'status' => StatusItem::Ativo,
        ]);

        $resposta = $this->withToken(self::TOKEN)->getJson('/api/monitor/resumo-semanal');

        $resposta->assertOk()->assertJsonStructure(['taxas_sem_verificacao_30_dias', 'cupons_vencendo_7_dias']);
        $this->assertCount(1, $resposta->json('cupons_vencendo_7_dias'));
        $this->assertSame('TESTE10', $resposta->json('cupons_vencendo_7_dias.0.codigo'));
    }
}
