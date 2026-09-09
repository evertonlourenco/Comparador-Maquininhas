<?php

namespace Tests\Feature\Interface;

use App\Enums\IncideSobre;
use App\Enums\TipoDesconto;
use App\Models\TaxaDivulgada;
use App\Motor\EstadoDoResultado;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * O que a etapa 06 promete e que uma view nao pode quebrar em silencio.
 *
 * Contraste e paleta ficam em `scripts/verifica-contraste.mjs`, que le o
 * app.css; aqui esta o resto: rotulo em todo campo, cupom vencido sumindo
 * sozinho (regra 5), selo degradando aos 45 dias (regra 8), faixa reportada
 * que nunca vira numero unico (regra 4) e link de afiliado marcado.
 */
class ComponentesDoPortalTest extends TestCase
{
    private function render(string $template, array $dados = []): string
    {
        return Blade::render($template, $dados);
    }

    public function test_o_guia_visual_responde(): void
    {
        $this->get('/guia-visual')
            ->assertOk()
            ->assertSee('Guia visual')
            // Pagina de trabalho, nao de publico.
            ->assertSee('noindex', escape: false);
    }

    /**
     * Placeholder nao e rotulo. Todo campo do portal tem <label for> apontando
     * para o id do controle — e essa varredura roda sobre o HTML de verdade.
     */
    public function test_todo_campo_da_pagina_tem_label_associado(): void
    {
        $html = $this->get('/guia-visual')->getContent();

        preg_match_all('/<(?:input|select|textarea)\b[^>]*/i', $html, $controles);
        $this->assertNotEmpty($controles[0], 'A pagina de amostra precisa ter campos para esta varredura valer.');

        preg_match_all('/<label[^>]*\bfor="([^"]+)"/i', $html, $rotulos);
        $rotulados = $rotulos[1];

        foreach ($controles[0] as $controle) {
            // O link de pular e o botao de tema nao sao campos; so <input> e cia caem aqui.
            $this->assertMatchesRegularExpression('/\bid="([^"]+)"/', $controle, "Campo sem id: {$controle}");
            preg_match('/\bid="([^"]+)"/', $controle, $id);

            $this->assertContains($id[1], $rotulados, "Campo sem <label for>: {$controle}");
        }
    }

    public function test_campo_liga_ajuda_e_erro_por_aria_describedby(): void
    {
        $html = $this->render(
            '<x-campo rotulo="Faturamento" nome="faturamento" ajuda="Some so o cartao." erro="Valor invalido." obrigatorio />'
        );

        $this->assertStringContainsString('aria-describedby="campo-faturamento-ajuda campo-faturamento-erro"', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);
        $this->assertStringContainsString('id="campo-faturamento-ajuda"', $html);
        $this->assertStringContainsString('id="campo-faturamento-erro"', $html);
        $this->assertStringContainsString('<label for="campo-faturamento"', $html);
        // O asterisco e decorativo; quem le por audio recebe a palavra.
        $this->assertStringContainsString('(obrigatório)', $html);
    }

    /** Regra 5: some sozinho ao vencer, sem depender de alguem lembrar. */
    public function test_cupom_vencido_nao_renderiza(): void
    {
        $html = $this->render(
            '<x-bloco-cupom codigo="TESTE10" :valido-ate="$ontem" :valor="50" />',
            ['ontem' => Carbon::today()->subDay()]
        );

        $this->assertStringNotContainsString('TESTE10', $html);
        $this->assertSame('', trim($html));
    }

    public function test_cupom_vigente_traz_codigo_validade_e_o_aviso_da_regra_5(): void
    {
        $html = $this->render(
            '<x-bloco-cupom codigo="TESTE10" marca="Marca" :valor="50" :tipo-desconto="$tipo" :incide-sobre="$incide" :valido-ate="$prazo" url="https://exemplo.test" />',
            [
                'prazo' => Carbon::create(2026, 12, 31),
                'tipo' => TipoDesconto::Valor,
                'incide' => IncideSobre::Adesao,
            ]
        );

        $this->assertStringContainsString('TESTE10', $html);
        // Regra 11 em todo numero exibido, inclusive aqui.
        $this->assertStringContainsString('R$ 50,00 de desconto na adesão', $html);
        $this->assertStringContainsString('31/12/2026', $html);
        $this->assertStringContainsString('A taxa pelo nosso link é a mesma do site oficial', $html);
        // Regra 5: a vantagem e a adesao, nunca a taxa.
        $this->assertStringContainsString('desconta apenas a adesão', $html);
    }

