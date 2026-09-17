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
 * PagBank (PagSeguro), lido em 08/09/2026.
 *
 * **O plano "Taxas iniciais" existiu até 16/09/2026 e foi retirado de
 * propósito.** Vinha de uma página ("Taxas e Tarifas") com a tabela
 * dimensional completa - grupo de bandeiras x prazo de recebimento (na
 * hora, 14 dias, 30 dias) x a vista/parcelado -, mas o Everton confirmou
 * em 17/09/2026 que a exclusão (soft-delete no painel, mesma sessão que
 * corrigiu a `TabelaDoPlano`) foi definitiva: os "Planos comerciais"
 * (Essencial e Super Max, `planosComerciais()` abaixo) são o que a marca
 * de fato oferece hoje, e substituem a antiga tabela de entrada. Catálogo
 * de marca muda com o tempo - o próprio Everton citou a Ton como exemplo,
 * que já teve 5, depois 3, depois 4 planos.
 *
 * 2. Taxas e Planos traz os planos comerciais (Essencial e Super Max) e os
 *    precos dos aparelhos, mas publica percentual sem dizer o prazo de
 *    recebimento nem o grupo de bandeiras. Faltando duas das cinco dimensoes
 *    da chave, esses numeros nao viram taxa: os planos entram como entidade e
 *    os precos de adesao entram no par equipamento+plano, sem taxa nenhuma
 *    pendurada. Regra 6: campo vazio e honesto, numero deduzido nao e.
 */
class PagBankSeeder extends SeederDeMarca
{
    private const URL_PLANOS = 'https://pagbank.com.br/para-seu-negocio/taxas-planos-maquininhas';

    /**
     * Etapa 17, sessão de 16/09/2026: taxas dos planos comerciais Essencial e
     * Super Max, lidas direto em pagbank.com.br (a própria home - o
     * simulador de taxas mostra a tabela dimensional completa, diferente da
     * "Taxas e Planos" que só dá o percentual solto). Prazo: só "na hora" é
     * mostrado. Grupos: "Visa e Mastercard" e "Elo e demais bandeiras" (o
     * mesmo padrão `demais` já usado no resto da carga desta marca).
     */
    private const URL_TAXAS_PLANOS_COMERCIAIS = 'https://pagbank.com.br';

    public function run(): void
    {
        $marca = $this->marca('pagbank');

        $this->planosComerciais($marca);
    }

