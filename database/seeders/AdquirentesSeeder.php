<?php

namespace Database\Seeders;

use App\Models\Adquirente;
use Illuminate\Database\Seeder;

/**
 * Regra 7: adquirente e transparencia, nao deduplicacao. Cada entrada aqui foi
 * confirmada no rodape ou no texto institucional do site da propria marca -
 * nunca inferida da semelhanca entre taxas.
 */
class AdquirentesSeeder extends Seeder
{
    public function run(): void
    {
        $adquirentes = [
            ['PagSeguro', 'pagseguro',
                'PagSeguro Internet Instituicao de Pagamento S.A., CNPJ 08.561.701/0001-01. '
                .'Credenciadora propria da marca PagBank, conforme rodape de pagbank.com.br.'],
            ['CloudWalk', 'cloudwalk',
                'Conglomerado autorizado e supervisionado pelo Banco Central. O proprio site da '
                .'InfinitePay a descreve como "a plataforma de servicos financeiros da CloudWalk".'],
            ['Stone', 'stone',
                'Stone Instituicao de Pagamento S.A., CNPJ 16.501.555/0001-57. Credenciadora das '
                .'marcas Stone e Ton - que, pela regra 7, seguem concorrendo como opcoes distintas.'],
            ['SumUp', 'sumup',
                'SumUp Instituicao de Pagamento Brasil Ltda., CNPJ 16.668.076/0001-20. '
                .'Credenciadora propria da marca SumUp, conforme rodape de sumup.com.'],
            ['Cielo', 'cielo', 'Credenciadora propria da marca Cielo.'],
            ['Rede', 'rede', 'Credenciadora propria da marca Rede, do conglomerado Itau Unibanco.'],
            ['GetNet', 'getnet', 'Credenciadora propria da marca GetNet, do conglomerado Santander.'],
        ];

        foreach ($adquirentes as [$nome, $slug, $observacao]) {
            Adquirente::updateOrCreate(['slug' => $slug], ['nome' => $nome, 'observacao' => $observacao]);
        }
    }
}
