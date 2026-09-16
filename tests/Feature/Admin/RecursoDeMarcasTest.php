<?php

namespace Tests\Feature\Admin;

use App\Enums\StatusMarca;
use App\Filament\Resources\Marcas\Pages\EditMarca;
use App\Models\Adquirente;
use App\Models\Marca;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 17 tornou `adquirente_id` opcional no banco (marca nova, tipo a
 * TrincaPay, pode não ter o adquirente identificado ainda) — mas o
 * `Select::make('adquirente_id')->required()` do formulário continuou
 * exigindo, e isso bloqueava salvar QUALQUER mudança numa marca sem
 * adquirente, até coisas sem relação nenhuma como trocar o logo. Achado
 * pelo Everton em 16/09/2026 tentando subir o logo da TrincaPay.
 */
class RecursoDeMarcasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_salva_marca_sem_adquirente_subjacente(): void
    {
        $marca = Marca::create([
            'adquirente_id' => null,
            'nome' => 'Marca Sem Adquirente',
            'slug' => 'marca-sem-adquirente',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        Livewire::test(EditMarca::class, ['record' => $marca->getRouteKey()])
            ->set('data.site_url', 'https://exemplo.com.br')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://exemplo.com.br', $marca->fresh()->site_url);
    }

    public function test_ainda_salva_marca_com_adquirente_subjacente(): void
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Teste', 'slug' => 'adquirente-teste']);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Com Adquirente',
            'slug' => 'marca-com-adquirente',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        Livewire::test(EditMarca::class, ['record' => $marca->getRouteKey()])
            ->assertHasNoFormErrors();
    }
}
