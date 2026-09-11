<?php

namespace Tests\Feature\Site;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O site publico fecha; o painel nao.
 *
 * O portal subiu na etapa 11 antes de ter identidade visual propria e com as
 * 964 taxas ainda em rascunho. Receber visita assim — e, pior, ser indexado
 * assim — nao interessa a ninguem. Mas `artisan down` derrubaria o /admin
 * junto, e e por ele que as taxas serao aprovadas enquanto o site espera.
 *
 * Dai um middleware proprio, ligado por SITE_EM_BREVE, aplicado so ao grupo
 * de routes/web.php.
 */
class ModoEmBreveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function rotasPublicas(): array
    {
        return [
            'comparador' => ['/'],
            'listagem de marcas' => ['/maquininhas'],
            'cupons' => ['/cupons'],
            'metodologia' => ['/metodologia'],
            'privacidade' => ['/privacidade'],
            'termos' => ['/termos'],
            'enviar proposta' => ['/enviar-proposta'],
            'guia visual' => ['/guia-visual'],
            'sitemap' => ['/sitemap.xml'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rotasPublicas')]
    public function test_com_a_chave_desligada_o_site_abre_normalmente(string $rota): void
    {
        config(['site.em_breve' => false]);

        $this->get($rota)->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rotasPublicas')]
    public function test_com_a_chave_ligada_toda_rota_publica_responde_503(string $rota): void
    {
        config(['site.em_breve' => true]);

        $this->get($rota)
            ->assertStatus(503)
            ->assertSee('Em breve')
            ->assertHeader('Retry-After');
    }

    /**
     * A metade que importa tanto quanto a outra: se o painel cair junto, nao
     * ha como aprovar taxa nenhuma enquanto o site espera.
     */
    public function test_o_painel_continua_de_pe_com_a_chave_ligada(): void
    {
        config(['site.em_breve' => true]);

        $this->get('/admin/login')->assertOk();

        $this->actingAs(User::factory()->create());
        $this->followingRedirects()->get('/admin')->assertOk();
    }

    /**
     * 503, e nao 200, porque um "em breve" servido com 200 e pagina real aos
     * olhos do buscador: ele indexa, e o dominio aparece com esse texto por
     * semanas. O noindex na view e a segunda tranca.
     */
    public function test_a_pagina_pede_para_nao_ser_indexada(): void
    {
        config(['site.em_breve' => true]);

        $this->get('/')
            ->assertStatus(503)
            ->assertSee('noindex', escape: false);
    }

    public function test_o_padrao_e_desligado_para_nao_fechar_o_site_por_engano(): void
    {
        $this->assertFalse(
            config('site.em_breve'),
            'SITE_EM_BREVE precisa nascer desligado: ligar e decisao explicita do .env.',
        );
    }
}
