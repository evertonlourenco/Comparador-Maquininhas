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
 * Etapa 09: um evento por clique em "usar cupom" ou por cópia de código,
 * para reconciliar com os relatórios dos parceiros no fim do mês.
 */
class RastreamentoDeCupomTest extends TestCase
{
    use RefreshDatabase;

    private function criarMarcaComCupom(): array
    {
        $adquirente = Adquirente::create(['nome' => 'Adquirente Rastreada', 'slug' => 'adquirente-rastreada']);

        $marca = Marca::create([
            'adquirente_id' => $adquirente->id,
            'nome' => 'Marca Rastreada',
            'slug' => 'marca-rastreada',
            'publica_tabela' => true,
            'status' => StatusMarca::Ativa,
        ]);

        $cupom = Cupom::create([
            'marca_id' => $marca->id,
            'codigo' => 'RASTREIO10',
            'tipo_desconto' => TipoDesconto::Valor,
            'incide_sobre' => IncideSobre::Adesao,
            'valor' => 10,
            'valido_de' => Carbon::today()->subDay(),
            'valido_ate' => Carbon::today()->addDays(10),
            'link_afiliado' => 'https://exemplo.test/rastreio',
            'status' => StatusItem::Ativo,
        ]);

        return [$marca, $cupom];
    }

    public function test_grava_evento_de_usar_cupom(): void
    {
        [$marca, $cupom] = $this->criarMarcaComCupom();

        $this->postJson('/eventos/cupons', [
            'marca' => $marca->slug,
            'codigo' => $cupom->codigo,
            'tipo_evento' => 'usar_cupom',
            'pagina_origem' => 'cupom_marca',
        ])->assertNoContent();

        $this->assertDatabaseHas('eventos_cupom', [
            'marca_id' => $marca->id,
            'cupom_id' => $cupom->id,
            'codigo' => 'RASTREIO10',
            'tipo_evento' => 'usar_cupom',
            'pagina_origem' => 'cupom_marca',
        ]);
    }

    public function test_grava_evento_de_copiar_codigo(): void
    {
        [$marca, $cupom] = $this->criarMarcaComCupom();

        $this->postJson('/eventos/cupons', [
            'marca' => $marca->slug,
            'codigo' => $cupom->codigo,
            'tipo_evento' => 'copiar_codigo',
            'pagina_origem' => 'cupons',
        ])->assertNoContent();

        $this->assertDatabaseHas('eventos_cupom', [
            'tipo_evento' => 'copiar_codigo',
            'pagina_origem' => 'cupons',
        ]);
    }

    public function test_marca_inexistente_nao_grava_e_nao_quebra(): void
    {
        $this->postJson('/eventos/cupons', [
            'marca' => 'marca-que-nao-existe',
            'codigo' => 'QUALQUER',
            'tipo_evento' => 'usar_cupom',
            'pagina_origem' => 'marca',
        ])->assertNoContent();

        $this->assertSame(0, EventoCupom::count());
    }

    public function test_codigo_sem_cupom_correspondente_grava_evento_sem_cupom_id(): void
    {
        [$marca] = $this->criarMarcaComCupom();

        $this->postJson('/eventos/cupons', [
            'marca' => $marca->slug,
            'codigo' => 'CODIGO-EDITADO-DEPOIS',
            'tipo_evento' => 'copiar_codigo',
            'pagina_origem' => 'marca',
        ])->assertNoContent();

        $this->assertDatabaseHas('eventos_cupom', [
            'marca_id' => $marca->id,
            'cupom_id' => null,
            'codigo' => 'CODIGO-EDITADO-DEPOIS',
        ]);
    }

    public function test_tipo_evento_invalido_e_rejeitado(): void
    {
        [$marca, $cupom] = $this->criarMarcaComCupom();

        $this->postJson('/eventos/cupons', [
            'marca' => $marca->slug,
            'codigo' => $cupom->codigo,
            'tipo_evento' => 'clique_qualquer',
            'pagina_origem' => 'cupons',
        ])->assertStatus(422);

        $this->assertSame(0, EventoCupom::count());
    }

    public function test_pagina_origem_invalida_e_rejeitada(): void
    {
        [$marca, $cupom] = $this->criarMarcaComCupom();

        $this->postJson('/eventos/cupons', [
            'marca' => $marca->slug,
            'codigo' => $cupom->codigo,
            'tipo_evento' => 'usar_cupom',
            'pagina_origem' => 'pagina_qualquer',
        ])->assertStatus(422);

        $this->assertSame(0, EventoCupom::count());
    }

    public function test_campos_ausentes_sao_rejeitados(): void
    {
        $this->postJson('/eventos/cupons', [])->assertStatus(422);
    }
}
