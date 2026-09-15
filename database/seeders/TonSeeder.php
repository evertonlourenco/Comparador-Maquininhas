<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoEquipamento;
use App\Models\Equipamento;
use App\Models\GrupoBandeira;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use Illuminate\Support\Str;

/**
 * Ton (Stone), lida em 08/09/2026 do simulador oficial de taxas do site.
 *
 * O simulador expoe a tabela inteira: 6 faixas de faturamento x 2 prazos de
 * recebimento x bandeira x 1x a 21x. Visa e Mastercard tem percentuais
 * identicos entre si, e Elo e Amex tambem - por isso a tabela cabe exatamente
 * nos grupos visa_master e demais, sem precisar de grupo novo.
 *
 * Regra 3: as faixas de faturamento sao planos de enquadramento automatico -
 * o lojista nao escolhe, cai na faixa conforme vendeu no mes anterior.
 */
class TonSeeder extends SeederDeMarca
{
    private const URL = 'https://www.ton.com.br/';

    public function run(): void
    {
        $marca = $this->marca('ton');

        $fonte = $this->fonte(
            self::URL,
            'Simulador oficial de taxas do site do Ton. Visa e Mastercard publicam o mesmo '
            .'percentual, assim como Elo e Amex - dai a tabela caber nos dois grupos existentes.',
        );

        $planos = [];

        foreach ($this->planos() as $slug => $dados) {
            $planos[$slug] = $this->plano($marca, $dados['nome'], [
                'tipo_enquadramento' => $dados['enquadramento'] ?? TipoEnquadramento::Automatico,
                'faturamento_min' => $dados['min'],
                'faturamento_max' => $dados['max'],
                'compromisso' => $dados['compromisso'] ?? null,
                'promocional_dias' => $dados['promocional_dias'] ?? null,
                'promocional_valor_processado' => $dados['promocional_valor_processado'] ?? null,
                // Etapa 17, dito pelo Everton: o Ton nao cobra mensalidade -
                // nao e campo nao lido (etapa 04), e zero confirmado.
                'mensalidade' => 0,
                'status' => StatusItem::Ativo,
                'ordem' => $dados['ordem'],
            ]);
        }

        foreach ($this->tabela() as $slug => $porPrazo) {
            foreach ($porPrazo as $prazo => $porGrupo) {
                foreach ($porGrupo as $grupo => [$debito, $credito]) {
                    $this->debito($planos[$slug], $grupo, $prazo, $debito, $fonte);
                    $this->serieDeCredito($planos[$slug], $grupo, $prazo, $credito, $fonte);
                }
            }
        }

        // Etapa 17, dito pelo Everton (dono do domínio): o Pix do Ton é
        // gratuito só quando o lojista cadastra a chave Pix no aplicativo -
        // sem isso, cobra 0,49%. Regra do Everton para o par real/condição:
        // o número que aparece na tela é o real (0,49%), e o 0% condicional
        // vai na nota, colada nele (campo `condicao`, nunca `observacao`).
        // Vale para todas as faixas, igual à leitura da InfinitePay na etapa 05.
        $fontePix = $this->fonte(
            self::URL,
            condicao: 'Grátis (0%) quando o lojista cadastra a chave Pix no aplicativo Ton.',
            dataVerificacao: '2026-09-15',
        );

        foreach ($planos as $plano) {
            $this->pix($plano, PrazoRecebimento::NA_HORA, 0.49, $fontePix);
        }

        $this->equipamentos($marca->getKey(), $planos);
    }

