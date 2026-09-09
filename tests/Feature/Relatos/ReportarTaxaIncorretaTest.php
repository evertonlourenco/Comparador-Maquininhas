<?php

namespace Tests\Feature\Relatos;

use App\Enums\StatusMarca;
use App\Models\Adquirente;
use App\Models\Marca;
use App\Models\RelatoTaxaIncorreta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Etapa 10: o botão "reportar taxa errada" reaproveitável, em qualquer
 * <x-tabela-taxas>. Ver App\Http\Controllers\RelatoTaxaIncorretoController.
 */
class ReportarTaxaIncorretaTest extends TestCase
{
    use RefreshDatabase;

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

    private function dadosValidos(Marca $marca, array $sobrescreve = []): array
    {
        return array_merge([
            'marca' => $marca->slug,
            'contexto' => 'Plano Padrão — Débito',
            'mensagem' => 'A taxa de débito está diferente do que vi no site oficial.',
            'carregado_em' => now()->subSeconds(10)->timestamp,
        ], $sobrescreve);
    }

    public function test_envio_via_json_grava_e_responde_ok(): void
    {
        $marca = $this->criarMarca();

        $this->postJson('/eventos/taxa-incorreta', $this->dadosValidos($marca))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('relatos_taxa_incorreta', [
            'marca_id' => $marca->id,
            'contexto' => 'Plano Padrão — Débito',
            'status' => 'pendente',
        ]);
    }

    public function test_envio_sem_javascript_grava_e_redireciona(): void
    {
        $marca = $this->criarMarca();

        $this->post('/eventos/taxa-incorreta', $this->dadosValidos($marca))
            ->assertRedirect();

        $this->assertDatabaseCount('relatos_taxa_incorreta', 1);
    }

    public function test_marca_inexistente_responde_sucesso_sem_gravar(): void
    {
        $this->postJson('/eventos/taxa-incorreta', $this->dadosValidos((new Marca(['slug' => 'nao-existe']))))
            ->assertOk();

        $this->assertDatabaseCount('relatos_taxa_incorreta', 0);
    }

    public function test_honeypot_preenchido_nao_grava(): void
    {
        $marca = $this->criarMarca();

        $this->postJson('/eventos/taxa-incorreta', $this->dadosValidos($marca, [
            'confirmar_contato' => 'sou um robô',
        ]))->assertOk();

        $this->assertDatabaseCount('relatos_taxa_incorreta', 0);
    }

    public function test_envio_rapido_demais_nao_grava(): void
    {
        $marca = $this->criarMarca();

        $this->postJson('/eventos/taxa-incorreta', $this->dadosValidos($marca, [
            'carregado_em' => now()->timestamp,
        ]))->assertOk();

        $this->assertDatabaseCount('relatos_taxa_incorreta', 0);
    }

    public function test_mensagem_ausente_e_rejeitada(): void
    {
        $marca = $this->criarMarca();

        $this->postJson('/eventos/taxa-incorreta', $this->dadosValidos($marca, ['mensagem' => '']))
            ->assertStatus(422);

        $this->assertDatabaseCount('relatos_taxa_incorreta', 0);
    }
}
