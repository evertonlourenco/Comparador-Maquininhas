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
        //
        // Os nomes de exibicao e as descricoes vao acentuados: eles saem na tela
        // publica do comparador (etapa 07) e no painel, e nao sao comentario.
        //
        // A quarta coluna e antecipacao_embutida (etapa 05, decisao 3): diz se
        // o percentual publicado para este prazo ja cobra o adiantamento. Os
        // tres prazos curtos so existem porque a marca antecipa o recebivel e
        // cobra por isso dentro do percentual - "em 14 dias" e literalmente
        // antecipacao parcial contratada. D+30 e o prazo natural do credito e
        // parcela a parcela e o fluxo sem antecipacao nenhuma.
        $prazos = [
            [PrazoRecebimento::NA_HORA, 'Na hora', 0, true, 'Cai na conta no momento da venda.'],
            [PrazoRecebimento::D1, 'Em 1 dia útil', 1, true, 'Cai no primeiro dia útil seguinte.'],
            [PrazoRecebimento::D14, 'Em 14 dias', 14, true, 'Antecipação parcial contratada.'],
            [PrazoRecebimento::D30, 'Em 30 dias', 30, false, 'Prazo padrão do crédito à vista.'],
            [PrazoRecebimento::PARCELA_A_PARCELA, 'Conforme as parcelas', null, false,
                'Cada parcela cai no mês correspondente, sem antecipação.'],
        ];

        foreach ($prazos as $ordem => [$codigo, $nome, $dias, $antecipacaoEmbutida, $descricao]) {
            PrazoRecebimento::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nome_exibicao' => $nome,
                    'dias' => $dias,
                    'antecipacao_embutida' => $antecipacaoEmbutida,
                    'descricao' => $descricao,
                    'ordem' => $ordem,
                ]
            );
        }

        // As marcas publicam por grupo, nao por bandeira. Qual bandeira cai em
        // qual grupo e definido por marca, no pivot bandeira_marca.
        $grupos = [
            [GrupoBandeira::VISA_MASTER, 'Visa e Mastercard',
                'Tabela principal, quase sempre a de menor percentual.'],
            [GrupoBandeira::DEMAIS, 'Demais bandeiras',
                'Elo, Hipercard, Amex, Cabal e afins. Cada marca define a composição.'],
            [GrupoBandeira::VOUCHER, 'Vale-refeição e alimentação',
                'Alelo, VR, Sodexo, Ticket. Operado como débito, com prazo e percentual próprios.'],
            // Etapa 05, decisao 1: o Pix nao tem bandeira, mas grupo_bandeira_id
            // e NOT NULL nas duas tabelas de taxa. Este grupo e o lugar dele.
            // Nunca entra no pivot bandeira_marca e nunca aceita taxa de cartao.
            [GrupoBandeira::PIX, 'Pix',
                'Grupo técnico: o Pix não passa por bandeira. Existe para a taxa de Pix ter '
                .'onde entrar sem tornar a coluna de grupo nula. Não agrupa bandeira nenhuma.'],
        ];

        foreach ($grupos as $ordem => [$codigo, $nome, $descricao]) {
            GrupoBandeira::updateOrCreate(
                ['codigo' => $codigo],
                ['nome_exibicao' => $nome, 'descricao' => $descricao, 'ordem' => $ordem]
            );
        }
    }
}
