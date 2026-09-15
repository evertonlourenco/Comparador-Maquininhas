<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoEquipamento;
use App\Enums\TipoOperacao;
use App\Models\Equipamento;
use App\Models\GrupoBandeira;
use App\Models\PrazoRecebimento;
use Illuminate\Support\Str;

/**
 * InfinitePay (CloudWalk), lida em 08/09/2026 de infinitepay.io/taxas.
 *
 * A marca publica quatro tabelas por faixa de faturamento e tres planos de
 * recebimento. Os tres viram prazo (regra 1), nao plano - o plano aqui e a
 * faixa de faturamento, que e o que muda o percentual de forma permanente.
 *
 * "Sem antecipacao" e o unico que mistura prazos dentro do mesmo plano, e o
 * proprio site explica por que: debito em 1 dia util e credito a partir de 30
 * dias. Como o parcelado sem antecipacao cai parcela a parcela, ele usa o
 * prazo parcela_a_parcela - que existe exatamente para este caso.
 */
class InfinitePaySeeder extends SeederDeMarca
{
    private const URL = 'https://www.infinitepay.io/taxas';

    public function run(): void
    {
        $marca = $this->marca('infinitepay');
        $vm = GrupoBandeira::VISA_MASTER;
        $demais = GrupoBandeira::DEMAIS;

        $fonte = $this->fonte(self::URL);

        $fonteNaHora = $this->fonte(
            self::URL,
            'No plano "Na hora" a InfinitePay publica um percentual unico para Visa, Mastercard, '
            .'Elo e Amex, e igual em todas as faixas de faturamento. A mesma linha e gravada nos '
            .'dois grupos de bandeira porque ela vale para os dois - nao por estimativa.',
        );

        $fonteSemAntecipacao = $this->fonte(
            self::URL,
            'Plano "sem antecipacao": o site informa debito em 1 dia util e credito a partir de 30 '
            .'dias. Por isso o credito a vista entra com prazo de 30 dias e o parcelado com prazo '
            .'parcela a parcela. O percentual do parcelado e unico de 2x a 12x, como publicado.',
        );

        $planos = [];

        foreach ($this->planos() as $slug => $dados) {
            $planos[$slug] = $this->plano($marca, $dados['nome'], [
                'tipo_enquadramento' => TipoEnquadramento::Automatico,
                'faturamento_min' => $dados['min'],
                'faturamento_max' => $dados['max'],
                // Publicado na propria pagina de taxas: sem aluguel, sem tarifa
                // de conta e Pix gratuito.
                'mensalidade' => 0,
                'tarifa_pix_recebimento' => 0,
                'tarifa_pix_envio' => 0,
                'taxa_antecipacao_mensal' => $dados['taxa_antecipacao'] ?? null,
                'compromisso' => 'Enquadramento automatico pelo faturamento do mes anterior. '
                    .'A marca declara nao praticar taxa promocional por tempo limitado.',
                'status' => StatusItem::Ativo,
                'ordem' => $dados['ordem'],
            ]);
        }

        // Plano de recebimento "Em 1 dia util".
        foreach ($this->emUmDiaUtil() as $slug => [$debitoVm, $creditoVm, $debitoDemais, $creditoDemais]) {
            $this->debito($planos[$slug], $vm, PrazoRecebimento::D1, $debitoVm, $fonte);
            $this->serieDeCredito($planos[$slug], $vm, PrazoRecebimento::D1, $creditoVm, $fonte);
            $this->debito($planos[$slug], $demais, PrazoRecebimento::D1, $debitoDemais, $fonte);
            $this->serieDeCredito($planos[$slug], $demais, PrazoRecebimento::D1, $creditoDemais, $fonte);
        }

        // Plano de recebimento "Na hora": tabela unica, igual para todas as
        // faixas de faturamento e para todas as bandeiras.
        $debitoNaHora = 2.29;
        $creditoNaHora = [5.49, 10.89, 11.99, 12.59, 13.29, 13.99, 14.99, 15.59, 16.19, 16.89, 17.89, 18.29];

        foreach ($planos as $plano) {
            foreach ([$vm, $demais] as $grupo) {
                $this->debito($plano, $grupo, PrazoRecebimento::NA_HORA, $debitoNaHora, $fonteNaHora);
                $this->serieDeCredito($plano, $grupo, PrazoRecebimento::NA_HORA, $creditoNaHora, $fonteNaHora);
            }
        }

        // Plano de recebimento "Sem antecipacao". Nao e oferecido na faixa
        // inicial - so a partir de R$ 20 mil por mes.
        foreach ($this->semAntecipacao() as $slug => $porGrupo) {
            foreach ($porGrupo as $grupo => [$debito, $creditoAvista, $parcelado]) {
                $this->debito($planos[$slug], $grupo, PrazoRecebimento::D1, $debito, $fonteSemAntecipacao);
                $this->taxa(
                    $planos[$slug],
                    TipoOperacao::CreditoAvista,
                    $grupo,
                    PrazoRecebimento::D30,
                    1,
                    $creditoAvista,
                    $fonteSemAntecipacao,
                );
                $this->creditoParceladoConstante(
                    $planos[$slug],
                    $grupo,
                    PrazoRecebimento::PARCELA_A_PARCELA,
                    2,
                    12,
                    $parcelado,
                    $fonteSemAntecipacao,
                );
            }
        }

        // Pix. A mesma leitura de infinitepay.io/taxas que ja sustenta
        // "sem aluguel, sem tarifa de conta e Pix gratuito" nos campos de
        // tarifa do plano: 0% em todas as faixas de faturamento. Cai na hora,
        // que e como o Pix funciona, e nao parcela.
        //
        // Ate a etapa 05 esta linha nao existia por falta de grupo de bandeira
        // para ela - nao por falta de dado.
        $fontePix = $this->fonte(
            self::URL,
            'A pagina de taxas da InfinitePay declara Pix gratuito, sem percentual e sem tarifa, '
            .'em todas as faixas de faturamento. Gravado no grupo tecnico "pix", que existe porque '
            .'o Pix nao passa por bandeira.',
        );

        foreach ($planos as $plano) {
            $this->pix($plano, PrazoRecebimento::NA_HORA, 0, $fontePix);
        }

        $this->equipamento($marca->getKey(), $planos);
    }

