<?php

namespace Tests\Feature\Admin;

use App\Enums\StatusMarca;
use App\Enums\StatusRevisao;
use App\Filament\Resources\PropostasRecebidas\Pages\EditPropostaRecebida;
use App\Filament\Resources\PropostasRecebidas\Pages\ListPropostasRecebidas;
use App\Filament\Resources\RelatosTaxaIncorreta\Pages\EditRelatoTaxaIncorreta;
use App\Filament\Resources\RelatosTaxaIncorreta\Pages\ListRelatosTaxaIncorreta;
use App\Filament\Widgets\PainelInicial;
use App\Models\Adquirente;
use App\Models\Marca;
use App\Models\PropostaRecebida;
use App\Models\RelatoTaxaIncorreta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 10: as duas filas de revisão humana no painel — regra 10 na forma
 * mais forte, e o toggle novo aceita_relatos em MarcaForm.
 */
class RecursosDeRelatosTest extends TestCase
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
            'publica_tabela' => false,
            'aceita_relatos' => true,
            'status' => StatusMarca::Ativa,
        ]);
    }

    public function test_paginas_dos_dois_recursos_novos_renderizam(): void
    {
        $marca = $this->criarMarca();

        $proposta = PropostaRecebida::create([
            'marca_id' => $marca->id,
            'taxas_relatadas' => [['tipo_operacao' => 'debito', 'parcelas' => 1, 'percentual' => 1.99]],
            'data_proposta' => now()->subDay(),
            'estado' => 'SP',
            'segmento' => 'padaria',
            'consentimento_uso_agregado' => true,
            'status' => StatusRevisao::Pendente,
        ]);

        $relato = RelatoTaxaIncorreta::create([
            'marca_id' => $marca->id,
            'mensagem' => 'A taxa parece errada.',
            'status' => StatusRevisao::Pendente,
        ]);

        Livewire::test(ListPropostasRecebidas::class)->assertOk();
        Livewire::test(EditPropostaRecebida::class, ['record' => $proposta->getKey()])->assertOk();
        Livewire::test(ListRelatosTaxaIncorreta::class)->assertOk();
        Livewire::test(EditRelatoTaxaIncorreta::class, ['record' => $relato->getKey()])->assertOk();
        Livewire::test(PainelInicial::class)->assertOk();
    }

    public function test_marcar_proposta_como_revisada_salva_o_status(): void
    {
        $marca = $this->criarMarca();

        $proposta = PropostaRecebida::create([
            'marca_id' => $marca->id,
            'taxas_relatadas' => [['tipo_operacao' => 'debito', 'parcelas' => 1, 'percentual' => 1.99]],
            'data_proposta' => now()->subDay(),
            'estado' => 'SP',
            'segmento' => 'padaria',
            'consentimento_uso_agregado' => true,
            'status' => StatusRevisao::Pendente,
        ]);

        Livewire::test(EditPropostaRecebida::class, ['record' => $proposta->getKey()])
            ->fillForm(['status' => StatusRevisao::Revisado->value, 'observacao_admin' => 'Virou faixa reportada.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(StatusRevisao::Revisado, $proposta->fresh()->status);
        $this->assertSame($marca->id, $proposta->fresh()->marca_id);
    }

    public function test_painel_inicial_conta_os_pendentes(): void
    {
        $marca = $this->criarMarca();

        PropostaRecebida::create([
            'marca_id' => $marca->id,
            'taxas_relatadas' => [['tipo_operacao' => 'debito', 'parcelas' => 1, 'percentual' => 1.99]],
            'data_proposta' => now()->subDay(),
            'estado' => 'SP',
            'segmento' => 'padaria',
            'consentimento_uso_agregado' => true,
            'status' => StatusRevisao::Pendente,
        ]);

        RelatoTaxaIncorreta::create([
            'marca_id' => $marca->id,
            'mensagem' => 'A taxa parece errada.',
            'status' => StatusRevisao::Pendente,
        ]);

        Livewire::test(PainelInicial::class)
            ->assertSee('Propostas pendentes de revisão')
            ->assertSee('Relatos de taxa incorreta pendentes');
    }

    public function test_toggle_aceita_relatos_salva_no_marca_form(): void
    {
        $marca = $this->criarMarca();
        $marca->update(['aceita_relatos' => false]);

        Livewire::test(\App\Filament\Resources\Marcas\Pages\EditMarca::class, ['record' => $marca->getKey()])
            ->fillForm(['aceita_relatos' => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($marca->fresh()->aceita_relatos);
    }
}
