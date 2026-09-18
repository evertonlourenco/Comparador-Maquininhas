<?php

namespace Tests\Feature\Institucional;

use Tests\TestCase;

/**
 * Etapa 10: /metodologia, /privacidade e /termos. Sem interação com o banco
 * — são páginas institucionais, servidas por Route::view.
 */
class PaginasLegaisTest extends TestCase
{
    public function test_metodologia_responde_e_explica_as_faixas_reportadas(): void
    {
        $resposta = $this->get('/metodologia');

        $resposta->assertOk();
        $resposta->assertSee('Metodologia', false);
        $resposta->assertSee('faixa reportada', false);
        // Desde a etapa 20 (bloco D) a frase so mora aqui, nao mais no rodape.
        $resposta->assertSee('exatamente a mesma do', false);
    }

    public function test_privacidade_responde_e_mostra_base_legal(): void
    {
        $resposta = $this->get('/privacidade');

        $resposta->assertOk();
        $resposta->assertSee('LGPD', false);
        $resposta->assertSee('consentimento', false);
    }

    public function test_privacidade_mostra_aviso_honesto_quando_identidade_do_controlador_nao_esta_definida(): void
    {
        config(['services.legal.razao_social' => null, 'services.legal.email_contato' => null]);

        $resposta = $this->get('/privacidade');

        $resposta->assertOk();
        $resposta->assertSee('a definir antes do lançamento', false);
    }

    public function test_termos_responde(): void
    {
        $resposta = $this->get('/termos');

        $resposta->assertOk();
        $resposta->assertSee('Termos de Uso', false);
    }

    public function test_metodologia_aparece_na_navegacao_principal(): void
    {
        $resposta = $this->get('/');

        $resposta->assertOk();
        $resposta->assertSee(route('metodologia'), false);
    }

    public function test_links_institucionais_aparecem_no_rodape(): void
    {
        $resposta = $this->get('/metodologia');

        $resposta->assertOk();
        $resposta->assertSee(route('privacidade'), false);
        $resposta->assertSee(route('termos'), false);
        $resposta->assertSee(route('propostas.create'), false);
    }
}
