<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoEquipamento;
use App\Models\Equipamento;
use App\Models\GrupoBandeira;
use App\Models\PrazoRecebimento;
use Illuminate\Support\Str;

/**
 * SumUp, lida em 08/09/2026 das paginas de taxas por prazo de recebimento.
 *
 * Duas particularidades da marca, ambas declaradas por ela:
 *
 * - "Receba na hora" e "Receba em 1 dia" saem pelo mesmo percentual. A pagina
 *   do recebimento na hora diz textualmente "E com as mesmas taxas". As duas
 *   linhas existem mesmo assim, porque prazo e dimensao da taxa (regra 1) e o
 *   comparador precisa poder filtrar por prazo.
 * - So ha percentual publicado para Visa e Mastercard - toda tabela do site
 *   traz a nota "Taxas validas para Visa/Mastercard". Elo, Amex e os vouchers
 *   estao vinculados a marca como bandeira aceita, mas sem taxa: nenhum numero
 *   foi deduzido para eles.
 */
class SumUpSeeder extends SeederDeMarca
{
    private const URL = 'https://www.sumup.com/pt-br/maquininhas/taxas/receba-um-dia/';

    private const URL_NA_HORA = 'https://www.sumup.com/pt-br/maquininhas/taxas/receba-na-hora/';

    public function run(): void
    {
        $marca = $this->marca('sumup');
        $vm = GrupoBandeira::VISA_MASTER;

        $planos = [];

        foreach ($this->planos() as $slug => $dados) {
            $planos[$slug] = $this->plano($marca, $dados['nome'], [
                'tipo_enquadramento' => TipoEnquadramento::Automatico,
                'faturamento_min' => $dados['min'],
                'faturamento_max' => $dados['max'],
                // Publicado na pagina de maquininhas: "Sem mensalidade. Sem
                // aluguel." e Pix a 0%.
                'mensalidade' => 0,
                'tarifa_pix_recebimento' => 0,
                'compromisso' => 'Enquadramento automatico pelo faturamento mensal. O primeiro mes '
                    .'tem tabela promocional propria, que nao foi carregada aqui.',
                'status' => StatusItem::Ativo,
                'ordem' => $dados['ordem'],
            ]);
        }

        $prazos = [
            PrazoRecebimento::D1 => $this->fonte(self::URL),
            PrazoRecebimento::NA_HORA => $this->fonte(
                self::URL_NA_HORA,
                'A propria pagina do recebimento na hora declara que o percentual e o mesmo do '
                .'recebimento em 1 dia util. O que muda e so o prazo.',
            ),
        ];

        foreach ($this->tabela() as $slug => [$debito, $credito]) {
            foreach ($prazos as $prazo => $fonte) {
                $this->debito($planos[$slug], $vm, $prazo, $debito, $fonte);
                $this->serieDeCredito($planos[$slug], $vm, $prazo, $credito, $fonte);
            }
        }

        $this->equipamentos($marca->getKey());
    }

    private function planos(): array
    {
        return [
            'ate-r-20-mil' => ['nome' => 'Até R$ 20 mil', 'min' => null, 'max' => 19999.99, 'ordem' => 0],
            'entre-r-20-mil-e-r-50-mil' => ['nome' => 'Entre R$ 20 mil e R$ 50 mil', 'min' => 20000, 'max' => 49999.99, 'ordem' => 1],
            'acima-de-r-50-mil' => ['nome' => 'Acima de R$ 50 mil', 'min' => 50000, 'max' => null, 'ordem' => 2],
        ];
    }

    /** [plano] => [debito, [1x, 2x, ... 12x]]. So Visa e Mastercard. */
    private function tabela(): array
    {
        return [
            'ate-r-20-mil' => [0.99, [3.49, 6.49, 7.49, 7.99, 8.49, 9.49, 9.99, 10.49, 10.99, 11.49, 12.49, 13.99]],
            // 10x sai abaixo de 9x nesta faixa. E o que a marca publica, e foi
            // transcrito como esta - nao e erro de leitura.
            'entre-r-20-mil-e-r-50-mil' => [0.99, [3.29, 4.49, 4.99, 5.49, 5.99, 6.59, 7.49, 8.49, 8.99, 8.79, 9.49, 10.49]],
            'acima-de-r-50-mil' => [0.99, [3.19, 3.99, 4.59, 5.19, 5.59, 6.49, 6.99, 7.49, 7.89, 8.49, 8.99, 9.99]],
        ];
    }

    /**
     * Sem linha em equipamento_plano: a pagina de maquininhas so publica o
     * valor da parcela, sem preco a vista, e um deles aparece com dois valores
     * de parcela sem dizer qual e o vigente. Multiplicar parcela por 12 daria
     * um numero que a marca nao publicou. Regra 6: preco fica em branco ate
     * alguem conferir - o painel permite completar sem mexer em codigo.
     */
    private function equipamentos(int $marcaId): void
    {
        $aparelhos = [
            ['Top', TipoEquipamento::PinPad, 'Conecta ao celular por Bluetooth. Pagamento por aproximacao e comprovante digital.',
                ['chip' => false, 'imprime' => false, 'nfc' => true, 'celular' => true], 0],
            ['Solo', TipoEquipamento::Pos, 'Wi-Fi e chip 4G ilimitado, base carregadora, Pix por QR Code e comprovante digital.',
                ['chip' => true, 'imprime' => false, 'nfc' => true, 'celular' => false], 1],
            ['SumUp Smart', TipoEquipamento::Smart, 'Wi-Fi e chip 4G ilimitado, impressao de comprovantes e relatorios, catalogo e estoque.',
                ['chip' => true, 'imprime' => true, 'nfc' => true, 'celular' => false], 2],
        ];

        foreach ($aparelhos as [$nome, $tipo, $descricao, $flags, $ordem]) {
            Equipamento::updateOrCreate(
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
        }
    }
}
