<?php

namespace Tests\Feature\Admin;

use App\Enums\StatusPublicacao;
use App\Filament\Resources\Equipamentos\Pages\ListEquipamentos;
use App\Filament\Resources\FaixaReportadas\Pages\CreateFaixaReportada;
use App\Filament\Resources\FaixaReportadas\Pages\ListFaixaReportadas;
use App\Filament\Resources\Marcas\Pages\ListMarcas;
use App\Filament\Resources\Planos\Pages\ListPlanos;
use App\Filament\Resources\PrazoRecebimentos\Pages\ListPrazoRecebimentos;
use App\Filament\Resources\TaxaDivulgadas\Pages\CreateTaxaDivulgada;
use App\Filament\Resources\TaxaDivulgadas\Pages\LancamentoEmLote;
use App\Filament\Resources\TaxaDivulgadas\Pages\ListTaxaDivulgadas;
use App\Filament\Widgets\PainelInicial;
use App\Models\Marca;
use App\Models\TaxaDivulgada;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Roda a carga da etapa 04 do zero e cobra dela as regras que nao podem ser
 * quebradas em silencio. Nao afere quantidade: marca nova entra sem mexer
 * aqui, mas nenhuma delas entra furando regra.
 */
class CargaEtapa04Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->seed(DatabaseSeeder::class);
    }

    public function test_o_painel_renderiza_com_a_carga_real(): void
    {
        $paginas = [
            ListMarcas::class, ListPlanos::class, ListEquipamentos::class,
            ListTaxaDivulgadas::class, ListFaixaReportadas::class,
            LancamentoEmLote::class, PainelInicial::class,
            // Os formularios de taxa passaram a montar a lista de grupos de
            // bandeira a partir do tipo de operacao (etapa 05, decisao 1).
            CreateTaxaDivulgada::class, CreateFaixaReportada::class,
            ListPrazoRecebimentos::class,
        ];

        foreach ($paginas as $pagina) {
            Livewire::test($pagina)->assertOk();
        }
    }

    public function test_a_carga_respeita_as_regras_da_taxa(): void
    {
        $this->assertGreaterThan(0, TaxaDivulgada::count());

        // Regra 10: nada entra publicado.
        $this->assertSame(0, TaxaDivulgada::where('status', '!=', StatusPublicacao::Rascunho)->count());

        // Regra 6: nenhuma taxa sem fonte.
        $this->assertSame(0, TaxaDivulgada::whereNull('url_fonte')->orWhere('url_fonte', '')->count());

        // Regra 1: marca_id vem do plano, escrito so pelo hook.
        $this->assertSame(0, TaxaDivulgada::whereNull('marca_id')->count());
        $this->assertSame(0, TaxaDivulgada::join('planos', 'planos.id', '=', 'taxas_divulgadas.plano_id')
            ->whereColumn('planos.marca_id', '!=', 'taxas_divulgadas.marca_id')->count());

        // Regra 4: as duas classes nunca se misturam.
        $this->assertSame(0, TaxaDivulgada::whereIn('marca_id', Marca::where('publica_tabela', false)->pluck('id'))->count());

        // Regra 2: parcelas sao inteiro, e so o parcelado passa de 1.
        $this->assertSame(0, TaxaDivulgada::where('tipo_operacao', '!=', 'credito_parcelado')->where('parcelas', '!=', 1)->count());
        $this->assertSame(0, TaxaDivulgada::where('tipo_operacao', 'credito_parcelado')
            ->where(fn ($q) => $q->where('parcelas', '<', 2)->orWhere('parcelas', '>', 21))->count());
    }

    public function test_a_carga_e_idempotente(): void
    {
        $antes = TaxaDivulgada::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($antes, TaxaDivulgada::count());
    }
}
