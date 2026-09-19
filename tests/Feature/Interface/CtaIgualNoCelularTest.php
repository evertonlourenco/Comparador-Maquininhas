<?php

namespace Tests\Feature\Interface;

use Tests\TestCase;

/**
 * Regra do Everton (19/09/2026): tudo que muda para quem usa computador
 * muda igual no celular. O CTA da barra fixa do celular tem de ser o mesmo
 * do cartao — mesmo texto, mesmo link, mesmo rel — e o cupom desmarcado na
 * comparacao nunca troca o link de afiliado pelo site oficial.
 */
class CtaIgualNoCelularTest extends TestCase
{
    public function test_a_barra_fixa_do_celular_usa_o_mesmo_texto_e_link_do_cartao(): void
    {
        $view = file_get_contents(resource_path('views/comparador.blade.php'));
        $cartao = file_get_contents(resource_path('views/components/resultado-comparado.blade.php'));

        foreach (['textoContratar(', 'hrefContratar(', 'temCupomParaContratar('] as $funcao) {
            $this->assertStringContainsString($funcao.'item)', $cartao);
            $this->assertStringContainsString($funcao.'melhorItem)', $view, "A barra do celular precisa usar {$funcao}");
        }

        $this->assertStringNotContainsString('textoContratarCurto', $view);
    }

    public function test_desmarcar_cupom_nao_troca_o_link_de_afiliado_pelo_site_oficial(): void
    {
        $js = file_get_contents(resource_path('js/comparador.js'));

        $this->assertStringContainsString('cupomDaMarca(item)', $js);
        $this->assertStringNotContainsString('return Boolean(item.comparacao?.custo_inicial?.tem_cupom && item.cupom)', $js);
    }
}
