<?php

namespace Tests\Feature\Cupons;

use App\Enums\IncideSobre;
use App\Enums\StatusItem;
use App\Enums\StatusMarca;
use App\Enums\TipoDesconto;
use App\Models\Adquirente;
use App\Models\Cupom;
use App\Models\EventoCupom;
use App\Models\Marca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Etapa 20 (bloco B): a rota de saída do CTA primário do cartão do
 * comparador — /ir/{marca}. Espelha o mesmo cuidado do
 * EventoCupomController (etapa 09): nunca estourar erro para o lojista por
 * causa de um cupom que já não existe ou já venceu.
 */
class SaidaMarcaTest extends TestCase
{
    use RefreshDatabase;

    private function criarMarcaComCupom(): array
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Saída', 'slug' => 'adquirente-saida']);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Saída',
            'slug' => 'marca-saida',
            'site_url' => 'https://exemplo.test/oficial',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $cupom = Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'SAIDA20',
            'tipo_desconto' => TipoDesconto::Percentual,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 20,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://exemplo.test/afiliado',
            'status' => StatusItem::Ativo,
        ]);

        return [$marca, $cupom];
    }

    public function test_com_cupom_vigente_grava_evento_e_redireciona_para_o_link_de_afiliado(): void
    {
        [$marca, $cupom] = $this->criarMarcaComCupom();

        $this->get("/ir/{$marca->slug}?origem=comparador&cupom={$cupom->codigo}")
            ->assertRedirect('https://exemplo.test/afiliado');

        $this->assertDatabaseHas('eventos_cupom', [
            'marca_id' => $marca->id,
            'cupom_id' => $cupom->id,
            'codigo' => 'SAIDA20',
            'tipo_evento' => 'usar_cupom',
            'pagina_origem' => 'comparador',
        ]);
    }

    public function test_sem_codigo_de_cupom_vai_direto_ao_site_oficial_sem_gravar_nada(): void
    {
        [$marca] = $this->criarMarcaComCupom();

        $this->get("/ir/{$marca->slug}")
            ->assertRedirect('https://exemplo.test/oficial');

        $this->assertSame(0, EventoCupom::count());
    }

    public function test_codigo_que_nao_bate_mais_cai_para_o_site_oficial_sem_quebrar(): void
    {
        [$marca] = $this->criarMarcaComCupom();

        $this->get("/ir/{$marca->slug}?cupom=CODIGO-EDITADO-DEPOIS")
            ->assertRedirect('https://exemplo.test/oficial');

        $this->assertSame(0, EventoCupom::count());
    }

    public function test_cupom_vencido_cai_para_o_site_oficial(): void
    {
        [$marca, $cupom] = $this->criarMarcaComCupom();
        $cupom->update(['valido_ate' => Carbon::yesterday()]);

        $this->get("/ir/{$marca->slug}?cupom={$cupom->codigo}")
            ->assertRedirect('https://exemplo.test/oficial');

        $this->assertSame(0, EventoCupom::count());
    }

    public function test_marca_inexistente_da_404(): void
    {
        $this->get('/ir/marca-que-nao-existe')->assertNotFound();
    }
}
