<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Models\GrupoBandeira;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;

/**
 * SidePay, lida em 15/09/2026 (etapa 17) em sidepay.com.br/planos-taxas.
 * Completada com screenshots do Everton no mesmo dia, cobrindo o toggle
 * "Receba na hora" que a primeira leitura (scrape de texto) não capturou.
 *
 * Correção sobre a primeira leitura desta mesma etapa: a SidePay tem só
 * DOIS grupos de bandeira, não três - a segunda coluna da tabela dela é
 * "Elo + Outros" (rótulo literal na tela), não "Elo" isolada como a Yelly
 * publica. A primeira leitura tinha gravado no grupo `elo`, o que mostraria
 * ao lojista "essa taxa vale só pra Elo" quando na verdade vale pra Elo E
 * qualquer outra bandeira fora Visa/Master - achado pelo Everton batendo o
 * olho no formulário do admin. Por isso os dois planos entram gravados do
 * zero: qualquer taxa antiga gravada sob o grupo errado (`elo`) é apagada
 * antes.
 *
 * Mesma adquirente da Yelly e da FacilityPay (o PagSeguro/PagBank, dito
 * pelo Everton) - os números do plano D+1 batem idênticos aos da Yelly
 * Premium, e os do D+0 batem idênticos aos da Yelly Flash, nos dois grupos.
 */
class SidePaySeeder extends SeederDeMarca
{
    private const URL = 'https://sidepay.com.br/planos-taxas/';

    public function run(): void
    {
        $marca = $this->marca('sidepay');

        // Limpa qualquer taxa gravada na primeira leitura desta etapa, que
        // usou o grupo `elo` errado para a segunda coluna (ver comentário
        // da classe) - evita deixar lixo de taxa órfã sob o grupo errado.
        TaxaDivulgada::query()
            ->whereHas('plano', fn ($q) => $q->where('marca_id', $marca->getKey()))
            ->delete();

        $fonte = $this->fonte(
            self::URL,
            'Toggle "Receba em 1 dia" lido por scrape de texto; toggle "Receba na hora" completado '
            .'por captura de tela enviada pelo Everton, ambos em 15/09/2026.',
            dataVerificacao: '2026-09-15',
        );

        $emUmDia = $this->plano($marca, 'Receba em 1 dia', [
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'compromisso' => 'Recebimento em 1 dia útil (D+1).',
            'status' => StatusItem::Ativo,
            'ordem' => 0,
        ]);

        $naHoraPlano = $this->plano($marca, 'Receba na hora', [
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'compromisso' => 'Recebimento na hora (D+0).',
            'status' => StatusItem::Ativo,
            'ordem' => 1,
        ]);

        $vm = GrupoBandeira::VISA_MASTER;
        $demais = GrupoBandeira::DEMAIS;
        $d1 = PrazoRecebimento::D1;
        $naHora = PrazoRecebimento::NA_HORA;

        // Receba em 1 dia (D+1). Pix cai na hora sempre (nao muda com o
        // plano - mesmo ajuste feito no YellySeeder nesta etapa).
        $this->debito($emUmDia, $vm, $d1, 1.05, $fonte);
        $this->serieDeCredito($emUmDia, $vm, $d1, [3.05, 4.31, 5.01, 5.71, 6.39, 7.08, 7.75, 8.42, 9.08, 9.74, 10.39, 11.04, 11.68, 12.30, 12.93, 13.55, 14.17, 14.78], $fonte);
        $this->pix($emUmDia, PrazoRecebimento::NA_HORA, 0.46, $fonte);

        $this->debito($emUmDia, $demais, $d1, 1.21, $fonte);
        $this->serieDeCredito($emUmDia, $demais, $d1, [3.41, 4.31, 5.01, 5.71, 6.39, 7.08, 8.05, 8.72, 9.38, 10.04, 10.69, 11.34, 11.98, 12.60, 13.23, 13.85, 14.47, 15.08], $fonte);

        // Receba na hora (D+0).
        $this->debito($naHoraPlano, $vm, $naHora, 1.45, $fonte);
        $this->serieDeCredito($naHoraPlano, $vm, $naHora, [3.21, 5.06, 5.72, 6.39, 7.05, 7.70, 8.90, 9.54, 10.17, 10.80, 11.42, 12.04, 12.65, 13.26, 13.86, 14.45, 15.04, 15.62], $fonte);
        $this->pix($naHoraPlano, PrazoRecebimento::NA_HORA, 0.56, $fonte);

        $this->debito($naHoraPlano, $demais, $naHora, 1.51, $fonte);
        $this->serieDeCredito($naHoraPlano, $demais, $naHora, [3.50, 5.21, 5.87, 6.54, 7.20, 7.85, 9.20, 9.84, 10.47, 11.10, 11.72, 12.34, 12.95, 13.56, 14.16, 14.75, 15.34, 15.92], $fonte);
    }
}
