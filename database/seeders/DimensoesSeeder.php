<?php

namespace Database\Seeders;

use App\Models\GrupoBandeira;
use App\Models\PrazoRecebimento;
use Illuminate\Database\Seeder;

/**
 * As duas dimensoes curadas da taxa. Nao sao "dados reais" (etapa 04): sao
 * parte da estrutura, referenciadas por constante nos models.
 */
class DimensoesSeeder extends Seeder
{
    public function run(): void
    {
        // Regra 1: o prazo e dimensao da taxa. dias e null em parcela_a_parcela,
        // que e o motivo de isto ser tabela e nao coluna inteira.
        $prazos = [
            [PrazoRecebimento::NA_HORA, 'Na hora', 0, 'Cai na conta no momento da venda.'],
            [PrazoRecebimento::D1, 'Em 1 dia util', 1, 'Cai no primeiro dia util seguinte.'],
            [PrazoRecebimento::D14, 'Em 14 dias', 14, 'Antecipacao parcial contratada.'],
            [PrazoRecebimento::D30, 'Em 30 dias', 30, 'Prazo padrao do credito a vista.'],
            [PrazoRecebimento::PARCELA_A_PARCELA, 'Conforme as parcelas', null,
                'Cada parcela cai no mes correspondente, sem antecipacao.'],
        ];

        foreach ($prazos as $ordem => [$codigo, $nome, $dias, $descricao]) {
            PrazoRecebimento::updateOrCreate(
                ['codigo' => $codigo],
                ['nome_exibicao' => $nome, 'dias' => $dias, 'descricao' => $descricao, 'ordem' => $ordem]
            );
        }

        // As marcas publicam por grupo, nao por bandeira. Qual bandeira cai em
        // qual grupo e definido por marca, no pivot bandeira_marca.
        $grupos = [
            [GrupoBandeira::VISA_MASTER, 'Visa e Mastercard',
                'Tabela principal, quase sempre a de menor percentual.'],
            [GrupoBandeira::DEMAIS, 'Demais bandeiras',
                'Elo, Hipercard, Amex, Cabal e afins. Cada marca define a composicao.'],
            [GrupoBandeira::VOUCHER, 'Vale-refeicao e alimentacao',
                'Alelo, VR, Sodexo, Ticket. Operado como debito, com prazo e percentual proprios.'],
        ];

        foreach ($grupos as $ordem => [$codigo, $nome, $descricao]) {
            GrupoBandeira::updateOrCreate(
                ['codigo' => $codigo],
                ['nome_exibicao' => $nome, 'descricao' => $descricao, 'ordem' => $ordem]
            );
        }
    }
}
