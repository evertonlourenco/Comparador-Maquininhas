<?php

namespace Tests\Unit\Motor;

use App\Motor\Cenario;
use App\Motor\EstadoDoResultado;
use App\Motor\MotorDeCalculo;
use PHPUnit\Framework\TestCase;
use Tests\Support\CatalogoDeTeste;

/**
 * Casos de valor conhecido sobre o catalogo sintetico. Toda conta esperada
 * esta escrita ao lado do assert - nao ha numero magico aqui.
 *
 * O catalogo: tests/Support/CatalogoDeTeste.php.
 */
class MotorDeCalculoTest extends TestCase
{
    private MotorDeCalculo $motor;

    private array $catalogo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->motor = new MotorDeCalculo;
        $this->catalogo = CatalogoDeTeste::montar();
    }

    // ------------------------------------------------------------------
    // O primitivo: custo de uma venda = percentual + valor fixo (regra 6)
    // ------------------------------------------------------------------

    public function test_custo_de_uma_venda_e_percentual_mais_valor_fixo(): void
    {
        // R$ 1.000,00 a 3,15% = R$ 31,50.
        $so_percentual = $this->motor->custoDaVenda(
            ['percentual' => 3.15, 'valor_fixo' => 0.0], 1000.0, null
        );

        $this->assertSame(31.5, $so_percentual['custo']);
        $this->assertSame(0.0, $so_percentual['custo_fixo']);

        // R$ 2.000,00 a 4% = R$ 80,00; mais 20 x R$ 0,50 = R$ 10,00.
        $com_fixo = $this->motor->custoDaVenda(
            ['percentual' => 4.0, 'valor_fixo' => 0.5], 2000.0, 20
        );

        $this->assertSame(80.0, $com_fixo['custo_percentual']);
        $this->assertSame(10.0, $com_fixo['custo_fixo']);
        $this->assertSame(90.0, $com_fixo['custo']);
    }

    public function test_meio_centavo_na_taxa_vai_para_cima(): void
    {
        // R$ 107,00 a 2,5% = R$ 2,675 exatos. Vira R$ 2,68.
        $custo = $this->motor->custoDaVenda(['percentual' => 2.5, 'valor_fixo' => 0.0], 107.0, null);

        $this->assertSame(2.68, $custo['custo']);
    }

    public function test_valor_fixo_sem_quantidade_nao_e_estimado(): void
    {
        $custo = $this->motor->custoDaVenda(['percentual' => 4.0, 'valor_fixo' => 0.5], 2000.0, null);

        $this->assertNull($custo['custo']);
        $this->assertNull($custo['custo_fixo']);
        $this->assertNotNull($custo['falta']);
    }

    // ------------------------------------------------------------------
    // O cenario completo
    // ------------------------------------------------------------------

    public function test_soma_venda_conta_e_aparelho_em_um_cenario_fechado(): void
    {
        $alfa = $this->item($this->calcular(), 'Alfa');

        $this->assertSame(EstadoDoResultado::Calculado->value, $alfa['estado']);

        // Vendas: R$ 1.000,00 a 1,00% = R$ 10,00; R$ 2.000,00 a 3,15% = R$ 63,00.
        $this->assertSame(73.0, $alfa['custos']['vendas']);

        // Conta: mensalidade R$ 0,00 e nenhum saque, TED ou Pix no cenario.
        $this->assertSame(0.0, $alfa['custos']['conta']);

        // Aparelho: adesao R$ 199,00 menos o cupom de R$ 50,00 = R$ 149,00,
        // amortizada em 12 meses = R$ 12,42. Sem aluguel.
        $this->assertSame(12.42, $alfa['custos']['aparelho']);

        $this->assertSame(85.42, $alfa['custos']['total_mensal']);
        $this->assertSame(['d_1'], $alfa['prazos_usados']);
        $this->assertSame([], $alfa['faltando']);
    }

    public function test_o_total_e_a_soma_exata_das_partes_exibidas(): void
    {
        $alfa = $this->item($this->calcular(), 'Alfa');
        $c = $alfa['custos'];

        $this->assertSame(
            $c['total_mensal'],
            round($c['vendas'] + $c['conta'] + $c['aparelho'] + $c['antecipacao_avulsa'], 2),
        );
    }

    // ------------------------------------------------------------------
    // Regra 5: cupom desconta adesao, nunca percentual
    // ------------------------------------------------------------------

    public function test_cupom_desconta_adesao_e_nao_encosta_no_percentual(): void
    {
        $com = $this->item($this->calcular(), 'Alfa');
        $sem = $this->item($this->calcular(['aplicar_cupom' => false]), 'Alfa');

        // A diferenca esta so no aparelho: R$ 199,00 / 12 = R$ 16,58.
        $this->assertSame(16.58, $sem['custos']['aparelho']);
        $this->assertSame(12.42, $com['custos']['aparelho']);

        // E o custo das vendas e identico nos dois - regra 5.
        $this->assertSame($sem['custos']['vendas'], $com['custos']['vendas']);
        $this->assertSame(50.0, $com['adesao']['desconto_do_cupom']);
        $this->assertNull($sem['cupom']);
    }

    /**
     * Regra 5, revista na etapa 17: cupom de afiliado via de regra nao tem data
     * de fim. valido_ate nulo e vigente, nao vencido - ate a etapa 20 o motor
     * comparava null >= hoje e o cupom da Ton sumia do resultado.
     */
    public function test_cupom_sem_data_de_fim_continua_vigente(): void
    {
        $this->catalogo['marcas'][0]['cupons'][0]['valido_ate'] = null;

        $alfa = $this->item($this->calcular(['hoje' => '2027-06-01']), 'Alfa');

        $this->assertSame(50.0, $alfa['adesao']['desconto_do_cupom']);
        $this->assertSame('ALFA50', $alfa['cupom']['codigo']);
    }

    public function test_cupom_que_ainda_nao_comecou_nao_vale(): void
    {
        $antes = $this->item($this->calcular(['hoje' => '2026-08-31']), 'Alfa');

        $this->assertNull($antes['cupom']);
    }

    /**
     * Etapa 20 (Everton, 18/09/2026): o ranking e pelo que sai todo mes, sem a
     * adesao amortizada. Adesao e custo de entrada, nao decide quem ganha.
     */
    public function test_ranking_ignora_a_adesao_amortizada(): void
    {
        $calculados = array_values(array_filter(
            $this->calcular()['itens'],
            fn (array $item): bool => $item['estado'] === EstadoDoResultado::Calculado->value,
        ));

        $chaves = array_map(
            fn (array $item): float => round($item['custos']['total_mensal'] - ($item['adesao']['por_mes'] ?? 0.0), 2),
            $calculados,
        );

        $ordenadas = $chaves;
        sort($ordenadas);

        $this->assertGreaterThanOrEqual(2, count($chaves));
        $this->assertSame($ordenadas, $chaves);
    }

    public function test_cupom_vencido_some_sozinho(): void
    {
        // O cupom da Alfa vale ate 30/09/2026.
        $depois = $this->item($this->calcular(['hoje' => '2026-10-01']), 'Alfa');

        $this->assertSame(16.58, $depois['custos']['aparelho']);
        $this->assertNull($depois['cupom']);
    }

    public function test_a_adesao_cheia_anda_junto_com_a_amortizada(): void
    {
        $alfa = $this->item($this->calcular(), 'Alfa');

        $this->assertSame(199.0, $alfa['adesao']['preco_cheio']);
        $this->assertSame(149.0, $alfa['adesao']['valor_final']);
        $this->assertSame(12, $alfa['adesao']['amortizada_em_meses']);
        $this->assertSame(12.42, $alfa['adesao']['por_mes']);

        // A parcela arredondada ao centavo nao reconstitui a adesao exata
        // (12 x 12,42 = 149,04). E por isso que valor_final vai junto.
        $this->assertNotSame(149.0, round($alfa['adesao']['por_mes'] * 12, 2));
    }

    public function test_horizonte_de_amortizacao_muda_o_resultado(): void
    {
        $em24 = $this->item($this->calcular(['horizonte_meses' => 24]), 'Alfa');

        // R$ 149,00 / 24 = R$ 6,2083... = R$ 6,21.
        $this->assertSame(6.21, $em24['custos']['aparelho']);
        $this->assertSame(73.0 + 6.21, $em24['custos']['total_mensal']);
    }

    // ------------------------------------------------------------------
    // Prazo: a quinta dimensao da chave
    // ------------------------------------------------------------------

    /**
     * Etapa 20 (Everton, 18/09/2026): nao existe plano que receba debito num
     * prazo e credito em outro. Sem prazo pedido, o motor escolhe UM prazo para
     * todos os cartoes - o mais barato entre os que fecham a conta. A Alfa so
     * vende debito em 1 dia util, entao o credito tambem fica em 1 dia util,
     * mesmo existindo credito mais barato em 30 dias.
     */
    public function test_sem_prazo_pedido_os_cartoes_ficam_num_prazo_so(): void
    {
        $alfa = $this->item($this->calcular(['prazo' => null]), 'Alfa');

        $this->assertSame(EstadoDoResultado::Calculado->value, $alfa['estado']);
        // R$ 1.000,00 a 1,00% + R$ 2.000,00 a 3,15%, ambos em 1 dia util.
        $this->assertSame(73.0, $alfa['custos']['vendas']);
        $this->assertSame(['d_1'], $alfa['prazos_usados']);
        $this->assertStringNotContainsString('prazo de recebimento', implode(' ', $alfa['avisos']));
    }

    public function test_sem_prazo_pedido_o_prazo_unico_e_o_mais_barato_que_fecha_a_conta(): void
    {
        // So credito a vista: 1 dia util (3,15%) e 30 dias (2,00%) fecham os
        // dois. Vence o de 30 dias - R$ 40,00 contra R$ 63,00.
        $alfa = $this->item($this->calcular(['prazo' => null, 'vendas' => [$this->creditoAVista()]]), 'Alfa');

        $this->assertSame(40.0, $alfa['custos']['vendas']);
        $this->assertSame(['d_30'], $alfa['prazos_usados']);
    }

    /**
     * O Pix cai sempre na hora. Pedir "Em 1 dia util" e escolha para os
     * cartoes - antes da etapa 20 o filtro de prazo derrubava o Pix e a marca
     * inteira virava "incompleto" so por aceitar Pix.
     */
    public function test_pix_fica_fora_do_prazo_pedido(): void
    {
        $alfa = $this->item($this->calcular([
            'prazo' => 'd_1',
            'vendas' => [$this->debito(), ['tipo_operacao' => 'pix', 'valor_mensal' => '500,00', 'quantidade_mensal' => 30]],
        ]), 'Alfa');

        $this->assertSame(EstadoDoResultado::Calculado->value, $alfa['estado']);
        $this->assertSame('na_hora', $alfa['vendas'][1]['prazo']);
        // O prazo do plano e o dos cartoes; o Pix nao conta como "mais um prazo".
        $this->assertSame(['d_1'], $alfa['prazos_usados']);
        $this->assertStringNotContainsString('prazo de recebimento', implode(' ', $alfa['avisos']));
    }

    public function test_adesao_com_preco_proprio_no_link_vale_como_valor_final(): void
    {
        // Pedido do Everton (19/09/2026): a adesao mostrada e a do link. Aqui
        // a pagina do link cobra R$ 120,00 pelo aparelho de R$ 199,00 - e nao
        // R$ 149,00 (199 menos o cupom de R$ 50,00).
        $this->catalogo['marcas'][0]['planos'][0]['equipamentos'][0]['preco_adesao_no_link'] = 120.0;

        $adesao = $this->item($this->calcular(), 'Alfa')['adesao'];

        $this->assertSame(199.0, $adesao['vigente']);
        $this->assertSame(120.0, $adesao['valor_final']);
        $this->assertSame(79.0, $adesao['desconto_do_cupom']);
    }

    public function test_preco_no_link_nao_vale_sem_cupom_aplicado(): void
    {
        $this->catalogo['marcas'][0]['planos'][0]['equipamentos'][0]['preco_adesao_no_link'] = 120.0;

        $adesao = $this->item($this->calcular(['aplicar_cupom' => false]), 'Alfa')['adesao'];

        $this->assertSame(199.0, $adesao['valor_final']);
        $this->assertSame(0.0, $adesao['desconto_do_cupom']);
    }

    public function test_plano_que_nao_vende_cartao_no_prazo_pedido_nem_aparece(): void
    {
        // Pedido do Everton (19/09/2026): quem escolheu um prazo nao ve plano
        // que nao recebe nele - nem como "falta dado". A Alfa nao vende
        // cartao na hora (so o Pix cai na hora); pedir esse prazo tira o
        // plano dela do resultado.
        $resultado = $this->calcular(['prazo' => 'na_hora']);

        $this->assertSame([], array_values(array_filter(
            $resultado['itens'],
            fn (array $item): bool => $item['marca']['nome'] === 'Alfa' && ($item['plano']['nome'] ?? null) !== null
                && ($item['plano']['tipo_enquadramento'] ?? null) !== 'promocional',
        )));
    }

    public function test_plano_que_oferece_o_prazo_mas_nao_publica_a_taxa_continua_como_falta_dado(): void
    {
        $alfa = $this->item($this->calcular([
            'prazo' => 'd_1',
            'vendas' => [[
                'tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master', 'parcelas' => 9,
                'valor_mensal' => '1.000,00', 'quantidade_mensal' => 10,
            ]],
        ]), 'Alfa');

        $this->assertSame(EstadoDoResultado::Incompleto->value, $alfa['estado']);
        $this->assertArrayNotHasKey('total_mensal', $alfa['custos']);
        $this->assertArrayHasKey('total_mensal_parcial', $alfa['custos']);
    }

    public function test_prazo_pedido_nao_esconde_plano_num_cenario_so_de_pix(): void
    {
        $resultado = $this->calcular([
            'prazo' => 'na_hora',
            'vendas' => [['tipo_operacao' => 'pix', 'valor_mensal' => '500,00', 'quantidade_mensal' => 30]],
        ]);

        $this->assertNotNull($this->item($resultado, 'Alfa'));
    }

    // ------------------------------------------------------------------
    // Decisao 3: antecipacao nunca e cobrada duas vezes
    // ------------------------------------------------------------------

    public function test_prazo_que_ja_embute_antecipacao_nao_recebe_a_avulsa_por_cima(): void
    {
        $alfa = $this->item($this->calcular(['antecipacao_avulsa' => true, 'prazo' => 'd_1']), 'Alfa');

        $this->assertSame(0.0, $alfa['custos']['antecipacao_avulsa']);
        $this->assertStringContainsString('já embute', implode(' ', $alfa['avisos']));

        // E o total nao muda em relacao ao cenario sem antecipacao.
        $this->assertSame(85.42, $alfa['custos']['total_mensal']);
    }

    public function test_antecipacao_avulsa_incide_no_prazo_que_nao_antecipa(): void
    {
        $alfa = $this->item($this->calcular([
            'antecipacao_avulsa' => true,
            'prazo' => 'd_30',
            'vendas' => [$this->creditoAVista()],
        ]), 'Alfa');

        // R$ 2.000,00 a 2,00% = R$ 40,00 de taxa; sobram R$ 1.960,00 a receber.
        // 30 dias / 30 = 1 mes a 2,00% ao mes = R$ 39,20.
        $this->assertSame(40.0, $alfa['custos']['vendas']);
        $this->assertSame(39.2, $alfa['custos']['antecipacao_avulsa']);
    }

    public function test_parcela_a_parcela_antecipa_pela_media_dos_meses_de_espera(): void
    {
        $alfa = $this->item($this->calcular([
            'antecipacao_avulsa' => true,
            'prazo' => 'parcela_a_parcela',
            'vendas' => [$this->parcelado6x()],
        ]), 'Alfa');

        // Taxa: R$ 2.000,00 a 4% = R$ 80,00, mais 20 x R$ 0,50 = R$ 90,00.
        $this->assertSame(90.0, $alfa['custos']['vendas']);

        // Sobram R$ 1.910,00. Em 6x, a parcela i cai no mes i, entao a espera
        // media e (6 + 1) / 2 = 3,5 meses. 1.910,00 x 2% x 3,5 = R$ 133,70.
        $this->assertSame(133.7, $alfa['custos']['antecipacao_avulsa']);
    }

    // ------------------------------------------------------------------
    // Decisao 1: Pix
    // ------------------------------------------------------------------

    public function test_pix_publicado_a_zero_e_zero_calculado(): void
    {
        $alfa = $this->item($this->calcular([
            'prazo' => 'na_hora',
            'vendas' => [['tipo_operacao' => 'pix', 'valor_mensal' => '500,00', 'quantidade_mensal' => 30]],
        ]), 'Alfa');

        $this->assertSame(EstadoDoResultado::Calculado->value, $alfa['estado']);
        $this->assertSame(0.0, $alfa['custos']['vendas']);
        $this->assertSame('pix', $alfa['vendas'][0]['venda']['grupo']);
    }

    public function test_marca_sem_taxa_de_pix_declara_a_falta_em_vez_de_cobrar_zero(): void
    {
        // Regra 4: o que falta nao pode aparecer como taxa zero.
        $beta = $this->item($this->calcular([
            'prazo' => null,
            'vendas' => [['tipo_operacao' => 'pix', 'valor_mensal' => '500,00', 'quantidade_mensal' => 30]],
        ]), 'Beta');

        $this->assertSame(EstadoDoResultado::Incompleto->value, $beta['estado']);
        $this->assertStringContainsString('Pix', implode(' ', $beta['faltando']));
    }

    // ------------------------------------------------------------------
    // Regra 4: as duas classes de dado e a marca sem dado
    // ------------------------------------------------------------------

    public function test_marca_sem_dado_nenhum_aparece_com_motivo_e_sem_numero(): void
    {
        $delta = $this->item($this->calcular(), 'Delta');

        $this->assertSame(EstadoDoResultado::SemDadoPublicado->value, $delta['estado']);
        $this->assertNull($delta['custos'], 'Zero aqui seria mentira.');
        $this->assertNull($delta['custos_faixa']);
        $this->assertNotNull($delta['motivo']);
    }

    public function test_faixa_reportada_nunca_vira_numero_unico(): void
    {
        $gama = $this->item($this->calcular(['prazo' => null]), 'Gama');
        $faixa = $gama['custos_faixa'];

        $this->assertSame(EstadoDoResultado::FaixaReportada->value, $gama['estado']);
        $this->assertNull($gama['custos'], 'Faixa e taxa publicada nunca dividem a mesma chave.');
        $this->assertArrayNotHasKey('total_mensal', $faixa);

        // Debito R$ 1.000,00 entre 1,20% e 2,10%, mediana 1,50%: R$ 12,00 /
        // R$ 15,00 / R$ 21,00. Credito a vista R$ 2.000,00 entre 2,90% e
        // 4,40%, mediana 3,50%: R$ 58,00 / R$ 70,00 / R$ 88,00.
        $this->assertSame(70.0, $faixa['vendas_minimo']);
        $this->assertSame(85.0, $faixa['vendas_mediana']);
        $this->assertSame(109.0, $faixa['vendas_maximo']);

        // Aparelho: adesao R$ 0,00 e aluguel R$ 40,00.
        $this->assertSame(40.0, $faixa['aparelho']);
        $this->assertSame(110.0, $faixa['total_mensal_minimo']);
        $this->assertSame(125.0, $faixa['total_mensal_mediana']);
        $this->assertSame(149.0, $faixa['total_mensal_maximo']);
    }

    // ------------------------------------------------------------------
    // Regra 3: enquadramento
    // ------------------------------------------------------------------

    public function test_enquadramento_automatico_filtra_por_faturamento(): void
    {
        $dentro = $this->item($this->calcular(['faturamento_mensal' => '3.000,00']), 'Beta');
        $fora = $this->item($this->calcular(['faturamento_mensal' => '8.000,00']), 'Beta');

        // A Beta so tem plano ate R$ 5 mil.
        $this->assertSame(EstadoDoResultado::Calculado->value, $dentro['estado']);
        $this->assertSame(EstadoDoResultado::SemDadoPublicado->value, $fora['estado']);
        $this->assertStringContainsString('faturamento', $fora['motivo']);
    }

    public function test_enquadramento_escolhido_aparece_em_qualquer_faturamento(): void
    {
        foreach (['3.000,00', '8.000,00', '80.000,00'] as $faturamento) {
            $epsilon = $this->item($this->calcular(['faturamento_mensal' => $faturamento]), 'Epsilon');

            $this->assertSame(EstadoDoResultado::Calculado->value, $epsilon['estado']);
            $this->assertNotNull($epsilon['enquadramento']['aviso']);
        }
    }

    // ------------------------------------------------------------------
    // Regra 8: frescor viaja no resultado
    // ------------------------------------------------------------------

    public function test_o_frescor_degrada_em_45_dias_e_vem_no_resultado(): void
    {
        // As taxas do catalogo foram verificadas em 01/09/2026.
        $noPrazo = $this->item($this->calcular(['hoje' => '2026-10-16']), 'Alfa');
        $vencido = $this->item($this->calcular(['hoje' => '2026-10-17']), 'Alfa');

        $this->assertSame(45, $noPrazo['frescor']['dias']);
        $this->assertSame('fresca', $noPrazo['frescor']['nivel']);

        $this->assertSame(46, $vencido['frescor']['dias']);
        $this->assertSame('desatualizada', $vencido['frescor']['nivel']);
        $this->assertSame(CatalogoDeTeste::VERIFICADO_EM, $vencido['frescor']['data_verificacao']);
    }

    // ------------------------------------------------------------------
    // Promocao de entrada: numero verdadeiro, com prazo de validade
    // ------------------------------------------------------------------

    public function test_promocao_de_entrada_tem_estado_proprio_e_nao_se_chama_total_mensal(): void
    {
        $promo = $this->item($this->calcular(), 'Alfa', 'Alfa Entrada');

        $this->assertSame(EstadoDoResultado::Promocional->value, $promo['estado']);

        // Vendas: R$ 1.000,00 a 0,50% = R$ 5,00; R$ 2.000,00 a 1,50% = R$ 30,00.
        // Aparelho: adesao R$ 199,00 menos o cupom de R$ 50,00, em 12 meses =
        // R$ 12,42. Total R$ 47,42 - mais barato que qualquer permanente.
        $this->assertSame(47.42, $promo['custos']['total_mensal_promocional']);

        // E justamente por ser o mais barato e que ele nao pode usar o nome do
        // numero permanente.
        $this->assertArrayNotHasKey('total_mensal', $promo['custos']);
        $this->assertSame('R$ 47,42', $promo['formatado']['total_mensal_promocional']);
        $this->assertNull($promo['formatado']['total_mensal']);
    }

    public function test_a_promocao_diz_os_dois_limites_e_o_plano_seguinte(): void
    {
        $promo = $this->item($this->calcular(), 'Alfa', 'Alfa Entrada');

        // Os dois limites valem em disjuncao: o que vier antes.
        $this->assertStringContainsString('30 dias', $promo['motivo']);
        $this->assertStringContainsString('R$ 5.000,00 processados', $promo['motivo']);
        $this->assertStringContainsString('o que vier antes', $promo['motivo']);

        $this->assertSame(30, $promo['promocao']['dias']);
        $this->assertSame(5000.0, $promo['promocao']['valor_processado']);

        // Sem sucessor declarado, o sucessor e o plano automatico da marca.
        $this->assertSame('Plano Único', $promo['promocao']['sucessor']['nome']);
    }

    public function test_a_promocao_nunca_aparece_antes_de_um_preco_permanente(): void
    {
        $itens = $this->calcular()['itens'];

        $posicaoDaPromocao = null;
        $ultimoPermanente = null;

        foreach ($itens as $i => $item) {
            if ($item['estado'] === EstadoDoResultado::Promocional->value) {
                $posicaoDaPromocao ??= $i;
            }

            if ($item['estado'] === EstadoDoResultado::Calculado->value) {
                $ultimoPermanente = $i;
            }
        }

        $this->assertNotNull($posicaoDaPromocao, 'A promoção não some: o lojista vai cair nela.');
        $this->assertGreaterThan($ultimoPermanente, $posicaoDaPromocao);
    }

    // ------------------------------------------------------------------
    // Taxa condicionada e parcelamento da adesao
    // ------------------------------------------------------------------

    public function test_condicao_da_taxa_sai_colada_no_resultado(): void
    {
        $alfa = $this->item($this->calcular([
            'prazo' => 'na_hora',
            'vendas' => [['tipo_operacao' => 'pix', 'valor_mensal' => '500,00', 'quantidade_mensal' => 30]],
        ]), 'Alfa');

        // O Pix a 0% da Alfa so vale com a chave ativada no aplicativo. O
        // numero e verdadeiro, mas nao vale sozinho.
        $this->assertSame(0.0, $alfa['custos']['vendas']);
        // Etapa 20: a condicao anda na linha (a tela a mostra no "?" ao lado da
        // taxa), e nao repetida como frase solta nos avisos do cartao.
        $this->assertNotContains('Válido com a chave Pix ativada no aplicativo da marca.', $alfa['avisos']);
        $this->assertSame(
            'Válido com a chave Pix ativada no aplicativo da marca.',
            $alfa['vendas'][0]['condicao'],
        );
    }

    public function test_a_parcela_da_marca_e_a_amortizacao_do_motor_sao_numeros_diferentes(): void
    {
        $alfa = $this->item($this->calcular(), 'Alfa');

        // A Alfa parcela a adesao em 10x: R$ 149,00 / 10 = R$ 14,90.
        $this->assertSame(10, $alfa['adesao']['parcelas_oferecidas']);
        $this->assertSame(14.9, $alfa['adesao']['parcela_da_marca']);
        $this->assertSame('10x de R$ 14,90', $alfa['formatado']['adesao']['parcela_da_marca']);

        // O motor amortiza em 12 meses para comparar: R$ 12,42. Sao coisas
        // diferentes, e so a segunda entra na soma do custo mensal.
        $this->assertSame(12.42, $alfa['adesao']['por_mes']);
        $this->assertSame(12.42, $alfa['custos']['aparelho']);

        // A Beta nao declarou parcelamento: nulo, nunca "à vista".
        $beta = $this->item($this->calcular(), 'Beta');
        $this->assertNull($beta['adesao']['parcelas_oferecidas']);
        $this->assertNull($beta['formatado']['adesao']['parcela_da_marca']);
    }

    // ------------------------------------------------------------------
    // Ordenacao
    // ------------------------------------------------------------------

    public function test_so_o_bloco_calculado_e_ranqueado_por_preco(): void
    {
        $itens = $this->calcular(['prazo' => 'd_1'])['itens'];

        $estados = array_map(fn (array $i): string => $i['estado'], $itens);
        $ordens = array_map(fn (string $e): int => EstadoDoResultado::from($e)->ordem(), $estados);

        $ordenado = $ordens;
        sort($ordenado);
        $this->assertSame($ordenado, $ordens, 'Os blocos saem na ordem calculado, faixa, incompleto, sem dado.');

        // Alfa R$ 85,42; Epsilon R$ 45,00 de vendas + R$ 49,90 de mensalidade
        // + R$ 5,00 de adesao promocional amortizada = R$ 99,90; Beta R$ 65,00
        // + R$ 10,00 + R$ 30,00 de aluguel = R$ 105,00.
        $calculados = array_values(array_filter($itens, fn (array $i): bool => $i['estado'] === 'calculado'));

        $this->assertSame(['Alfa', 'Epsilon', 'Beta'], array_map(fn (array $i): string => $i['marca']['nome'], $calculados));
        $this->assertSame([85.42, 99.9, 105.0], array_map(fn (array $i): float => $i['custos']['total_mensal'], $calculados));
    }

    // ------------------------------------------------------------------

    private function calcular(array $ajustes = []): array
    {
        return $this->motor->calcular($this->catalogo, Cenario::deArray([
            'faturamento_mensal' => '3.000,00',
            'hoje' => '2026-09-08',
            'prazo' => 'd_1',
            'vendas' => [$this->debito(), $this->creditoAVista()],
            ...$ajustes,
        ]));
    }

    private function debito(): array
    {
        return ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
            'valor_mensal' => '1.000,00', 'quantidade_mensal' => 50];
    }

    private function creditoAVista(): array
    {
        return ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
            'valor_mensal' => '2.000,00', 'quantidade_mensal' => 40];
    }

    private function parcelado6x(): array
    {
        return ['tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master', 'parcelas' => 6,
            'valor_mensal' => '2.000,00', 'quantidade_mensal' => 20];
    }

    /**
     * O item do resultado daquela marca. Falha claro quando ela sumiu.
     *
     * Ignora o plano promocional por padrao: ele e estado temporario da marca,
     * nao a oferta permanente dela. Para chegar nele, informe o nome do plano.
     */
    private function item(array $resultado, string $marca, ?string $plano = null): array
    {
        foreach ($resultado['itens'] as $item) {
            if ($item['marca']['nome'] !== $marca) {
                continue;
            }

            if ($plano === null && ($item['plano']['tipo_enquadramento'] ?? null) === 'promocional') {
                continue;
            }

            if ($plano === null || $item['plano']['nome'] === $plano) {
                return $item;
            }
        }

        $this->fail("A marca {$marca} sumiu do resultado - nenhuma marca pode sumir (regra 4).");
    }
}
