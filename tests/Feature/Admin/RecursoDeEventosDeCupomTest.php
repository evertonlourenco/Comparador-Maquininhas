<?php

namespace Tests\Feature\Admin;

use App\Enums\PaginaOrigemCupom;
use App\Enums\TipoEventoCupom;
use App\Filament\Resources\EventosCupom\Pages\ListEventosCupom;
use App\Models\Adquirente;
use App\Models\EventoCupom;
use App\Models\Marca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 09: o log de cliques em cupom só tem listagem no painel — sem
 * create/edit/delete, porque editar um evento à mão invalidaria a
 * reconciliação com o relatório do parceiro.
 */
class RecursoDeEventosDeCupomTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_a_listagem_monta_e_mostra_os_eventos(): void
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Painel', 'slug' => 'adquirente-painel']);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Painel',
            'slug' => 'marca-painel',
            'publica_tabela' => true,
            'status' => 'ativa',
        ]);

        EventoCupom::create([
            'marca_id' => $marca->id,
            'cupom_id' => null,
            'codigo' => 'PAINEL10',
            'tipo_evento' => TipoEventoCupom::UsarCupom,
            'pagina_origem' => PaginaOrigemCupom::PaginaDeCupom,
        ]);

        Livewire::test(ListEventosCupom::class)
            ->assertOk()
            ->assertCanSeeTableRecords(EventoCupom::all());
    }

    public function test_nao_ha_acao_de_criar(): void
    {
        Livewire::test(ListEventosCupom::class)
            ->assertActionDoesNotExist('create');
    }
}
