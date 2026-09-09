<?php

namespace Tests\Feature\Comparador;

use App\Http\Controllers\ComparadorController;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O que a pagina do comparador (etapa 07) nao pode quebrar em silencio.
 *
 * A aritmetica ja e coberta por ParidadeDoResumoTest; aqui esta o resto: a
 * ordem das quatro perguntas, a promessa da regra 9 (nenhuma consulta ao banco
 * por visita) e a acessibilidade que a etapa 06 estabeleceu — nenhum campo sem
 * rotulo, nem os que so existem depois que o Alpine roda.
 */
class PaginaDoComparadorTest extends TestCase
{
    public function test_a_home_e_o_comparador(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Quanto a maquininha custa para o seu negócio')
            // O comparador le o JSON estatico, e nao um endpoint dinamico.
            ->assertSee(ComparadorController::CAMINHO_DO_JSON, escape: false);
    }

    /**
     * A ordem das perguntas e decisao de produto, nao acaso: faturamento (o
     * unico numero que todo lojista sabe de cabeca), mix (o que decide o
     * resultado), prazo (a quinta dimensao da chave da regra 1) e marcas.
     */
    public function test_as_quatro_perguntas_aparecem_na_ordem_combinada(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $posicoes = [];

        foreach ([
            'Quanto você fatura por mês',
            'Como seus clientes pagam',
            'Quando você quer o dinheiro',
            'Quais marcas comparar',
        ] as $pergunta) {
            $posicao = mb_strpos($html, $pergunta);

            $this->assertNotFalse($posicao, "A pergunta \"{$pergunta}\" sumiu da tela.");
            $posicoes[] = $posicao;
        }

        $ordenadas = $posicoes;
        sort($ordenadas);

        $this->assertSame($ordenadas, $posicoes, 'As quatro perguntas sairam fora de ordem.');
    }

    /** O botao que existe para quem nao quer escolher marca nenhuma. */
    public function test_o_atalho_escolha_por_mim_esta_na_tela(): void
    {
        $this->get('/')->assertOk()->assertSee('Escolha por mim');
    }

    /**
     * Regra 9, cobrada e nao so prometida. A carga chega em pico de video: uma
     * consulta que se infiltre aqui so vai aparecer no pior dia possivel.
     */
    public function test_a_pagina_nao_consulta_o_banco(): void
    {
        DB::enableQueryLog();

        $this->get('/')->assertOk();

        $this->assertSame(
            [],
            DB::getQueryLog(),
            'A pagina do comparador consultou o banco. Regra 9: o catalogo e o JSON estatico.',
        );
    }

    /**
     * Etapa 06: placeholder nao e rotulo, e isso vale para os campos que o
     * Alpine cria depois. Um <input> com id fixo precisa de <label for>; um com
     * :id ligado precisa do :for equivalente no mesmo documento.
     */
    public function test_todo_campo_da_tela_tem_rotulo_associado(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('/<(?:input|select|textarea)\b[^>]*/i', $html, $controles);
        $this->assertNotEmpty($controles[0]);

        preg_match_all('/<label[^>]*\bfor="([^"]+)"/i', $html, $rotulos);
        preg_match_all('/<label[^>]*\s:for="([^"]+)"/i', $html, $rotulosLigados);

        foreach ($controles[0] as $controle) {
            if (preg_match('/\bid="([^"]+)"/', $controle, $id)) {
                $this->assertContains($id[1], $rotulos[1], "Campo sem <label for>: {$controle}");

                continue;
            }

            $this->assertMatchesRegularExpression(
                '/\s:id="([^"]+)"/',
                $controle,
                "Campo sem id nem :id: {$controle}",
            );
            preg_match('/\s:id="([^"]+)"/', $controle, $ligado);

            $this->assertContains(
                $ligado[1],
                $rotulosLigados[1],
                "Campo com id dinamico e sem <label :for> equivalente: {$controle}",
            );
        }
    }

    /**
     * /public/dados esta fora do Git, entao um clone novo cai neste estado. A
     * pagina tem de dizer o que fazer em vez de aparecer vazia.
     */
    public function test_sem_o_json_gerado_a_pagina_explica_o_que_falta(): void
    {
        $html = view('comparador', [
            'caminhoDoJson' => ComparadorController::CAMINHO_DO_JSON,
            'jsonExiste' => false,
            'atualizadoEm' => null,
        ])->render();

        $this->assertStringContainsString('comparador:gerar-json', $html);
    }
}
