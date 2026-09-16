<?php

namespace Tests\Feature\Console;

use App\Enums\IncideSobre;
use App\Enums\StatusItem;
use App\Enums\StatusMarca;
use App\Enums\TipoDesconto;
use App\Models\Adquirente;
use App\Models\Cupom;
use App\Models\Marca;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Etapa 16, prioridade 1: `links:verificar` é o comando de maior valor da
 * lista — link de afiliado quebrado é receita perdida e hoje ninguém vê.
 */
class VerificarLinksAfiliadosTest extends TestCase
{
    use RefreshDatabase;

    private function criarAdquirente(): Adquirente
    {
        return Adquirente::create(['nome' => 'Adquirente Links', 'slug' => 'adquirente-links']);
    }

    public function test_marca_com_link_no_ar_fica_marcada_como_nao_quebrada(): void
    {
        Http::fake(['https://no-ar.test/marca' => Http::response('', 200)]);

        $marca = Marca::create([
            'adquirente_id' => $this->criarAdquirente()->id,
            'nome' => 'Marca No Ar',
            'slug' => 'marca-no-ar',
            'site_url' => 'https://no-ar.test/marca',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $this->artisan('links:verificar')->assertExitCode(0);

        $marca->refresh();

        $this->assertFalse($marca->link_quebrado);
        $this->assertSame(200, $marca->link_ultimo_status);
        $this->assertNotNull($marca->link_verificado_em);
    }

    public function test_marca_com_link_quebrado_fica_marcada(): void
    {
        Http::fake(['https://quebrado.test/marca' => Http::response('', 404)]);

        $marca = Marca::create([
            'adquirente_id' => $this->criarAdquirente()->id,
            'nome' => 'Marca Quebrada',
            'slug' => 'marca-quebrada',
            'site_url' => 'https://quebrado.test/marca',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $this->artisan('links:verificar')->assertExitCode(0);

        $marca->refresh();

        $this->assertTrue($marca->link_quebrado);
        $this->assertSame(404, $marca->link_ultimo_status);
    }

    public function test_cupom_com_link_de_afiliado_quebrado_fica_marcado(): void
    {
        Http::fake(['https://quebrado.test/cupom' => Http::response('', 500)]);

        $marca = Marca::create([
            'adquirente_id' => $this->criarAdquirente()->id,
            'nome' => 'Marca Do Cupom',
            'slug' => 'marca-do-cupom',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $cupom = Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'LINKQUEBRADO',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 10,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://quebrado.test/cupom',
            'status' => StatusItem::Ativo,
        ]);

        $this->artisan('links:verificar')->assertExitCode(0);

        $cupom->refresh();

        $this->assertTrue($cupom->link_quebrado);
        $this->assertSame(500, $cupom->link_ultimo_status);
    }

    /**
     * Achado em 16/09/2026 (Yelly): o CloudFront/S3 dela devolve HTTP 404
     * pra URL do cupom mesmo servindo a página real, que funciona no
     * navegador. `link_confirmado_manualmente` é a válvula de escape - o
     * comando continua gravando o status HTTP real, só para de reportar
     * "quebrado".
     */
    public function test_link_confirmado_manualmente_nao_fica_marcado_como_quebrado_mesmo_com_404(): void
    {
        Http::fake(['https://confirmado.test/cupom' => Http::response('', 404)]);

        $marca = Marca::create([
            'adquirente_id' => $this->criarAdquirente()->id,
            'nome' => 'Marca Confirmada',
            'slug' => 'marca-confirmada',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $cupom = Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'CONFIRMADO',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 10,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://confirmado.test/cupom',
            'link_confirmado_manualmente' => true,
            'status' => StatusItem::Ativo,
        ]);

        $this->artisan('links:verificar')->assertExitCode(0);

        $cupom->refresh();

        $this->assertFalse($cupom->link_quebrado);
        $this->assertSame(404, $cupom->link_ultimo_status, 'o status real continua gravado, só o alerta de quebrado para');
    }

    /** A mesma válvula de escape vale para o site_url da marca. */
    public function test_marca_com_link_confirmado_manualmente_nao_fica_marcada_como_quebrada(): void
    {
        Http::fake(['https://confirmado.test/marca' => Http::response('', 404)]);

        $marca = Marca::create([
            'adquirente_id' => $this->criarAdquirente()->id,
            'nome' => 'Marca Confirmada Site',
            'slug' => 'marca-confirmada-site',
            'site_url' => 'https://confirmado.test/marca',
            'link_confirmado_manualmente' => true,
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $this->artisan('links:verificar')->assertExitCode(0);

        $marca->refresh();

        $this->assertFalse($marca->link_quebrado);
        $this->assertSame(404, $marca->link_ultimo_status);
    }

    /** HEAD sem suporte (405) tem que cair para GET antes de decidir. */
    public function test_405_em_head_tenta_get_antes_de_marcar_quebrado(): void
    {
        Http::fake(function ($request) {
            return $request->method() === 'HEAD'
                ? Http::response('', 405)
                : Http::response('', 200);
        });

        $marca = Marca::create([
            'adquirente_id' => $this->criarAdquirente()->id,
            'nome' => 'Marca Sem Head',
            'slug' => 'marca-sem-head',
            'site_url' => 'https://sem-head.test/marca',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $this->artisan('links:verificar')->assertExitCode(0);

        $marca->refresh();

        $this->assertFalse($marca->link_quebrado);
        $this->assertSame(200, $marca->link_ultimo_status);
    }
}
