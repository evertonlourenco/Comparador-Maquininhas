<?php

namespace Database\Seeders;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Models\PrazoRecebimento;

/**
 * TrincaPay, lida em 15/09/2026 (etapa 17) na página de afiliado do canal
 * (trincapay.com.br/canal-monetizando/). A tabela vem como IMAGEM, não
 * texto - lida via captura de tela, não scrape de HTML.
 *
 * Ambiguidade não resolvida: o título da seção diz "taxas para faturamento
 * acima de R$ 5.000,00/mês", mas o cabeçalho da própria tabela diz "45K FAT
 * > R$45K" - dois números de faturamento diferentes na mesma página. Não dá
 * pra saber se existem outras faixas (5k-15k, 15k-45k) escondidas atrás de
 * algum seletor, ou se "R$ 45K" ali é erro de digitação da marca. Por isso o
 * plano entra sem faixa de faturamento declarada, com a ambiguidade anotada
 * no compromisso - não é a etapa 17 que resolve isso, é o Everton ou a
 * própria TrincaPay confirmando.
 *
 * Visa e Master têm o mesmo percentual em toda a tabela, EXCETO em 2x (Visa
 * 4,94% contra Master 5,14%) - é o que a marca publica, não erro de leitura
 * (mesmo padrão documentado no SumUpSeeder para uma divergência parecida).
 * Como o catálogo não distingue Visa de Master, ficou o valor do Master
 * (maior): mostrar o número mais caro é o lado seguro de um comparador, não
 * o mais otimista. Sem Pix publicado nesta página.
 */
class TrincaPaySeeder extends SeederDeMarca
{
    private const URL = 'https://trincapay.com.br/canal-monetizando/';

    public function run(): void
    {
        $marca = $this->marca('trincapay');

        $fonte = $this->fonte(
            self::URL,
            'Tabela publicada como imagem, lida por captura de tela. Cabeçalho da tabela diz "45K '
            .'FAT > R$45K", mas o título da seção diz "acima de R$ 5.000,00/mês" - ambiguidade não '
            .'resolvida, ver comentário da classe.',
            dataVerificacao: '2026-09-15',
        );

        $plano = $this->plano($marca, 'Oferta Canal Monetizando', [
            'tipo_enquadramento' => TipoEnquadramento::Escolhido,
            'compromisso' => 'Tabela da oferta exclusiva para o Canal Monetizando. A página não '
                .'deixa claro a partir de que faturamento ela vale (o texto diz "acima de R$ 5 mil", '
                .'o cabeçalho da tabela diz "> R$45 mil") - confirmar com a TrincaPay antes de tratar '
                .'como a tabela permanente da marca.',
            'status' => StatusItem::Ativo,
            'ordem' => 0,
        ]);

        // A pagina de afiliado diz "recebimento em 1 dia util".
        $d1 = PrazoRecebimento::D1;

        $this->debito($plano, 'visa_master', $d1, 1.39, $fonte);
        $this->serieDeCredito($plano, 'visa_master', $d1, [
            3.54, 5.14, 5.58, 6.56, 7.60, 8.53, 10.13, 10.95, 11.66, 12.38,
            13.03, 13.49, 16.08, 16.80, 17.51, 18.73, 19.64, 21.05, 22.29, 23.30, 24.62,
        ], $fonte);

        $this->debito($plano, 'elo', $d1, 1.79, $fonte);
        $this->serieDeCredito($plano, 'elo', $d1, [
            4.04, 5.56, 6.28, 7.29, 8.20, 9.03, 10.84, 11.65, 12.36, 13.13,
            13.89, 14.72, 17.08, 17.80, 18.01, 19.23, 20.44, 22.05, 23.20, 23.91, 24.62,
        ], $fonte);
    }
}
