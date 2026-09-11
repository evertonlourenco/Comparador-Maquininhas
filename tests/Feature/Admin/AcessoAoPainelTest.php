<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O 403 da etapa 11, que so apareceu no primeiro login em producao.
 *
 * `Filament\Http\Middleware\Authenticate` aborta com 403 quando o model de
 * usuario nao implementa `FilamentUser` e `APP_ENV` nao e `local`. Como o
 * desenvolvimento inteiro rodou em `local`, o painel funcionou das etapas 03 a
 * 10 e recusou o acesso no minuto em que subiu.
 *
 * Os outros testes de admin nao pegam isso por construcao: eles usam
 * `Livewire::test($pagina)`, que instancia o componente direto, sem pilha de
 * middlewares HTTP. Este arquivo existe para cobrir o caminho que os outros
 * pulam — a requisicao HTTP de verdade, com middleware e tudo.
 */
class AcessoAoPainelTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A guarda que torna este arquivo honesto: em `local` o Filament deixa
     * qualquer um passar, entao um teste rodando em `local` passaria mesmo com
     * o bug de volta. Se alguem mudar o APP_ENV do phpunit.xml para `local`,
     * e isto aqui que avisa, em vez de o teste virar decoracao.
     */
    public function test_o_ambiente_de_teste_reproduz_a_regra_de_producao(): void
    {
        $this->assertNotSame(
            'local',
            config('app.env'),
            'Em APP_ENV=local o Filament libera o painel para qualquer usuario, '
            .'e este teste deixaria de provar qualquer coisa.',
        );
    }

    public function test_o_model_de_usuario_implementa_o_contrato_do_filament(): void
    {
        $this->assertInstanceOf(
            FilamentUser::class,
            User::factory()->make(),
            'Sem FilamentUser no model, o painel responde 403 em qualquer '
            .'ambiente que nao seja local — inclusive producao.',
        );
    }

    public function test_usuario_autenticado_nao_leva_403_ao_abrir_o_painel(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin')->assertStatus(302);
    }

    public function test_o_painel_abre_de_fato_seguindo_o_redirecionamento(): void
    {
        $this->actingAs(User::factory()->create());

        $this->followingRedirects()->get('/admin')->assertOk();
    }

    public function test_visitante_sem_login_vai_para_a_tela_de_login_e_nao_para_403(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');

        $this->get('/admin/login')->assertOk();
    }
}
