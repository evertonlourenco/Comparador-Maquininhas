<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Models\GrupoBandeira;
use App\Models\PrazoRecebimento;

/**
 * FacilityPay, lida em 15/09/2026 (etapa 17) em facilitypay.com.br/planos.
 *
 * Leitura PARCIAL: a página tem três planos (Profit, Express, Light) atrás
 * de abas, e só o Express (o que carrega por padrão) veio no scrape - os
 * cliques nas outras duas abas não mudaram o conteúdo capturado, então
 * Profit e Light ficam pendentes de uma leitura feita de outro jeito
 * (talvez precise de navegador real, não fetch simples).
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

        $fonte = $this->fonte(
            self::URL,
            'Leitura parcial: só o plano Express (padrão da página) veio no scrape - Profit e Light '
            .'estão atrás de abas que não mudaram o conteúdo capturado.',
            dataVerificacao: '2026-09-15',
        );

        $express = $this->plano($marca, 'Express', [
            'tipo_enquadramento' => TipoEnquadramento::Escolhido,
            'compromisso' => 'Recebimento na hora (D+0). Um dos três planos da marca - Profit e '
                .'Light ainda não foram lidos.',
            'status' => StatusItem::Ativo,
            'ordem' => 0,
        ]);

        $vm = GrupoBandeira::VISA_MASTER;
        $demais = GrupoBandeira::DEMAIS;
        $naHora = PrazoRecebimento::NA_HORA;

        $this->debito($express, $vm, $naHora, 1.45, $fonte);
        $this->serieDeCredito($express, $vm, $naHora, [2.97, 4.59, 5.29, 6.01, 6.70, 7.39, 8.13, 8.80, 9.47, 10.14, 10.80, 11.45, 12.09, 12.73, 13.37, 14.00, 14.62, 15.23], $fonte);

        $this->debito($express, $demais, $naHora, 1.51, $fonte);
        $this->serieDeCredito($express, $demais, $naHora, [3.30, 4.74, 5.44, 6.16, 6.85, 7.54, 8.33, 9.00, 9.67, 10.34, 11.00, 11.65, 12.29, 12.93, 13.57, 14.20, 14.82, 15.43], $fonte);

        $this->pix($express, $naHora, 0.50, $fonte);
    }
}
