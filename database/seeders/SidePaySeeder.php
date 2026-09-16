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
 * SidePay, lida em 15/09/2026 (etapa 17) em sidepay.com.br/planos-taxas.
 * Completada com screenshots do Everton no mesmo dia, cobrindo o toggle
 * "Receba na hora" que a primeira leitura (scrape de texto) não capturou.
 *
 * Correção sobre a primeira leitura desta mesma etapa (15/09/2026): a SidePay
 * tem só DOIS grupos de bandeira, não três - a segunda coluna da tabela dela
 * é "Elo + Outros" (rótulo literal na tela), não "Elo" isolada como a Yelly
 * publica. A primeira leitura tinha gravado no grupo `elo`, o que mostraria
 * ao lojista "essa taxa vale só pra Elo" quando na verdade vale pra Elo E
 * qualquer outra bandeira fora Visa/Master - achado pelo Everton batendo o
 * olho no formulário do admin.
 *
 * **Achado real em produção em 16/09/2026:** a correção original limpava as
 * taxas erradas com um `DELETE` incondicional no início deste `run()` - até
 * aqui certo, porque era limpeza de um bug pontual daquele dia. O problema é
 * que o `DELETE` ficou incondicional para SEMPRE, então qualquer reseed
 * seguinte (como o que trouxe o catálogo de equipamentos, nesta mesma sessão)
 * apagava as 78 taxas já aprovadas no painel e recriava tudo do zero como
 * `rascunho` (padrão de `fonte()`, regra 10) - derrubando a SidePay do JSON
 * público (`comparador:gerar-json` some com a marca sem taxa publicada) sem
 * nenhum aviso. Confirmado sem taxa nenhuma da SidePay sobrando no grupo
 * `elo` antes de remover o bloco - a limpeza já não tem mais nada para
 * limpar, então sai daqui. Se precisar limpar de novo por outro motivo,
 * fazer manualmente uma vez, nunca de volta para o `run()`.
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

        $this->equipamentos($marca, $emUmDia, $naHoraPlano);
    }

    /**
     * Etapa 17, sessão de 16/09/2026: catálogo lido em sidepay.com.br/maquininhas.
     * As fotos são as mesmas que o próprio site usa (baixadas de lá, convertidas
     * para WebP aqui como qualquer upload) - mesmo padrão do FacilityPaySeeder.
     *
     * Diferente da FacilityPay (que muda de aba por plano com nome próprio), a
     * SidePay usa o mesmo toggle "Receba em 1 dia" / "Receba na hora" da tabela
     * de taxas para também trocar o preço do aparelho: `preco_adesao` ("De:",
     * o cheio riscado) é igual nos dois toggles para cada aparelho, só o
     * promocional ("por") muda - mais barato em "Receba na hora", assim como a
     * taxa de "Receba na hora" é mais alta (o mesmo trade-off adesão x taxa
     * mensal que o motor de cálculo já resolve, etapa 05).
     *
     * A SidePay usa aparelho fornecido pela PagBank por trás (junto de
     * FacilityPay e Yelly, dito pelo Everton) - mas o catálogo aqui é o nome e
     * a ficha técnica que a própria SidePay publica, não o nome PagBank
     * correspondente.
     */
    private function equipamentos(Marca $marca, Plano $emUmDia, Plano $naHoraPlano): void
    {
        $diretorioAssets = __DIR__.'/assets/sidepay';

        $aparelhos = [
            [
                'nome' => 'Mini',
                'tipo' => TipoEquipamento::PinPad,
                'descricao' => 'A maquininha mais acessível que oferece tudo o que o seu negócio precisa. '
                    .'Com conectividade 3G, tela colorida e design compacto, ela é pequena no tamanho, mas '
                    .'gigante na performance.',
                'imprime' => false,
                'arquivo' => 'sidepay-mini.png',
                'ordem' => 0,
                'precoCheio' => 247.00,
                'precoPorPlano' => ['em_um_dia' => 147.00, 'na_hora' => 97.00],
            ],
            [
                'nome' => 'Pro',
                'tipo' => TipoEquipamento::Pos,
                'descricao' => 'Custo-benefício: tela touchscreen, imprime comprovante na hora e garantia '
                    .'vitalícia.',
                'imprime' => true,
                'arquivo' => 'sidepay-pro.png',
                'ordem' => 1,
                'precoCheio' => 397.00,
                'precoPorPlano' => ['em_um_dia' => 247.00, 'na_hora' => 197.00],
            ],
            [
                'nome' => 'Smart',
                'tipo' => TipoEquipamento::Smart,
                'descricao' => 'Tecnologia de ponta: sistema Android, tela touchscreen e comprovante '
                    .'impresso ou por SMS.',
                'imprime' => true,
                'arquivo' => 'sidepay-smart.png',
                'ordem' => 2,
                'precoCheio' => 497.00,
                'precoPorPlano' => ['em_um_dia' => 347.00, 'na_hora' => 297.00],
            ],
        ];

        $planos = ['em_um_dia' => $emUmDia, 'na_hora' => $naHoraPlano];
        $nomePlano = ['em_um_dia' => 'Receba em 1 dia', 'na_hora' => 'Receba na hora'];

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
                        'observacao' => 'Preço no toggle "'.$nomePlano[$codigoPlano].'" em '
                            .'sidepay.com.br/maquininhas, verificado em 16/09/2026 - o toggle muda o preço '
                            .'do aparelho junto com a taxa, não só a taxa. "Cheio" (preço riscado no site) é '
                            .'igual nos dois toggles para o mesmo aparelho.',
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
