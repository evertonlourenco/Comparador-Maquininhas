<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoEquipamento;
use App\Models\Equipamento;
use App\Models\GrupoBandeira;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Support\Uploads\ImagemSeguraWebp;
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

        // Etapa 17, dito pelo Everton: o Pix da SumUp é gratuito só quando
        // recebido direto na conta SumUp Bank - fora dela, cobra 0,9%. Mesma
        // regra da Ton: o número na tela é o real (0,9%), o 0% condicional
        // vai colado em `condicao`.
        $fontePix = $this->fonte(
            self::URL,
            condicao: 'Grátis (0%) quando recebido diretamente na conta SumUp Bank.',
            dataVerificacao: '2026-09-15',
        );

        foreach ($planos as $plano) {
            $this->pix($plano, PrazoRecebimento::NA_HORA, 0.9, $fontePix);
        }

        $this->equipamentos($marca->getKey(), $planos);
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
     * Preço, etapa 17 (15/09/2026 e corrigido em 17/09/2026): a etapa 04
     * tinha deixado o preço em branco porque a página de maquininhas só
     * publicava a parcela, às vezes ambígua entre dois valores. O Everton
     * confirmou em 15/09/2026 um único preço por aparelho (Smart R$ 190,80,
     * Solo R$ 58,80, Top R$ 46,80) - sem URL pública pra citar como fonte
     * naquele momento.
     *
     * Em 17/09/2026, revisitando `sumup.com/pt-br/maquininhas/` (a página
     * agora mostra os dois valores lado a lado, riscado e promocional, para
     * Solo e Smart - Top continua com um preço só): os números batem
     * exatamente com o que o Everton tinha confirmado, só que **o valor que
     * ele deu é o promocional, não o cheio** - Smart tem um "de R$ 598,80"
     * riscado (12x R$ 49,90) que não estava capturado, e Solo um "de
     * R$ 118,80" (12x R$ 9,90). Sem aluguel: o aparelho é comprado, mesmo
     * preço nos três planos de faturamento.
     *
     * `parcelas_adesao = 12` continua batendo com a própria página: a
     * parcela que `EquipamentoPlano::parcelaDaAdesao()` calcula a partir do
     * preço vigente (promocional quando existe) reproduz exatamente os "12x
     * R$ X,XX" publicados (Smart 15,90, Solo 4,90, Top 3,90) - conferido
     * antes de gravar.
     *
     * @param  array<string, Plano>  $planos
     */
    private function equipamentos(int $marcaId, array $planos): void
    {
        $diretorioAssets = __DIR__.'/assets/sumup';

        $aparelhos = [
            ['Top', TipoEquipamento::PinPad, 'Conecta ao celular por Bluetooth. Pagamento por aproximacao e comprovante digital.',
                ['chip' => false, 'imprime' => false, 'nfc' => true, 'celular' => true], 46.80, null, 'sumup-top.webp', 0],
            ['Solo', TipoEquipamento::Pos, 'Wi-Fi e chip 4G ilimitado, base carregadora, Pix por QR Code e comprovante digital.',
                ['chip' => true, 'imprime' => false, 'nfc' => true, 'celular' => false], 118.80, 58.80, 'sumup-solo.jpeg', 1],
            ['SumUp Smart', TipoEquipamento::Smart, 'Wi-Fi e chip 4G ilimitado, impressao de comprovantes e relatorios, catalogo e estoque.',
                ['chip' => true, 'imprime' => true, 'nfc' => true, 'celular' => false], 598.80, 190.80, 'sumup-smart.webp', 2],
        ];

        foreach ($aparelhos as [$nome, $tipo, $descricao, $flags, $adesao, $promocional, $arquivo, $ordem]) {
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

            if ($equipamento->imagem_path === null) {
                $caminho = ImagemSeguraWebp::salvar("{$diretorioAssets}/{$arquivo}", 'equipamentos');

                if ($caminho !== null) {
                    $equipamento->update(['imagem_path' => $caminho]);
                }
            }

            foreach ($planos as $plano) {
                $equipamento->planos()->syncWithoutDetaching([
                    $plano->getKey() => [
                        'preco_adesao' => $adesao,
                        'preco_adesao_promocional' => $promocional,
                        'aluguel_mensal' => null,
                        'parcelas_adesao' => 12,
                        'observacao' => 'Preço em sumup.com/pt-br/maquininhas/, verificado em 17/09/2026 '
                            .'- sem aluguel, aparelho comprado, mesmo preço nos três planos de '
                            .'faturamento.',
                        'status' => StatusItem::Ativo->value,
                    ],
                ]);
            }
        }
    }
}