    private function planos(): array
    {
        return [
            // Etapa 05: entrou na etapa 04 como enquadramento automatico de
            // R$ 2 mil a R$ 5 mil, mas esses R$ 5 mil nunca foram faixa de
            // faturamento - sao o teto de volume processado da propria
            // promocao. Gravados assim, o motor ranqueava a tabela de entrada
            // como se fosse preco permanente, e ela ganhava de todo mundo.
            //
            // Os 30 dias ja estavam escritos no compromisso da carga original.
            // O piso de R$ 2 mil foi descartado: ele nao tem contrapartida nos
            // termos da promocao e provavelmente era leitura da faixa vizinha.
            // Confirmar no site antes de publicar.
            'periodo-promocional' => [
                'nome' => 'Período Promocional', 'min' => null, 'max' => null, 'ordem' => 0,
                'enquadramento' => TipoEnquadramento::Promocional,
                'promocional_dias' => 30,
                'promocional_valor_processado' => 5000,
                'compromisso' => 'Tabela promocional de entrada. Vale por 30 dias ou até R$ 5.000,00 '
                    .'processados na maquininha, o que vier antes. Depois dela o lojista passa a ser '
                    .'enquadrado pela faixa de faturamento do mês anterior.',
            ],
            'ate-r-3-mil' => ['nome' => 'Até R$ 3 mil', 'min' => null, 'max' => 2999.99, 'ordem' => 1],
            'de-r-3-mil-a-r-6-mil' => ['nome' => 'De R$ 3 mil a R$ 6 mil', 'min' => 3000, 'max' => 5999.99, 'ordem' => 2],
            'de-r-6-mil-a-r-10-mil' => ['nome' => 'De R$ 6 mil a R$ 10 mil', 'min' => 6000, 'max' => 9999.99, 'ordem' => 3],
            'de-r-10-mil-a-r-30-mil' => ['nome' => 'De R$ 10 mil a R$ 30 mil', 'min' => 10000, 'max' => 29999.99, 'ordem' => 4],
            'acima-de-r-30-mil' => ['nome' => 'Acima de R$ 30 mil', 'min' => 30000, 'max' => null, 'ordem' => 5],
        ];
    }

