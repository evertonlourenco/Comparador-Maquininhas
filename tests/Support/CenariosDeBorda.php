<?php

namespace Tests\Support;

/**
 * Os casos de borda da etapa 05, em um lugar so.
 *
 * Eles nasceram no teste de paridade do motor e sao reusados pelo teste de
 * paridade do resumo (etapa 07) de proposito: as duas camadas tem gemeo em
 * JavaScript, e a lista que pegou divergencia uma vez e a lista que tem de
 * continuar rodando. Duplicar os cenarios seria deixar uma das duas para tras
 * no dia em que alguem acrescentasse um caso novo.
 *
 * Sinteticos cobrem os cinco estados da regra 4 e os dois lados da decisao 3,
 * com valores redondos conferiveis a mao. Reais exercitam percentual de quatro
 * casas e valores que nao caem redondos no centavo.
 */
final class CenariosDeBorda
{
    /**
     * @return array<string, array{0: string, 1: array}>
     */
    public static function todos(): array
    {
        $vendasBasicas = [
            ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                'valor_mensal' => '1.000,00', 'quantidade_mensal' => 50],
            ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                'valor_mensal' => '2.000,00', 'quantidade_mensal' => 40],
        ];

        return [
            // Sinteticos: cobrem os quatro estados da regra 4 e os dois lados
            // da decisao 3.
            'sintetico: cenario fechado com cupom' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => 'd_1', 'vendas' => $vendasBasicas,
            ]],
            'sintetico: motor escolhe o prazo mais barato' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => null, 'vendas' => $vendasBasicas,
            ]],
            'sintetico: antecipacao ja embutida no percentual' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => 'd_1', 'antecipacao_avulsa' => true, 'vendas' => $vendasBasicas,
            ]],
            'sintetico: antecipacao avulsa parcela a parcela' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => 'parcela_a_parcela', 'antecipacao_avulsa' => true,
                'vendas' => [['tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master',
                    'parcelas' => 6, 'valor_mensal' => '2.000,00', 'quantidade_mensal' => 20]],
            ]],
            'sintetico: valor fixo sem quantidade informada' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08',
                'prazo' => 'parcela_a_parcela',
                'vendas' => [['tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master',
                    'parcelas' => 6, 'valor_mensal' => '2.000,00']],
            ]],
            'sintetico: Pix e o grupo tecnico' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08', 'prazo' => null,
                'vendas' => [['tipo_operacao' => 'pix', 'valor_mensal' => '500,00', 'quantidade_mensal' => 30]],
            ]],
            'sintetico: cupom vencido e frescor degradado' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-10-17',
                'prazo' => 'd_1', 'vendas' => $vendasBasicas, 'saques_mensais' => 3,
                'teds_mensais' => 2, 'pix_envios_mensais' => 7,
            ]],
            // R$ 107,00 a 2,50% da R$ 2,675 exatos - o desempate que separa
            // round() do PHP de Math.round() do JavaScript. Sem um caso assim
            // a paridade passaria mesmo com o arredondamento errado de um lado.
            'sintetico: meio centavo no percentual da taxa' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08', 'prazo' => 'd_1',
                'vendas' => [['tipo_operacao' => 'debito', 'grupo' => 'demais', 'parcelas' => 1,
                    'valor_mensal' => '107,00', 'quantidade_mensal' => 3]],
            ]],
            'sintetico: horizonte de 24 meses' => ['sintetico', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-08', 'prazo' => 'd_1',
                'horizonte_meses' => 24, 'vendas' => $vendasBasicas,
            ]],

            // Reais: percentuais de quatro casas e valores que nao caem
            // redondos no centavo.
            'real: faturamento de 10 mil em 1 dia util' => ['real', [
                'faturamento_mensal' => '10.000,00', 'hoje' => '2026-09-20', 'prazo' => 'd_1',
                'vendas' => [
                    ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '4.317,49', 'quantidade_mensal' => 213],
                    ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '3.982,51', 'quantidade_mensal' => 97],
                    ['tipo_operacao' => 'credito_parcelado', 'grupo' => 'demais', 'parcelas' => 6,
                        'valor_mensal' => '1.700,00', 'quantidade_mensal' => 11],
                ],
            ]],
            'real: sem prazo pedido, com antecipacao avulsa' => ['real', [
                'faturamento_mensal' => '45.000,00', 'hoje' => '2026-09-20', 'prazo' => null,
                'antecipacao_avulsa' => true, 'saques_mensais' => 4, 'teds_mensais' => 1,
                'vendas' => [
                    ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '20.000,00', 'quantidade_mensal' => 640],
                    ['tipo_operacao' => 'credito_parcelado', 'grupo' => 'visa_master', 'parcelas' => 12,
                        'valor_mensal' => '25.000,00', 'quantidade_mensal' => 130],
                ],
            ]],
            // A promocao de entrada do Ton, que e onde os dois motores tem de
            // concordar tanto no estado quanto no texto do aviso de validade.
            'real: promocao de entrada do Ton' => ['real', [
                'faturamento_mensal' => '3.000,00', 'hoje' => '2026-09-20', 'prazo' => 'd_1',
                'vendas' => [
                    ['tipo_operacao' => 'debito', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '1.500,00', 'quantidade_mensal' => 80],
                    ['tipo_operacao' => 'credito_avista', 'grupo' => 'visa_master', 'parcelas' => 1,
                        'valor_mensal' => '1.500,00', 'quantidade_mensal' => 40],
                ],
            ]],
            'real: Pix da InfinitePay' => ['real', [
                'faturamento_mensal' => '9.999,99', 'hoje' => '2026-09-20', 'prazo' => 'na_hora',
                'vendas' => [['tipo_operacao' => 'pix', 'valor_mensal' => '2.345,67', 'quantidade_mensal' => 88]],
            ]],
            'real: faturamento alto, todos os 21x do Ton' => ['real', [
                'faturamento_mensal' => '120.000,00', 'hoje' => '2026-11-30', 'prazo' => 'na_hora',
                'vendas' => [['tipo_operacao' => 'credito_parcelado', 'grupo' => 'demais', 'parcelas' => 21,
                    'valor_mensal' => '33.333,33', 'quantidade_mensal' => 7]],
            ]],
        ];
    }
}
