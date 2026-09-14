<?php

namespace Tests\Feature\Admin;

use App\Enums\StatusMarca;
use App\Enums\StatusRevisao;
use App\Filament\Resources\DeteccoesDeMudanca\Pages\EditDeteccaoDeMudanca;
use App\Filament\Resources\DeteccoesDeMudanca\Pages\ListDeteccoesDeMudanca;
use App\Filament\Widgets\PainelInicial;
use App\Models\Adquirente;
use App\Models\DeteccaoDeMudanca;
use App\Models\Marca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 13: a fila de revisão do monitor de mudanças no painel — só chega
 * aqui por POST /api/monitor/deteccoes (ver MonitorApiTest), nunca por
 * create no Filament (DeteccaoDeMudancaResource::canCreate() é false).
 */
class RecursoDeDeteccoesDeMudancaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function criarMarca(): Marca
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Teste', 'slug' => 'adquirente-teste-'.uniqid()]);

        return Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Teste '.uniqid(),
            'slug' => 'marca-teste-'.uniqid(),
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);
    }

    public function test_paginas_do_recurso_renderizam(): void
    {
        $marca = $this->criarMarca();

        $deteccao = DeteccaoDeMudanca::create([
            'marca_id' => $marca->id,
            'fonte_id' => 'marca-teste-tabela-taxas',
            'categoria' => 'tabela_taxas',
            'tipo' => 'mudanca',
            'url' => 'https://exemplo.com/taxas',
            'resumo' => 'A taxa de débito subiu.',
            'trecho_alterado' => "-1,20%\n+1,35%",
            'status' => StatusRevisao::Pendente,
            'detectado_em' => now(),
        ]);

        Livewire::test(ListDeteccoesDeMudanca::class)->assertOk();
        Livewire::test(EditDeteccaoDeMudanca::class, ['record' => $deteccao->getKey()])->assertOk();
    }

    public function test_marcar_deteccao_como_revisada_salva_o_status(): void
    {
        $deteccao = DeteccaoDeMudanca::create([
            'fonte_id' => 'stone-contrato-credenciamento',
            'categoria' => 'contrato_credenciamento',
            'tipo' => 'falha',
            'url' => 'https://exemplo.com/contrato.pdf',
            'mensagem_erro' => 'HTTP 403 — provável bloqueio de WAF.',
            'status' => StatusRevisao::Pendente,
            'detectado_em' => now(),
        ]);

        Livewire::test(EditDeteccaoDeMudanca::class, ['record' => $deteccao->getKey()])
            ->fillForm(['status' => StatusRevisao::Revisado->value, 'observacao_admin' => 'Fonte movida para o Mac.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(StatusRevisao::Revisado, $deteccao->fresh()->status);
    }

    public function test_painel_inicial_conta_deteccoes_pendentes(): void
    {
        DeteccaoDeMudanca::create([
            'fonte_id' => 'x',
            'categoria' => 'tabela_taxas',
            'tipo' => 'mudanca',
            'url' => 'https://exemplo.com',
            'resumo' => 'Mudou algo.',
            'status' => StatusRevisao::Pendente,
            'detectado_em' => now(),
        ]);

        Livewire::test(PainelInicial::class)->assertSee('Detecções do monitor pendentes');
    }

    public function test_nao_permite_criar_pelo_painel(): void
    {
        $this->assertFalse(\App\Filament\Resources\DeteccoesDeMudanca\DeteccaoDeMudancaResource::canCreate());
    }
}
