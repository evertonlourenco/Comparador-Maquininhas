<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Models\PrazoRecebimento;

/**
 * Yelly, lida em 15/09/2026 (etapa 17) em yelly.com.br/taxas. A tabela
 * completa (dois planos, tres grupos de bandeira, 1x a 18x + Pix) está
 * aberta na página pública - marca com a leitura mais completa das quatro
 * novas desta etapa.
 *
 * Mesma adquirente da SidePay e da FacilityPay (o PagSeguro/PagBank, dito
 * pelo Everton) - e os números batem: Premium (Ton, D+1) é idêntico ao que a
 * SidePay publica, até a segunda casa decimal, nos dois grupos que a SidePay
 * deixou abertos.
 */
class YellySeeder extends SeederDeMarca
{
    private const URL = 'https://www.yelly.com.br/taxas';

    public function run(): void
    {
        $marca = $this->marca('yelly');

        $fonte = $this->fonte(
            self::URL,
            dataVerificacao: '2026-09-15',
        );

        $flash = $this->plano($marca, 'Flash', [
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'compromisso' => 'Recebimento na hora (D+0). Máquinas mais baratas.',
            'status' => StatusItem::Ativo,
            'ordem' => 0,
        ]);

        $premium = $this->plano($marca, 'Premium', [
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'compromisso' => 'Recebimento em 1 dia útil (D+1). Taxas menores que o Flash.',
            'status' => StatusItem::Ativo,
            'ordem' => 1,
        ]);

        foreach ($this->tabela() as [$plano, $prazo, $grupo, $debito, $credito, $pix]) {
            $planoModel = $plano === 'flash' ? $flash : $premium;

            $this->debito($planoModel, $grupo, $prazo, $debito, $fonte);
            $this->serieDeCredito($planoModel, $grupo, $prazo, $credito, $fonte);

            // O Pix da Yelly aparece na tabela de cada plano (0,56% no Flash,
            // 0,46% no Premium) - mas o dinheiro do Pix cai na hora sempre,
            // isso nao muda com o plano. A dimensao que varia aqui e o plano,
            // nao o prazo (por isso NA_HORA fixo, nao o $prazo da linha, que
            // seria D+1 nas linhas do Premium e daria errado).
            $this->pix($planoModel, PrazoRecebimento::NA_HORA, $pix, $fonte);
        }
    }

    /**
     * [plano, prazo, grupo, débito, [1x..18x], pix].
     */
    private function tabela(): array
    {
        $naHora = PrazoRecebimento::NA_HORA;
        $d1 = PrazoRecebimento::D1;

        return [
            ['flash', $naHora, 'visa_master', 1.45, [3.21, 5.06, 5.72, 6.39, 7.05, 7.70, 8.90, 9.54, 10.17, 10.80, 11.42, 12.04, 12.65, 13.26, 13.86, 14.45, 15.04, 15.62], 0.56],
            ['flash', $naHora, 'elo', 1.51, [3.50, 5.21, 5.87, 6.54, 7.20, 7.85, 9.20, 9.84, 10.47, 11.10, 11.72, 12.34, 12.95, 13.56, 14.16, 14.75, 15.34, 15.92], 0.56],
            ['flash', $naHora, 'demais', 1.66, [3.50, 5.21, 5.87, 6.54, 7.20, 7.85, 9.20, 9.84, 10.47, 11.10, 11.72, 12.34, 12.95, 13.56, 14.16, 14.75, 15.34, 15.92], 0.56],
            ['premium', $d1, 'visa_master', 1.05, [3.05, 4.31, 5.01, 5.71, 6.39, 7.08, 7.75, 8.42, 9.08, 9.74, 10.39, 11.04, 11.68, 12.30, 12.93, 13.55, 14.17, 14.78], 0.46],
            ['premium', $d1, 'elo', 1.21, [3.41, 4.31, 5.01, 5.71, 6.39, 7.08, 8.05, 8.72, 9.38, 10.04, 10.69, 11.34, 11.98, 12.60, 13.23, 13.85, 14.47, 15.08], 0.46],
            ['premium', $d1, 'demais', 1.95, [3.85, 4.31, 5.01, 5.71, 6.39, 7.08, 8.05, 8.72, 9.38, 10.04, 10.69, 11.34, 11.98, 12.60, 13.23, 13.85, 14.47, 15.08], 0.46],
        ];
    }
}