    /**
     * [plano][prazo][grupo] => [debito, [1x, 2x, ... 21x]].
     */
    private function tabela(): array
    {
        $vm = GrupoBandeira::VISA_MASTER;
        $demais = GrupoBandeira::DEMAIS;
        $naHora = PrazoRecebimento::NA_HORA;
        $d1 = PrazoRecebimento::D1;

        return [
            'periodo-promocional' => [
                // O plano promocional publica o mesmo percentual nos dois prazos.
                $d1 => [
                    $vm => [0.57, [0.57, 3.97, 3.97, 4.97, 5.97, 6.97, 7.97, 7.97, 7.97, 7.97, 7.97, 7.97, 14.87, 14.87, 14.87, 14.87, 14.87, 14.87, 14.87, 14.87, 14.87]],
                    $demais => [2.57, [4.34, 7.02, 7.58, 8.38, 9.38, 10.38, 10.98, 11.38, 12.38, 12.88, 13.74, 13.78, 14.87, 15.51, 16.15, 16.79, 17.43, 18.07, 18.71, 19.35, 19.99]],
                ],
                $naHora => [
                    $vm => [0.57, [0.57, 3.97, 3.97, 4.97, 5.97, 6.97, 7.97, 7.97, 7.97, 7.97, 7.97, 7.97, 14.87, 14.87, 14.87, 14.87, 14.87, 14.87, 14.87, 14.87, 14.87]],
                    $demais => [2.57, [4.34, 7.02, 7.58, 8.38, 9.38, 10.38, 10.98, 11.38, 12.38, 12.88, 13.74, 13.78, 14.87, 15.51, 16.15, 16.79, 17.43, 18.07, 18.71, 19.35, 19.99]],
                ],
            ],
            'ate-r-3-mil' => [
                $d1 => [
                    $vm => [1.69, [3.86, 9.86, 11.24, 12.59, 13.92, 15.22, 16.50, 17.76, 18.99, 20.19, 20.39, 20.39, 21.03, 21.67, 22.31, 22.95, 23.59, 24.23, 24.87, 25.51, 26.15]],
                    $demais => [2.98, [5.15, 11.30, 12.68, 14.03, 15.36, 16.66, 17.94, 19.20, 20.43, 21.78, 22.64, 22.68, 23.32, 23.96, 24.60, 25.24, 25.88, 26.52, 27.16, 27.80, 28.44]],
                ],
                $naHora => [
                    $vm => [1.98, [4.86, 10.86, 12.24, 13.59, 14.92, 16.22, 17.50, 18.76, 19.99, 21.19, 21.39, 21.39, 22.03, 22.67, 23.31, 23.95, 24.59, 25.23, 25.87, 26.51, 27.15]],
                    $demais => [3.27, [6.15, 12.30, 13.68, 15.03, 16.36, 17.66, 18.94, 20.20, 21.43, 22.78, 23.64, 23.68, 24.32, 24.96, 25.60, 26.24, 26.88, 27.52, 28.16, 28.80, 29.44]],
                ],
            ],
            'de-r-3-mil-a-r-6-mil' => [
                $d1 => [
                    $vm => [1.39, [3.34, 7.29, 8.35, 9.23, 10.10, 10.85, 10.90, 10.95, 11.00, 11.05, 11.73, 12.38, 13.02, 13.66, 14.30, 14.94, 15.58, 16.22, 16.86, 17.50, 18.14]],
                    $demais => [2.68, [4.63, 8.73, 9.79, 10.67, 11.54, 12.29, 12.34, 12.39, 12.44, 12.64, 13.98, 14.67, 15.31, 15.95, 16.59, 17.23, 17.87, 18.51, 19.15, 19.79, 20.43]],
                ],
                $naHora => [
                    $vm => [1.43, [3.36, 7.38, 8.97, 9.63, 10.50, 11.18, 12.19, 13.04, 13.07, 13.12, 13.17, 13.22, 13.86, 14.50, 15.14, 15.78, 16.42, 17.06, 17.70, 18.34, 18.98]],
                    $demais => [2.72, [4.65, 8.82, 10.41, 11.07, 11.94, 12.62, 13.63, 14.48, 14.51, 14.71, 15.42, 15.51, 16.15, 16.79, 17.43, 18.07, 18.71, 19.35, 19.99, 20.63, 21.27]],
                ],
            ],
            'de-r-6-mil-a-r-10-mil' => [
                $d1 => [
                    $vm => [1.32, [3.25, 6.69, 7.76, 8.64, 9.51, 10.37, 10.87, 10.92, 10.97, 11.02, 11.70, 12.35, 12.99, 13.63, 14.27, 14.91, 15.55, 16.19, 16.83, 17.47, 18.11]],
                    $demais => [2.61, [4.54, 8.13, 9.20, 10.08, 10.95, 11.81, 12.31, 12.36, 12.41, 12.61, 13.95, 14.64, 15.28, 15.92, 16.56, 17.20, 17.84, 18.48, 19.12, 19.76, 20.40]],
                ],
                $naHora => [
                    $vm => [1.34, [3.31, 7.18, 8.56, 9.44, 10.31, 11.17, 12.00, 12.50, 12.55, 12.58, 12.61, 12.66, 13.30, 13.94, 14.58, 15.22, 15.86, 16.50, 17.14, 17.78, 18.42]],
                    $demais => [2.63, [4.60, 8.62, 10.00, 10.88, 11.75, 12.61, 13.44, 13.94, 13.99, 14.17, 14.86, 14.95, 15.59, 16.23, 16.87, 17.51, 18.15, 18.79, 19.43, 20.07, 20.71]],
                ],
            ],
            'de-r-10-mil-a-r-30-mil' => [
                $d1 => [
                    $vm => [1.22, [3.02, 5.38, 6.11, 7.84, 8.56, 9.27, 9.98, 10.68, 10.94, 10.99, 11.67, 11.73, 12.37, 13.01, 13.65, 14.29, 14.93, 15.57, 16.21, 16.85, 17.49]],
                    $demais => [2.51, [4.31, 6.82, 7.55, 9.28, 10.00, 10.71, 11.42, 12.12, 12.38, 12.58, 13.92, 14.02, 14.66, 15.30, 15.94, 16.58, 17.22, 17.86, 18.50, 19.14, 19.78]],
                ],
                $naHora => [
                    $vm => [1.25, [3.05, 6.59, 8.19, 8.89, 9.76, 11.10, 11.68, 11.73, 11.78, 11.83, 11.88, 11.95, 12.59, 13.23, 13.87, 14.51, 15.15, 15.79, 16.43, 17.07, 17.71]],
                    $demais => [2.54, [4.34, 8.03, 9.63, 10.33, 11.20, 12.54, 13.12, 13.17, 13.22, 13.42, 14.13, 14.24, 14.88, 15.52, 16.16, 16.80, 17.44, 18.08, 18.72, 19.36, 20.00]],
                ],
            ],
            'acima-de-r-30-mil' => [
                $d1 => [
                    $vm => [1.19, [2.85, 5.33, 6.06, 7.79, 8.51, 9.22, 9.93, 10.63, 10.91, 10.96, 11.46, 11.51, 12.15, 12.79, 13.43, 14.07, 14.71, 15.35, 15.99, 16.63, 17.27]],
                    $demais => [2.48, [4.14, 6.77, 7.50, 9.23, 9.95, 10.66, 11.37, 12.07, 12.35, 12.55, 13.71, 13.80, 14.44, 15.08, 15.72, 16.36, 17.00, 17.64, 18.28, 18.92, 19.56]],
                ],
                $naHora => [
                    $vm => [1.22, [2.91, 6.54, 8.14, 8.84, 9.71, 10.79, 10.84, 10.89, 10.94, 10.99, 11.63, 11.73, 12.37, 13.01, 13.65, 14.29, 14.93, 15.57, 16.21, 16.85, 17.49]],
                    $demais => [2.51, [4.20, 7.98, 9.58, 10.28, 11.15, 12.23, 12.28, 12.33, 12.38, 12.58, 13.88, 14.02, 14.66, 15.30, 15.94, 16.58, 17.22, 17.86, 18.50, 19.14, 19.78]],
                ],
            ],
        ];
    }

