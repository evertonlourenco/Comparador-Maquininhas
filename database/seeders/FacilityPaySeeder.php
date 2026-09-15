<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Models\GrupoBandeira;
use App\Models\PrazoRecebimento;

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
    }
}
