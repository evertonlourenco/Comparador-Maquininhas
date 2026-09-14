<?php

namespace Tests\Feature\Seguranca;

use Tests\TestCase;

/**
 * Etapa 12. App\Http\Middleware\CabecalhosDeSeguranca e global — os quatro
 * cabecalhos aqui sao o que securityheaders.com de fato mede.
 */
class CabecalhosDeSegurancaTest extends TestCase
{
    public function test_pagina_publica_recebe_os_quatro_cabecalhos(): void
    {
        $resposta = $this->get('/');

        $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
        $resposta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $resposta->assertHeader('Permissions-Policy');
        $resposta->assertHeader('Content-Security-Policy');

        $csp = $resposta->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString('nonce-', $csp);
    }

    /**
     * O Filament embute o proprio Alpine e mexe com estilo inline em varios
     * componentes (seletor de cor, por exemplo) — uma CSP estrita ali, sem a
     * mesma cobertura de teste que o site publico tem, arrisca quebrar o
     * painel usado todo dia para aprovar taxa. So os tres cabecalhos sem
     * risco de conflito valem la.
     */
    public function test_admin_recebe_os_tres_cabecalhos_seguros_mas_nao_a_csp(): void
    {
        $resposta = $this->get('/admin/login');

        $resposta->assertHeader('X-Content-Type-Options', 'nosniff');
        $resposta->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $resposta->assertHeader('Permissions-Policy');
        $resposta->assertHeaderMissing('Content-Security-Policy');
    }

    /** O nonce da CSP tem que ser o mesmo que foi para o <script> da pagina. */
    public function test_o_script_inline_do_layout_carrega_o_nonce_da_csp(): void
    {
        $resposta = $this->get('/');

        $csp = $resposta->headers->get('Content-Security-Policy');
        preg_match("/'nonce-([^']+)'/", $csp, $capturado);
        $nonce = $capturado[1] ?? null;

        $this->assertNotNull($nonce);
        $resposta->assertSee('nonce="'.$nonce.'"', false);
    }
}
