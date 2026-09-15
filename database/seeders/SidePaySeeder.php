<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Models\PrazoRecebimento;

/**
 * SidePay, lida em 15/09/2026 (etapa 17) em sidepay.com.br/planos-taxas.
 *
 * Leitura PARCIAL, de propósito - regra 6 não admite completar o resto por
 * semelhança, mesmo sendo forte a evidência de que é a mesma tabela da
 * Yelly (mesma adquirente, PagSeguro, confirmada pelo Everton): os números
 * capturados aqui batem idênticos aos do plano Premium (D+1) da Yelly, nos
 * dois grupos de bandeira que a página abriu de cara.
 *
 * O que falta, e por quê:
 * - A página tem toggle "Receba em 1 dia" / "Receba na hora" - só o de 1 dia
 *   veio no scrape (o de "na hora" provavelmente espelha o Flash da Yelly,
 *   mas isso é suposição, não leitura).
 * - Só 2 tabelas apareceram (Visa/Master e Elo). A Yelly tem uma terceira
 *   ("demais bandeiras") - pode existir aqui também, atrás de outro toggle
 *   não encontrado.
 * - Pix: a Yelly publica 0,46% em D+1; a SidePay não mostrou linha de Pix
 *   no scrape.
 */
class SidePaySeeder extends SeederDeMarca
{
    private const URL = 'https://sidepay.com.br/planos-taxas/';

    public function run(): void
    {
        $marca = $this->marca('sidepay');

        $fonte = $this->fonte(
            self::URL,
            'Leitura parcial - ver comentário da classe. Só o toggle "Receba em 1 dia" e dois dos '
            .'prováveis três grupos de bandeira apareceram no scrape.',
            dataVerificacao: '2026-09-15',
        );

        $plano = $this->plano($marca, 'Padrão', [
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'compromisso' => 'Recebimento em 1 dia útil (D+1). A página também oferece "receba na '
                .'hora", não capturado nesta leitura.',
            'status' => StatusItem::Ativo,
            'ordem' => 0,
        ]);

        $d1 = PrazoRecebimento::D1;

        $this->debito($plano, 'visa_master', $d1, 1.05, $fonte);
        $this->serieDeCredito($plano, 'visa_master', $d1, [3.05, 4.31, 5.01, 5.71, 6.39, 7.08, 7.75, 8.42, 9.08, 9.74, 10.39, 11.04, 11.68, 12.30, 12.93, 13.55, 14.17, 14.78], $fonte);

        $this->debito($plano, 'elo', $d1, 1.21, $fonte);
        $this->serieDeCredito($plano, 'elo', $d1, [3.41, 4.31, 5.01, 5.71, 6.39, 7.08, 8.05, 8.72, 9.38, 10.04, 10.69, 11.34, 11.98, 12.60, 13.23, 13.85, 14.47, 15.08], $fonte);
    }
}
