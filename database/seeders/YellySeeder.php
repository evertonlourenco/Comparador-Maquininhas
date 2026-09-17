<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoEquipamento;
use App\Models\Equipamento;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Support\Uploads\ImagemSeguraWebp;
use Illuminate\Support\Str;

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

        $this->equipamentos($marca, $flash, $premium);
    }

    /**
     * Etapa 17, sessão de 17/09/2026: catálogo lido em yelly.com.br/maquininhas
     * (ficha técnica e preço "padrão", sem cupom) e conferido contra os
     * screenshots do checkout com o cupom de afiliado do Everton
     * (checkout.yelly.com.br/monetizando/?cupom=AFILIADOS10) - mesmo padrão do
     * SidePaySeeder/FacilityPaySeeder.
     *
     * O checkout mostra um plano e aparelho extras, "Yelly Plus" (Débito
     * 1,13%, Crédito 12x 12,68%) - fora daqui por decisão do Everton: não
     * aparece na página oficial de maquininhas nem na de taxas, então não dá
     * pra confirmar ficha técnica nem se ainda está à venda (provável plano/
     * aparelho descontinuado, sem contrapartida em lugar nenhum verificável).
     *
     * `preco_adesao` (cheio) é igual nos dois planos, só o promocional muda -
     * mais barato no Flash (D+0) do que no Premium (D+1), o mesmo trade-off
     * adesão x taxa mensal já visto na SidePay e na FacilityPay. Conferido:
     * o "Total" que aparece no resumo do pedido dos screenshots do Everton
     * (com o cupom AFILIADOS10 aplicado) bate exatamente com 10% de desconto
     * sobre esse promocional - o cupom já cadastrado (`AFILIADOS10`, 10%,
     * incide sobre adesão de qualquer maquininha) resolve isso sozinho, sem
     * precisar de preço específico por equipamento.
     */
    private function equipamentos(Marca $marca, Plano $flash, Plano $premium): void
    {
        $diretorioAssets = __DIR__.'/assets/yelly';

        $aparelhos = [
            [
                'nome' => 'Mini',
                'tipo' => TipoEquipamento::PinPad,
                'descricao' => 'Mobilidade total, sem complicação: pra quem atende na rua, em domicílio, '
                    .'delivery ou prestação de serviço e precisa de uma maquininha leve e rápida. Vende no '
                    .'Pix, na aproximação (NFC) e no cartão, com tela colorida e teclado físico - simples de '
                    .'operar até na correria. Comprovante por SMS.',
                'imprime' => false,
                'arquivo' => 'yelly-mini.png',
                'ordem' => 0,
                'precoCheio' => 399.00,
                'precoPorPlano' => ['flash' => 99.90, 'premium' => 199.90],
            ],
            [
                'nome' => 'Pro',
                'tipo' => TipoEquipamento::Pos,
                'descricao' => 'A escolha mais equilibrada pra loja física: rapidez + comprovante impresso '
                    .'(já com bobinas). Tela maior que a Mini, aceita aproximação (NFC) e QR Code. Bateria '
                    .'para até 12 horas.',
                'imprime' => true,
                'arquivo' => 'yelly-pro.png',
                'ordem' => 1,
                'precoCheio' => 699.00,
                'precoPorPlano' => ['flash' => 199.90, 'premium' => 299.90],
            ],
            [
                'nome' => 'Smart',
                'tipo' => TipoEquipamento::Smart,
                'descricao' => 'Frente de caixa com cara de sistema: touch, câmera e gestão na própria '
                    .'máquina. Pra operação mais pesada, com volume e fila. Tela touchscreen de 5,5", câmera '
                    .'5MP com flash, aceita QR Code e NFC, imprime comprovante (com bobinas) e oferece '
                    .'gestão do negócio (estoque, produtos, clientes e caixa) direto na máquina.',
                'imprime' => true,
                'arquivo' => 'yelly-smart.png',
                'ordem' => 2,
                'precoCheio' => 799.00,
                'precoPorPlano' => ['flash' => 299.90, 'premium' => 399.90],
            ],
        ];

        $planos = ['flash' => $flash, 'premium' => $premium];
        $nomePlano = ['flash' => 'Flash', 'premium' => 'Premium'];

        foreach ($aparelhos as $dados) {
            $equipamento = $this->equipamento($marca, $dados['nome'], [
                'tipo' => $dados['tipo'],
                'descricao' => $dados['descricao'],
                'tem_chip_gratis' => true,
                'imprime_comprovante' => $dados['imprime'],
                'aceita_nfc' => true,
                'exige_celular' => false,
                'status' => StatusItem::Ativo,
                'ordem' => $dados['ordem'],
            ]);

            // Só grava a foto na criação - se o admin trocar depois pelo painel
            // (upload manual ou "buscar por URL"), o reseed não pode reverter a
            // escolha dele (mesmo espírito da regra do etapa 17 sobre nome/status
            // de plano nunca serem sobrescritos).
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
                        'aluguel_mensal' => null,
                        // Etapa 17, dito pelo Everton: adesão parcela em 12x sem
                        // juros sobre o preço à vista, em todas as marcas.
                        'parcelas_adesao' => 12,
                        'observacao' => 'Preço no plano "'.$nomePlano[$codigoPlano].'" em '
                            .'yelly.com.br/maquininhas, verificado em 17/09/2026 e conferido contra o '
                            .'checkout com o cupom de afiliado do Everton - o plano muda o preço do aparelho '
                            .'junto com a taxa, não só a taxa. "Cheio" (preço riscado no checkout) é igual '
                            .'nos dois planos para o mesmo aparelho.',
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