    /**
     * Essencial e Super Max: a pagina "Taxas e Planos" (URL_PLANOS) so da o
     * percentual solto, sem prazo nem grupo de bandeiras - por isso os
     * precos de aparelho vem de la, mas a taxa em si vem do simulador na
     * propria home (URL_TAXAS_PLANOS_COMERCIAIS), que abre a tabela
     * dimensional completa por plano, com o alternador "periodo
     * promocional" / "apos o periodo promocional" (e, no Super Max, mais um
     * alternador por faixa de faturamento).
     *
     * Regra 3: a oferta de entrada de cada plano e um Plano `promocional`
     * a parte, nunca uma coluna a mais no plano permanente - e por isso
     * "Essencial" e "Super Max" cada um ganhou um plano-irmao "periodo
     * promocional".
     */
    private function planosComerciais(Marca $marca): void
    {
        $marcaId = $marca->getKey();
        $vm = GrupoBandeira::VISA_MASTER;
        $demais = GrupoBandeira::DEMAIS;
        $fontePlanos = $this->fonte(self::URL_TAXAS_PLANOS_COMERCIAIS);

        $essencial = $this->plano($marca, 'Essencial', [
            'tipo_enquadramento' => TipoEnquadramento::Escolhido,
            'compromisso' => 'Sem minimo de vendas.',
            'mensalidade' => 0,
            'status' => StatusItem::Ativo,
            'ordem' => 1,
        ]);

        $essencialPromo = $this->plano($marca, 'Essencial — período promocional', [
            'tipo_enquadramento' => TipoEnquadramento::Promocional,
            'promocional_dias' => 30,
            'promocional_valor_processado' => 1500,
            'promocional_sucessor_id' => $essencial->getKey(),
            'compromisso' => 'Oferta de entrada: valida por 30 dias ou ate R$ 1.500 processados, o que vier primeiro.',
            'mensalidade' => 0,
            'status' => StatusItem::Ativo,
            'ordem' => 1,
        ]);

        // Regra 1: so o prazo "na hora" e publicado nessas telas - nao ha
        // D+1/D+30 declarado para os planos comerciais.
        $this->gravarTabelaComercial($essencialPromo, $vm, $demais, $fontePlanos, pix: 0, debito: [0, 0], credito: [
            $vm => [0, 9.91, 11.29, 12.64, 13.97, 15.27, 16.55, 17.81, 19.04, 20.24, 21.43, 22.59],
            $demais => [0, 9.91, 11.29, 12.64, 13.97, 15.27, 16.55, 17.81, 19.04, 20.24, 21.43, 22.59],
        ]);

        $this->gravarTabelaComercial($essencial, $vm, $demais, $fontePlanos, pix: 0.99, debito: [2.39, 2.98], credito: [
            $vm => [4.99, 9.91, 11.29, 12.64, 13.97, 15.27, 16.55, 17.81, 19.04, 20.24, 21.43, 22.59],
            $demais => [6.17, 11.10, 12.48, 13.83, 15.16, 16.46, 17.74, 19.00, 20.23, 21.43, 22.62, 23.78],
        ]);

        $superMaxAte2k = $this->plano($marca, 'Super Max — até R$ 2.000', [
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'faturamento_max' => 1999.99,
            'compromisso' => 'Faturamento mensal ate R$ 2.000.',
            'mensalidade' => 0,
            'status' => StatusItem::Ativo,
            'ordem' => 2,
        ]);

        $superMaxAcima2k = $this->plano($marca, 'Super Max — acima de R$ 2.000', [
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'faturamento_min' => 2000,
            'compromisso' => 'Faturamento mensal acima de R$ 2.000.',
            'mensalidade' => 0,
            'status' => StatusItem::Ativo,
            'ordem' => 2,
        ]);

        $superMaxPromo = $this->plano($marca, 'Super Max — período promocional', [
            'tipo_enquadramento' => TipoEnquadramento::Promocional,
            'promocional_dias' => 30,
            'promocional_valor_processado' => 5000,
            // Sem sucessor unico de proposito: depois dos 30 dias o lojista
            // cai na faixa automatica (ate/acima R$ 2.000) que bater com o
            // faturamento dele - regra 3, "o enquadramento automatico da
            // marca para aquele faturamento".
            'compromisso' => 'Oferta de entrada: valida por 30 dias ou ate R$ 5.000 processados, o que vier primeiro.',
            'mensalidade' => 0,
            'status' => StatusItem::Ativo,
            'ordem' => 2,
        ]);

        $this->gravarTabelaComercial($superMaxPromo, $vm, $demais, $fontePlanos, pix: 0, debito: [0.58, 2.57], credito: [
            $vm => [0.58, 3.98, 3.98, 4.98, 5.98, 6.98, 7.98, 7.98, 7.98, 7.98, 7.98, 7.98],
            $demais => [4.34, 7.02, 7.02, 8.02, 9.02, 10.02, 10.72, 10.72, 10.72, 10.72, 10.72, 10.72],
        ]);

        $this->gravarTabelaComercial($superMaxAte2k, $vm, $demais, $fontePlanos, pix: 0.99, debito: [2.39, 2.98], credito: [
            $vm => [4.99, 9.91, 11.29, 12.64, 13.97, 15.27, 16.55, 17.81, 19.04, 20.24, 21.43, 22.59],
            $demais => [6.17, 11.10, 12.48, 13.83, 15.16, 16.46, 17.74, 19.00, 20.23, 21.43, 22.62, 23.78],
        ]);

        $this->gravarTabelaComercial($superMaxAcima2k, $vm, $demais, $fontePlanos, pix: 0.99, debito: [1.44, 1.65], credito: [
            $vm => [3.49, 6.98, 6.98, 7.98, 9.98, 10.88, 11.68, 12.48, 12.98, 13.28, 13.48, 13.78],
            $demais => [3.49, 8.28, 8.28, 9.28, 11.28, 12.18, 12.98, 13.78, 14.28, 14.58, 14.78, 15.08],
        ]);

        $diretorioAssets = __DIR__.'/assets/pagbank';

        // Etapa 17 (17/09/2026): fotos adicionadas e dois achados na
        // releitura de pagbank.com.br/para-seu-negocio/maquininhas/moderninha-plus-2
        // (a página individual de cada aparelho tem "Ficha técnica" completa
        // e, no rodapé, um grid comparativo com o tipo de comprovante das
        // seis - fonte mais granular que a página de listagem usada na
        // etapa 04).
        //
        // - **`imprime` da Moderninha Plus 2 estava `true`, errado.** O
        //   próprio texto do produto diz "Envio de comprovante por SMS", e o
        //   grid comparativo confirma "Comprovante por SMS" - sem impressora.
        //   Corrigido para `false`.
        // - **`chip` deixou de ser fixo `true` para todas.** A Minizinha
        //   NFC 2 é a única das seis cuja página nunca diz "(chip grátis)" -
        //   diz só "Conexão por Bluetooth (precisa de celular)". As outras
        //   cinco repetem literalmente "Não precisa de celular (chip
        //   grátis)". Coerente com `exige_celular`: quem depende do celular
        //   não tem chip de dados próprio.
        $aparelhos = [
            ['Minizinha NFC 2', TipoEquipamento::PinPad, 'Maquininha compacta que conecta ao celular via '
                .'Bluetooth - não tem chip de dados próprio, usa a internet do celular pareado. Pagamento '
                .'por aproximação (NFC) e comprovante por e-mail ou SMS.',
                ['imprime' => false, 'celular' => true, 'chip' => false], 118.80, 15.00, 'minizinha-nfc2.png', 0],
            ['Minizinha Chip 3', TipoEquipamento::Pos, 'Maquininha compacta com chip e Wi-Fi próprios, não '
                .'precisa de celular. Pagamento por aproximação (NFC) e comprovante por SMS.',
                ['imprime' => false, 'celular' => false, 'chip' => true], 298.80, 47.88, 'minizinha-chip3.png', 1],
            ['Moderninha Plus 2', TipoEquipamento::Pos, 'Chip e internet grátis (Wi-Fi e Bluetooth), sem '
                .'precisar de celular. Pagamento por aproximação (NFC) e comprovante por SMS - não imprime.',
                ['imprime' => false, 'celular' => false, 'chip' => true], 346.80, 59.88, 'moderninha-plus2.png', 2],
            ['Moderninha Pro 2', TipoEquipamento::Pos, 'Chip e internet 4G grátis, não precisa de celular. '
                .'Imprime comprovante ou envia gratuitamente por SMS, com bateria de até 12 horas.',
                ['imprime' => true, 'celular' => false, 'chip' => true], 838.80, 107.88, 'moderninha-pro2.png', 3],
            ['Moderninha Smart 2', TipoEquipamento::Smart, 'Maquininha Android com controle de estoque e '
                .'gestão completa, chip e internet grátis, não precisa de celular. Imprime comprovante.',
                ['imprime' => true, 'celular' => false, 'chip' => true], 838.80, 196.08, 'moderninha-smart2.png', 4],
            ['Moderninha ProFit', TipoEquipamento::Smart, 'Pagamento por aproximação (NFC), conexão Wi-Fi e '
                .'chip grátis, não precisa de celular. Imprime comprovante, com reposição de bobina grátis.',
                ['imprime' => true, 'celular' => false, 'chip' => true], 871.64, 83.88, 'moderninha-profit.png', 5],
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
                    'aceita_nfc' => true,
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

            // A pagina publica esses precos como "Maquininhas do Plano Super
            // Max". Sem preco publicado para os outros planos, so o Super Max
            // ganha linha - a ausencia de linha ja diz "nao publicado". As
            // duas faixas automaticas (etapa 17, 16/09/2026) sao a mesma
            // compra de aparelho, so a taxa depois do periodo promocional
            // muda com o faturamento - por isso o mesmo preco entra nas duas.
            $pivot = [
                'preco_adesao' => $adesao,
                'preco_adesao_promocional' => $promocional,
                'aluguel_mensal' => null,
                // Etapa 17, dito pelo Everton: adesao parcela em 12x sem
                // juros sobre o preco a vista, em todas as marcas.
                'parcelas_adesao' => 12,
                'observacao' => 'Preço em pagbank.com.br/para-seu-negocio/maquininhas, verificado em '
                    .'17/09/2026, com entrega grátis e 5 anos de garantia. Aparelho comprado, sem aluguel.',
                'status' => StatusItem::Ativo->value,
            ];

            $equipamento->planos()->syncWithoutDetaching([
                $superMaxAte2k->getKey() => $pivot,
                $superMaxAcima2k->getKey() => $pivot,
            ]);
        }
    }

    /**
     * @param  array<string, list<float>>  $credito  chave = codigo do grupo de bandeira,
     *                                                valor = [1x, 2x, 3x, ..., 12x]
     */
    private function gravarTabelaComercial(
        Plano $plano,
        string $vm,
        string $demais,
        array $fontePlanos,
        float $pix,
        array $debito,
        array $credito,
    ): void {
        $this->pix($plano, PrazoRecebimento::NA_HORA, $pix, $fontePlanos);
        $this->debito($plano, $vm, PrazoRecebimento::NA_HORA, $debito[0], $fontePlanos);
        $this->debito($plano, $demais, PrazoRecebimento::NA_HORA, $debito[1], $fontePlanos);
        $this->serieDeCredito($plano, $vm, PrazoRecebimento::NA_HORA, $credito[$vm], $fontePlanos);
        $this->serieDeCredito($plano, $demais, PrazoRecebimento::NA_HORA, $credito[$demais], $fontePlanos);
    }
}
