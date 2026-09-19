<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoEquipamento;
use App\Models\Equipamento;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Support\Uploads\ImagemSeguraWebp;
use Illuminate\Support\Str;

/**
 * FacilityPay, lida em 15/09/2026 (etapa 17) em facilitypay.com.br/planos.
 * Completada no mesmo dia com screenshots do Everton, cobrindo Profit e
 * Light - as abas que o scrape de texto não conseguia trocar.
 *
 * Cada um dos três planos vem com um prazo de recebimento fixo, não um
 * toggle: Profit e Light mostram "Receba um dia depois" (D+1), Express
 * mostra "Receba na hora" (D+0) - é o que os cartões da própria página
 * exibem por plano, não uma escolha do lojista dentro de um mesmo plano.
 *
 * Profit (D+1) bate número por número com Yelly Premium e SidePay "Receba
 * em 1 dia" - mais uma confirmação de que as três compartilham o PagSeguro/
 * PagBank como adquirente.
 *
 * Diferente de Yelly/SidePay: aqui só duas tabelas (Visa/Master e "demais
 * bandeiras", sem Elo separada) mais um Pix único - por isso usa o grupo
 * DEMAIS já existente, não o grupo novo "elo".
 */
class FacilityPaySeeder extends SeederDeMarca
{
    private const URL = 'https://facilitypay.com.br/planos';

    public function run(): void
    {
        $marca = $this->marca('facilitypay');
        $vm = GrupoBandeira::VISA_MASTER;
        $demais = GrupoBandeira::DEMAIS;

        $fonteExpress = $this->fonte(
            self::URL,
            'Plano Express (padrão da página), lido por scrape de texto.',
            dataVerificacao: '2026-09-15',
        );

        $fonteScreenshots = $this->fonte(
            self::URL,
            'Planos Profit e Light, completados por captura de tela enviada pelo Everton - o scrape '
            .'de texto não trocava de aba.',
            dataVerificacao: '2026-09-15',
        );

        $express = $this->plano($marca, 'Express', [
            'tipo_enquadramento' => TipoEnquadramento::Escolhido,
            'compromisso' => 'Recebimento na hora (D+0).',
            'status' => StatusItem::Ativo,
            'ordem' => 0,
        ]);

        $profit = $this->plano($marca, 'Profit', [
            'tipo_enquadramento' => TipoEnquadramento::Escolhido,
            'compromisso' => 'Recebimento em 1 dia útil (D+1). Taxas mais baixas que o Express.',
            'status' => StatusItem::Ativo,
            'ordem' => 1,
        ]);

        $light = $this->plano($marca, 'Light', [
            'tipo_enquadramento' => TipoEnquadramento::Escolhido,
            'compromisso' => 'Recebimento em 1 dia útil (D+1). Taxas mais altas que o Profit - provável '
                .'plano de entrada, sem compromisso de volume.',
            'status' => StatusItem::Ativo,
            'ordem' => 2,
        ]);

        $naHora = PrazoRecebimento::NA_HORA;
        $d1 = PrazoRecebimento::D1;

        // Express (D+0).
        $this->debito($express, $vm, $naHora, 1.45, $fonteExpress);
        $this->serieDeCredito($express, $vm, $naHora, [2.97, 4.59, 5.29, 6.01, 6.70, 7.39, 8.13, 8.80, 9.47, 10.14, 10.80, 11.45, 12.09, 12.73, 13.37, 14.00, 14.62, 15.23], $fonteExpress);
        $this->debito($express, $demais, $naHora, 1.51, $fonteExpress);
        $this->serieDeCredito($express, $demais, $naHora, [3.30, 4.74, 5.44, 6.16, 6.85, 7.54, 8.33, 9.00, 9.67, 10.34, 11.00, 11.65, 12.29, 12.93, 13.57, 14.20, 14.82, 15.43], $fonteExpress);
        $this->pix($express, $naHora, 0.50, $fonteExpress);

        // Profit (D+1).
        $this->debito($profit, $vm, $d1, 1.05, $fonteScreenshots);
        $this->serieDeCredito($profit, $vm, $d1, [3.05, 4.31, 5.01, 5.71, 6.39, 7.08, 7.75, 8.42, 9.08, 9.74, 10.39, 11.04, 11.68, 12.30, 12.93, 13.55, 14.17, 14.78], $fonteScreenshots);
        $this->debito($profit, $demais, $d1, 1.21, $fonteScreenshots);
        $this->serieDeCredito($profit, $demais, $d1, [3.41, 4.31, 5.01, 5.71, 6.39, 7.08, 8.05, 8.72, 9.38, 10.04, 10.69, 11.34, 11.98, 12.60, 13.23, 13.85, 14.47, 15.08], $fonteScreenshots);
        $this->pix($profit, $naHora, 0.50, $fonteScreenshots);

        // Light (D+1).
        $this->debito($light, $vm, $d1, 1.55, $fonteScreenshots);
        $this->serieDeCredito($light, $vm, $d1, [3.80, 5.21, 6.08, 6.94, 7.80, 8.65, 9.63, 10.46, 11.27, 12.08, 12.88, 13.66, 14.44, 15.20, 15.97, 16.71, 17.45, 18.19], $fonteScreenshots);
        $this->debito($light, $demais, $d1, 2.05, $fonteScreenshots);
        $this->serieDeCredito($light, $demais, $d1, [4.80, 5.76, 6.63, 7.49, 8.35, 9.20, 10.48, 11.31, 12.12, 12.93, 13.73, 14.51, 15.29, 16.05, 16.82, 17.56, 18.30, 19.04], $fonteScreenshots);
        $this->pix($light, $naHora, 0.50, $fonteScreenshots);

        $this->equipamentos($marca, $express, $profit, $light);
    }

