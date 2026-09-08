<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Enums\TipoEquipamento;
use App\Models\Equipamento;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\PrazoRecebimento;
use Illuminate\Support\Str;

/**
 * PagBank (PagSeguro), lido em 08/09/2026.
 *
 * O PagBank publica as taxas em duas paginas que nao se sobrepoem:
 *
 * 1. Taxas e Tarifas traz a tabela dimensional completa - grupo de bandeiras x
 *    prazo de recebimento (na hora, 14 dias, 30 dias) x a vista/parcelado. E a
 *    unica que casa com a chave da regra 1, e e dela que saem as taxas aqui.
 *    A propria pagina chama esses numeros de "taxas iniciais", e e esse o nome
 *    do plano.
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
    private const URL_TAXAS = 'https://pagbank.com.br/para-seu-negocio/maquininhas/taxas-e-tarifas';

    private const URL_PLANOS = 'https://pagbank.com.br/para-seu-negocio/taxas-planos-maquininhas';

    public function run(): void
    {
        $marca = $this->marca('pagbank');
        $vm = GrupoBandeira::VISA_MASTER;
        $demais = GrupoBandeira::DEMAIS;

        $taxasIniciais = $this->plano($marca, 'Taxas iniciais', [
            'tipo_enquadramento' => TipoEnquadramento::Automatico,
            'compromisso' => 'Tabela de entrada, aplicada a quem ainda nao negociou condicao '
                .'comercial. A propria pagina avisa que as taxas podem variar por negociacao.',
            'status' => StatusItem::Ativo,
            'ordem' => 0,
        ]);

        $fonte = $this->fonte(self::URL_TAXAS);

        $fonteParcelado = $this->fonte(
            self::URL_TAXAS,
            'O PagBank publica um percentual unico de parcelado por prazo, sem abrir por numero de '
            .'parcelas. O mesmo valor foi gravado de 2x a 12x, que e o limite que a pagina cita; '
            .'acima de 12x ela informa depender de aprovacao do emissor, entao nada foi gravado la.',
        );

        $fonteDebito = $this->fonte(
            self::URL_TAXAS,
            'A pagina apresenta o percentual de debito como "a vista por 1 ano" - e taxa de entrada '
            .'com prazo declarado, nao a condicao permanente.',
        );

        // Debito: so o prazo "na hora" e publicado.
        $this->debito($taxasIniciais, $vm, PrazoRecebimento::NA_HORA, 1.99, $fonteDebito);
        $this->debito($taxasIniciais, $demais, PrazoRecebimento::NA_HORA, 2.98, $fonteDebito);

        // Credito a vista, por prazo de recebimento.
        $aVista = [
            PrazoRecebimento::NA_HORA => [$vm => 4.99, $demais => 6.17],
            PrazoRecebimento::D14 => [$vm => 3.99, $demais => 3.99],
            PrazoRecebimento::D30 => [$vm => 3.19, $demais => 3.19],
        ];

        foreach ($aVista as $prazo => $porGrupo) {
            foreach ($porGrupo as $grupo => $percentual) {
                $this->serieDeCredito($taxasIniciais, $grupo, $prazo, [$percentual], $fonte);
            }
        }

        // Credito parcelado: percentual unico por prazo, de 2x a 12x.
        $parcelado = [
            PrazoRecebimento::NA_HORA => [$vm => 5.59, $demais => 6.78],
            PrazoRecebimento::D14 => [$vm => 4.59, $demais => 4.59],
            PrazoRecebimento::D30 => [$vm => 3.79, $demais => 3.79],
        ];

        foreach ($parcelado as $prazo => $porGrupo) {
            foreach ($porGrupo as $grupo => $percentual) {
                $this->creditoParceladoConstante($taxasIniciais, $grupo, $prazo, 2, 12, $percentual, $fonteParcelado);
            }
        }

        $this->planosComerciais($marca);
    }

    /**
     * Essencial e Super Max entram sem taxa: a pagina que os publica nao diz
     * prazo de recebimento nem grupo de bandeiras. Os precos de aparelho, sim,
     * sao publicados por plano - e e no par equipamento+plano que eles moram.
     */
    private function planosComerciais(Marca $marca): void
    {
        $marcaId = $marca->getKey();

        $this->plano($marca, 'Essencial', [
            'tipo_enquadramento' => TipoEnquadramento::Escolhido,
            'compromisso' => 'Sem minimo de vendas.',
            'status' => StatusItem::Ativo,
            'ordem' => 1,
        ]);

        $superMax = $this->plano($marca, 'Super Max', [
            'tipo_enquadramento' => TipoEnquadramento::Escolhido,
            'faturamento_min' => 2000,
            'compromisso' => 'Exige vendas acima de R$ 2.000 por mes.',
            'status' => StatusItem::Ativo,
            'ordem' => 2,
        ]);

        $aparelhos = [
            ['Minizinha NFC 2', TipoEquipamento::PinPad, 'Maquininha compacta que funciona conectada ao celular.',
                ['imprime' => false, 'celular' => true], 118.80, 15.00, 0],
            ['Minizinha Chip 3', TipoEquipamento::Pos, 'Maquininha com chip e Wi-Fi, sem precisar de celular.',
                ['imprime' => false, 'celular' => false], 298.80, 47.88, 1],
            ['Moderninha Plus 2', TipoEquipamento::Pos, 'Chip e internet gratis, sem aluguel.',
                ['imprime' => true, 'celular' => false], 346.80, 59.88, 2],
            ['Moderninha Pro 2', TipoEquipamento::Pos, 'Chip e internet gratis, com pagamento via QR Code.',
                ['imprime' => true, 'celular' => false], 838.80, 107.88, 3],
            ['Moderninha Smart 2', TipoEquipamento::Smart, 'Maquininha smart com controle de estoque e gestao.',
                ['imprime' => true, 'celular' => false], 838.80, 196.08, 4],
            ['Moderninha ProFit', TipoEquipamento::Smart, 'Pagamento por aproximacao, conexao Wi-Fi e chip.',
                ['imprime' => true, 'celular' => false], 871.64, 83.88, 5],
        ];

        foreach ($aparelhos as [$nome, $tipo, $descricao, $flags, $adesao, $promocional, $ordem]) {
            $equipamento = Equipamento::updateOrCreate(
                ['marca_id' => $marcaId, 'slug' => Str::slug($nome)],
                [
                    'nome' => $nome,
                    'tipo' => $tipo,
                    'descricao' => $descricao,
                    'tem_chip_gratis' => true,
                    'imprime_comprovante' => $flags['imprime'],
                    'aceita_nfc' => true,
                    'exige_celular' => $flags['celular'],
                    'status' => StatusItem::Ativo,
                    'ordem' => $ordem,
                ],
            );

            // A pagina publica esses precos como "Maquininhas do Plano Super
            // Max". Sem preco publicado para os outros planos, so o Super Max
            // ganha linha - a ausencia de linha ja diz "nao publicado".
            $equipamento->planos()->syncWithoutDetaching([
                $superMax->getKey() => [
                    'preco_adesao' => $adesao,
                    'preco_adesao_promocional' => $promocional,
                    'aluguel_mensal' => null,
                    'observacao' => 'Preco promocional vigente no site em 08/09/2026, com entrega '
                        .'gratis e 5 anos de garantia. Aparelho comprado, sem aluguel.',
                    'status' => StatusItem::Ativo->value,
                ],
            ]);
        }
    }
}
