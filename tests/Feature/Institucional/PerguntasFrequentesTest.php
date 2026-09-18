<?php

namespace Tests\Feature\Institucional;

use App\Support\PerguntasFrequentes;
use Tests\TestCase;

/**
 * Etapa 20 (bloco F): /perguntas-frequentes, o FAQPage de schema.org e o
 * bloco de 4 perguntas no fim da home. Sem interação com o banco — é conteúdo
 * fixo, servido por Route::view.
 */
class PerguntasFrequentesTest extends TestCase
{
    public function test_a_pagina_responde_e_lista_as_dez_perguntas(): void
    {
        $resposta = $this->get('/perguntas-frequentes');

        $resposta->assertOk();
        $resposta->assertSee('Perguntas frequentes', false);

        foreach (PerguntasFrequentes::todas() as $p) {
            $resposta->assertSee($p['pergunta'], false);
        }
    }

    public function test_a_pagina_tem_o_faqpage_em_schema_org(): void
    {
        $html = $this->get('/perguntas-frequentes')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"FAQPage"', $html);
        $this->assertStringContainsString('"@type":"Question"', $html);
        $this->assertStringContainsString('"@type":"Answer"', $html);

        // As 10 perguntas do schema batem com as 10 da tela — nenhuma
        // pergunta com resposta so na tela e nao no dado estruturado.
        foreach (PerguntasFrequentes::todas() as $p) {
            $this->assertStringContainsString(
                json_encode($p['pergunta'], JSON_UNESCAPED_UNICODE),
                $html,
            );
        }
    }

    public function test_perguntas_frequentes_aparece_na_navegacao_e_no_rodape(): void
    {
        $resposta = $this->get('/');

        $resposta->assertOk();
        $resposta->assertSee(route('faq'), false);
    }

    public function test_a_home_mostra_um_bloco_com_quatro_das_dez_perguntas(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $destaque = PerguntasFrequentes::destaque();
        $this->assertCount(4, $destaque);

        foreach ($destaque as $p) {
            $this->assertStringContainsString($p['pergunta'], $html);
        }

        // O link para a pagina completa esta na home.
        $this->assertStringContainsString(route('faq'), $html);
    }
}