    /**
     * Etapa 17, sessão de 16/09/2026: catálogo lido em
     * facilitypay.com.br/maquininhas. As fotos são as mesmas que o próprio
     * site usa (baixadas de lá, convertidas para WebP aqui como qualquer
     * upload) — o Everton mandou capturas de tela da Facility Pro, e batendo
     * com o site percebi que o mesmo endereço tem o PNG original das três
     * máquinas, sem marca d'água, em resolução melhor que a captura.
     *
     * A FacilityPay usa aparelho fornecido pela PagBank por trás (junto de
     * SidePay e Yelly, dito pelo Everton) - mas o catálogo aqui é o nome e a
     * ficha técnica que a própria FacilityPay publica, não o nome PagBank
     * correspondente.
     *
     * **Preço de adesão, corrigido em 16/09/2026** com
     * `facilitypay.com.br/maquininhas` - a página tem uma aba por plano
     * (Profit/Express/Light) e cada aba muda o preço do aparelho, não só a
     * taxa. Primeira leitura (mesmo dia) só capturou a aba Express, porque
     * era a aba ativa por padrão e o scrape de texto não trocava aba
     * sozinho - o Everton conferiu as três abas manualmente e mandou os
     * nove valores. `preco_adesao` (o preço "cheio", riscado no site) só
     * foi verificado no contexto da aba Express (R$ 359,90/649,90/749,90);
     * sem dado de um "cheio" diferente por aba, o mesmo valor foi mantido
     * nos três planos - romper essa suposição pede nova verificação.
     *
     * Preço "cheio" mais alto, adesão mais barata no Profit (o plano de
     * taxa mais baixa) e mais cara no Light (taxa mais alta) é o trade-off
     * real: adesão mais cara se paga com taxa melhor no longo prazo. É
     * exatamente esse tipo de conta que o motor de cálculo já resolve
     * (custo inicial vs. custo mensal recorrente, etapa 05) - por isso a
     * decisão com o Everton foi manter os três planos com preço próprio,
     * não simplificar para um preço único "da parceria".
     *
     * **Resolvido em 19/09/2026 (`preco_adesao_no_link`):** o Everton mandou
     * a página do link (Facility Mini, planos D1PLUS e EXPRESS, os dois a
     * R$ 55,50) e pediu que a adesão mostrada seja SEMPRE a do link. O preço
     * do link é o valor final, não "site menos 10%" - Mini 55,50 (confirmado
     * na captura), Pro 119,90 e Smart 221,90 (da leitura de 16/09, não
     * reconferidos por plano). O parágrafo abaixo é o histórico da dúvida.
     *
     * **O link de indicação do Everton
     * (`https://app.facilitypay.com.br/indicacao/EVERTON10`) mostrou um
     * terceiro preço, ainda mais baixo** (R$ 55,50/119,90/221,90, sob os
     * rótulos "D1PLUS"/"EXPRESS" - nenhum dos dois nome de plano do
     * catálogo) - não usado aqui porque não bate com nenhuma das colunas
     * Profit/Express/Light confirmadas depois, e continua sem mapeamento
     * claro para um plano do catálogo. Isso não é preço cheio menos os 10%
     * do cupom `EVERTON10`: é bem mais barato do que 10% explicariam. A
     * pendência sobre `EconomiaDoCupom` (que aplica o percentual do cupom
     * sobre `preco_adesao`/`preco_adesao_promocional` e por isso
     * **subestima** a economia real de usar o link do Everton) continua
     * registrada no CLAUDE.md - não é bug desta carga, é decisão de
     * produto pendente.
     */
    private function equipamentos(Marca $marca, Plano $express, Plano $profit, Plano $light): void
    {
        $diretorioAssets = __DIR__.'/assets/facilitypay';

        $aparelhos = [
            [
                'nome' => 'Facility Mini',
                'tipo' => TipoEquipamento::PinPad,
                'descricao' => 'Prática, portátil e econômica - indicada principalmente para delivery. '
                    .'Não precisa de celular para vender.',
                'imprime' => false,
                'exigeCelular' => false,
                'arquivo' => 'facility-mini.png',
                'ordem' => 0,
                'precoCheio' => 359.90,
                'precoPorPlano' => ['express' => 104.90, 'profit' => 194.90, 'light' => 64.90],
                'precoNoLink' => 55.50,
            ],
            [
                'nome' => 'Facility Pro',
                'tipo' => TipoEquipamento::Pos,
                'descricao' => 'A mais vendida, com o melhor custo-benefício segundo a própria marca. '
                    .'Tela touchscreen e garantia vitalícia.',
                'imprime' => true,
                'exigeCelular' => false,
                'arquivo' => 'facility-pro.png',
                'ordem' => 1,
                'precoCheio' => 649.90,
                'precoPorPlano' => ['express' => 198.90, 'profit' => 299.90, 'light' => 119.90],
                'precoNoLink' => 119.90,
            ],
            [
                'nome' => 'Facility Smart',
                'tipo' => TipoEquipamento::Smart,
                'descricao' => 'A mais completa: sistema Android, tela touch, gestão de produtos e '
                    .'estoque, e acompanhamento de vendas em tempo real.',
                'imprime' => true,
                'exigeCelular' => false,
                'arquivo' => 'facility-smart.png',
                'ordem' => 2,
                'precoCheio' => 749.90,
                'precoPorPlano' => ['express' => 319.90, 'profit' => 469.90, 'light' => 209.90],
                'precoNoLink' => 221.90,
            ],
        ];

        $planos = ['express' => $express, 'profit' => $profit, 'light' => $light];

        foreach ($aparelhos as $dados) {
            $equipamento = $this->equipamento($marca, $dados['nome'], [
                'tipo' => $dados['tipo'],
                'descricao' => $dados['descricao'],
                'tem_chip_gratis' => true,
                'imprime_comprovante' => $dados['imprime'],
                'aceita_nfc' => true,
                'exige_celular' => $dados['exigeCelular'],
                'status' => StatusItem::Ativo,
                'ordem' => $dados['ordem'],
            ]);

            // Só grava a foto na criação - se o admin trocar depois pelo
            // painel (upload manual ou "buscar por URL"), o reseed não pode
            // reverter a escolha dele (mesmo espírito da regra do etapa 17
            // sobre nome/status de plano nunca serem sobrescritos).
            if ($equipamento->wasRecentlyCreated && $equipamento->imagem_path === null) {
                $caminho = ImagemSeguraWebp::salvar("{$diretorioAssets}/{$dados['arquivo']}", 'equipamentos');

                if ($caminho !== null) {
                    $equipamento->update(['imagem_path' => $caminho]);
                }
            }

            foreach ($planos as $codigoPlano => $plano) {
                $equipamento->planos()->syncWithoutDetaching([
                    $plano->getKey() => [
                        'preco_adesao' => $dados['precoCheio'],
                        'preco_adesao_promocional' => $dados['precoPorPlano'][$codigoPlano],
                        // 19/09/2026: a adesao mostrada e a do link do Everton.
                        'preco_adesao_no_link' => $dados['precoNoLink'],
                        'aluguel_mensal' => null,
                        // Etapa 17, dito pelo Everton: adesão parcela em 12x sem
                        // juros sobre o preço à vista, em todas as marcas.
                        'parcelas_adesao' => 12,
                        'observacao' => 'Preço da aba "'.ucfirst($codigoPlano).'" em '
                            .'facilitypay.com.br/maquininhas, verificado em 16/09/2026 pelo Everton - '
                            .'cada plano muda o preço do aparelho, não só a taxa. "Cheio" (preço riscado '
                            .'no site) só foi conferido no contexto da aba Express; sem dado de um cheio '
                            .'diferente por aba, o mesmo valor foi mantido nos três.',
                        'status' => StatusItem::Ativo->value,
                    ],
                ]);
            }
        }
    }

    /** @param  array<string, mixed>  $atributos */
    private function equipamento(Marca $marca, string $nome, array $atributos): Equipamento
    {
        return Equipamento::updateOrCreate(
            ['marca_id' => $marca->getKey(), 'slug' => Str::slug($nome)],
            ['nome' => $nome, ...$atributos],
        );
    }
}
