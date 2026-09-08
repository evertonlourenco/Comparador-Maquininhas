<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Adquirentes\Pages\ListAdquirentes;
use App\Filament\Resources\Bandeiras\Pages\ListBandeiras;
use App\Filament\Resources\GrupoBandeiras\Pages\EditGrupoBandeira;
use App\Filament\Resources\GrupoBandeiras\Pages\ListGrupoBandeiras;
use App\Filament\Resources\PrazoRecebimentos\Pages\ListPrazoRecebimentos;
use App\Models\GrupoBandeira;
use App\Models\User;
use Database\Seeders\DimensoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * As quatro dimensoes passaram a ter CRUD no painel. O que este teste protege
 * e o essencial delas: as telas montam, e o codigo de uma dimensao curada nao
 * pode ser reescrito pelo painel - ele e referenciado por constante no codigo.
 */
class RecursosDeDimensaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_as_listagens_das_dimensoes_montam(): void
    {
        $this->seed(DimensoesSeeder::class);

        foreach ([ListAdquirentes::class, ListBandeiras::class, ListGrupoBandeiras::class, ListPrazoRecebimentos::class] as $pagina) {
            Livewire::test($pagina)->assertOk();
        }
    }

    public function test_codigo_de_dimensao_curada_nao_e_alterado_pelo_painel(): void
    {
        $this->seed(DimensoesSeeder::class);

        $grupo = GrupoBandeira::where('codigo', GrupoBandeira::VISA_MASTER)->sole();

        Livewire::test(EditGrupoBandeira::class, ['record' => $grupo->getKey()])
            ->fillForm(['nome_exibicao' => 'Visa, Mastercard e Elo', 'codigo' => 'outro_codigo'])
            ->call('save')
            ->assertHasNoFormErrors();

        $grupo->refresh();

        $this->assertSame(GrupoBandeira::VISA_MASTER, $grupo->codigo);
        $this->assertSame('Visa, Mastercard e Elo', $grupo->nome_exibicao);
    }
}
