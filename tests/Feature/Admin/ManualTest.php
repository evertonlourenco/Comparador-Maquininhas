<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\Manual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Etapa 18: manual do administrador, página dentro do próprio painel
 * (/admin/manual) — não PDF, para nunca envelhecer numa pasta esquecida.
 */
class ManualTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pagina_monta_sem_excecao(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Manual::class)->assertOk();
    }

    public function test_a_rota_e_admin_manual(): void
    {
        $this->assertSame('manual', Manual::getSlug());
    }

    public function test_cobre_os_oito_pontos_pedidos_pelo_everton(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Manual::class)
            ->assertSee('A ordem certa de cadastro')
            ->assertSee('Como lançar a tabela de taxas de uma marca em lote')
            ->assertSee('O que significa cada estado')
            ->assertSee('regenerar o JSON')
            ->assertSee('As duas filas de revisão de relatos de lojistas')
            ->assertSee('Como ler o painel de saúde')
            ->assertSee('Como restaurar um backup')
            ->assertSee('O que nunca fazer');
    }

    public function test_explica_a_regra_de_parcelas_1x_e_2x_a_21x(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Manual::class)
            ->assertSee('crédito à vista')
            ->assertSee('crédito parcelado');
    }

    public function test_traz_o_comando_exato_de_regeneracao_do_json(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Manual::class)
            ->assertSee('comparador:gerar-json');
    }

    public function test_avisa_que_publicar_no_painel_nao_muda_o_site_sozinho(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Manual::class)
            ->assertSee('não muda o site sozinho');
    }

    public function test_avisa_que_as_filas_de_revisao_nunca_publicam_sozinhas(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Manual::class)
            ->assertSee('não publicam nada sozinhas');
    }
}