    /**
     * O preco de adesao do Ton nao varia por plano, mas a adesao pertence ao
     * par equipamento+plano de qualquer forma - entao a mesma linha e repetida
     * para cada plano, e nao promovida a coluna do equipamento.
     *
     * @param  array<string, Plano>  $planos
     */
    private function equipamentos(int $marcaId, array $planos): void
    {
        $aparelhos = [
            ['T1', TipoEquipamento::PinPad, 'A maquininha compacta que conecta com o seu celular.',
                ['chip' => true, 'imprime' => false, 'nfc' => true, 'celular' => true], 56.57, 16.80, 0],
            ['T2', TipoEquipamento::Pos, 'A maquininha compacta, com chip 3G e Wi-Fi. Comprovante por SMS.',
                ['chip' => true, 'imprime' => false, 'nfc' => true, 'celular' => false], 176.00, 49.88, 1],
            ['T3', TipoEquipamento::Pos, 'Maquininha com comprovante impresso, chip 3G e Wi-Fi.',
                ['chip' => true, 'imprime' => true, 'nfc' => true, 'celular' => false], 391.92, 108.00, 2],
            ['T3 Smart', TipoEquipamento::Smart, 'Maquininha Android com visor touchscreen, chip 4G e Wi-Fi.',
                ['chip' => true, 'imprime' => true, 'nfc' => true, 'celular' => false], 671.43, 191.88, 3],
        ];

        foreach ($aparelhos as [$nome, $tipo, $descricao, $flags, $adesao, $promocional, $ordem]) {
            $equipamento = Equipamento::updateOrCreate(
                ['marca_id' => $marcaId, 'slug' => Str::slug($nome)],
                [
                    'nome' => $nome,
                    'tipo' => $tipo,
                    'descricao' => $descricao,
                    'tem_chip_gratis' => $flags['chip'],
                    'imprime_comprovante' => $flags['imprime'],
                    'aceita_nfc' => $flags['nfc'],
                    'exige_celular' => $flags['celular'],
                    'status' => StatusItem::Ativo,
                    'ordem' => $ordem,
                ],
            );

            foreach ($planos as $plano) {
                $equipamento->planos()->syncWithoutDetaching([
                    $plano->getKey() => [
                        'preco_adesao' => $adesao,
                        'preco_adesao_promocional' => $promocional,
                        'aluguel_mensal' => null,
                        // Etapa 17, dito pelo Everton: a adesao parcela em 12x
                        // sem juros sobre o preco a vista, em todas as marcas.
                        'parcelas_adesao' => 12,
                        'observacao' => 'Sem aluguel: o aparelho e comprado. Preco promocional vigente '
                            .'no site em 08/09/2026, com frete gratis.',
                        'status' => StatusItem::Ativo->value,
                    ],
                ]);
            }
        }
    }
}