    private function planos(): array
    {
        return [
            'plano-inicial' => ['nome' => 'Plano Inicial', 'min' => null, 'max' => 19999.99, 'ordem' => 0],
            // Etapa 17, dito pelo Everton: nao ha taxa adicional de
            // antecipacao avulsa - as taxas publicadas (inclusive no plano
            // "sem antecipacao", que so existe a partir daqui) ja sao as
            // finais.
            'acima-de-20-mil' => ['nome' => 'Acima de 20 mil', 'min' => 20000, 'max' => 39999.99, 'ordem' => 1, 'taxa_antecipacao' => 0],
            'acima-de-40-mil' => ['nome' => 'Acima de 40 mil', 'min' => 40000, 'max' => 79999.99, 'ordem' => 2, 'taxa_antecipacao' => 0],
            'acima-de-80-mil' => ['nome' => 'Acima de 80 mil', 'min' => 80000, 'max' => null, 'ordem' => 3, 'taxa_antecipacao' => 0],
        ];
    }

    /** [plano] => [debito VM, credito VM 1x-12x, debito demais, credito demais 1x-12x]. */
    private function emUmDiaUtil(): array
    {
        return [
            'plano-inicial' => [
                1.37, [3.15, 5.39, 6.12, 6.85, 7.57, 8.28, 8.99, 9.69, 10.38, 11.06, 11.74, 12.40],
                2.58, [4.91, 6.47, 7.20, 7.92, 8.63, 9.33, 10.03, 10.72, 11.41, 12.08, 12.75, 13.41],
            ],
            'acima-de-20-mil' => [
                0.85, [2.89, 4.22, 4.83, 5.44, 6.05, 6.64, 7.24, 7.82, 8.41, 8.98, 9.56, 10.12],
                2.08, [4.65, 6.09, 6.69, 7.28, 7.87, 8.46, 9.05, 9.63, 10.20, 10.76, 11.33, 11.88],
            ],
            'acima-de-40-mil' => [
                0.79, [2.79, 4.08, 4.65, 5.21, 5.77, 6.32, 6.87, 7.42, 7.96, 8.49, 9.03, 9.56],
                1.98, [4.56, 5.95, 6.50, 7.05, 7.60, 8.15, 8.69, 9.23, 9.76, 10.29, 10.81, 11.33],
            ],
            'acima-de-80-mil' => [
                0.75, [2.69, 3.94, 4.46, 4.98, 5.49, 5.99, 6.51, 6.99, 7.51, 7.99, 8.49, 8.99],
                1.88, [4.46, 5.81, 6.32, 6.83, 7.33, 7.83, 8.34, 8.83, 9.32, 9.81, 10.29, 10.77],
            ],
        ];
    }

    /** [plano][grupo] => [debito, credito a vista, parcelado 2x-12x]. */
    private function semAntecipacao(): array
    {
        $vm = GrupoBandeira::VISA_MASTER;
        $demais = GrupoBandeira::DEMAIS;

        return [
            'acima-de-20-mil' => [$vm => [0.85, 2.22, 2.75], $demais => [2.08, 4.00, 4.65]],
            'acima-de-40-mil' => [$vm => [0.79, 2.12, 2.60], $demais => [1.98, 3.90, 4.50]],
            'acima-de-80-mil' => [$vm => [0.75, 1.62, 2.25], $demais => [1.88, 3.40, 4.15]],
        ];
    }

    private function equipamento(int $marcaId, array $planos): void
    {
        $equipamento = Equipamento::updateOrCreate(
            ['marca_id' => $marcaId, 'slug' => Str::slug('Maquininha Smart')],
            [
                'nome' => 'Maquininha Smart',
                'tipo' => TipoEquipamento::Smart,
                'descricao' => 'Unico aparelho da marca. Impressao de comprovante, bateria de alta '
                    .'duracao, gestao de vendas e estoque, sem aluguel e sem fidelidade.',
                'tem_chip_gratis' => true,
                'imprime_comprovante' => true,
                'aceita_nfc' => true,
                'exige_celular' => false,
                'status' => StatusItem::Ativo,
                'ordem' => 0,
            ],
        );

        foreach ($planos as $plano) {
            $equipamento->planos()->syncWithoutDetaching([
                $plano->getKey() => [
                    'preco_adesao' => 199.00,
                    'preco_adesao_promocional' => null,
                    // Etapa 05: as 12 vezes ja estavam na observacao desta
                    // mesma carga ("12x de R$ 16,58"). Agora sao campo, para o
                    // motor distinguir a parcela que a marca oferece da
                    // amortizacao que ele proprio faz para comparar.
                    'parcelas_adesao' => 12,
                    'aluguel_mensal' => null,
                    'observacao' => 'R$ 199,00 a vista ou 12x de R$ 16,58, com frete gratis. '
                        .'Preco de compra da primeira maquininha, sem aluguel.',
                    'status' => StatusItem::Ativo->value,
                ],
            ]);
        }
    }
}