    public function test_link_de_afiliado_sai_marcado_e_avisa_a_nova_aba(): void
    {
        $html = $this->render('<x-botao href="https://exemplo.test" afiliado>Contratar</x-botao>');

        $this->assertStringContainsString('rel="noopener noreferrer sponsored nofollow"', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('(abre em nova aba)', $html);
    }

    /** Regra 8: o selo e calculado, nunca gravado — e degrada aos 45 dias. */
    public function test_selo_de_frescor_degrada_no_limite_da_regra_8(): void
    {
        $limite = TaxaDivulgada::DIAS_ATE_DEGRADAR;

        $fresca = $this->render(
            '<x-selo-frescor :data="$data" />',
            ['data' => Carbon::today()->subDays($limite)]
        );
        $degradada = $this->render(
            '<x-selo-frescor :data="$data" />',
            ['data' => Carbon::today()->subDays($limite + 1)]
        );
        $semData = $this->render('<x-selo-frescor :data="null" />');

        $this->assertStringContainsString('text-aferido', $fresca);
        $this->assertStringNotContainsString('acima dos', $fresca);

        $this->assertStringContainsString('text-reportado', $degradada);
        $this->assertStringContainsString('acima dos', $degradada);

        $this->assertStringContainsString('Sem data de verificação', $semData);
    }

    /** Regra 4: faixa reportada nunca vira numero exato. */
    public function test_tabela_reportada_sai_como_faixa_e_nunca_como_numero_unico(): void
    {
        $html = $this->render(
            '<x-tabela-taxas classe="reportada" titulo="Marca" :linhas="$linhas" />',
            ['linhas' => [
                ['rotulo' => 'Débito', 'minimo' => 1.29, 'mediana' => 1.99, 'maximo' => 2.6, 'relatos' => 84],
            ]]
        );

        $this->assertStringContainsString('Faixa reportada', $html);
        $this->assertStringContainsString('1,29%', $html);
        $this->assertStringContainsString('2,60%', $html);
        $this->assertStringContainsString('1,99%', $html);
        $this->assertStringContainsString('Mediana', $html);
        $this->assertStringContainsString('não tabela publicada pela marca', $html);
        // A etiqueta da outra classe nao pode aparecer na mesma tabela.
        $this->assertStringNotContainsString('Taxa divulgada', $html);
    }

    public function test_tabela_divulgada_cola_a_condicao_no_numero(): void
    {
        $html = $this->render(
            '<x-tabela-taxas titulo="Marca" :linhas="$linhas" />',
            ['linhas' => [
                ['rotulo' => 'Pix', 'percentual' => 0.0, 'condicao' => 'Exige ativar a chave Pix no aplicativo.'],
                ['rotulo' => 'Crédito 21x', 'percentual' => null],
            ]]
        );

        $this->assertStringContainsString('Taxa divulgada', $html);
        $this->assertStringContainsString('0,00%', $html);
        $this->assertStringContainsString('Exige ativar a chave Pix no aplicativo.', $html);
        // Sem taxa nao vira zero: vira o motivo.
        $this->assertStringContainsString('não publicada', $html);
        // Regiao rolavel alcancavel pelo teclado.
        $this->assertStringContainsString('tabindex="0"', $html);
        $this->assertStringContainsString('role="region"', $html);
    }

    public function test_cartao_de_marca_mostra_o_estado_do_resultado(): void
    {
        $html = $this->render(
            '<x-cartao-marca nome="Marca" :estado="$estado" adquirente="Adquirente" :nota="7.4" :nota-data="$data" />',
            ['estado' => EstadoDoResultado::SemDadoPublicado, 'data' => Carbon::create(2026, 9, 8)]
        );

        $this->assertStringContainsString(EstadoDoResultado::SemDadoPublicado->rotulo(), $html);
        // Regra 7: adquirente e transparencia, e vem rotulado para quem le por audio.
        $this->assertStringContainsString('Adquirente que processa por trás:', $html);
        $this->assertStringContainsString('7,4', $html);
        $this->assertStringContainsString('08/09/2026', $html);
    }
}
