<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regra 1: o prazo e dimensao da taxa, nao atributo da marca. A mesma venda
 * tem percentual diferente conforme receber na hora, em 14 ou em 30 dias.
 *
 * dias e null em parcela_a_parcela - e o motivo de isto ser tabela e nao
 * uma coluna inteira na taxa.
 */
#[Table('prazos_recebimento')]
#[Fillable(['codigo', 'nome_exibicao', 'descricao', 'dias', 'antecipacao_embutida', 'ordem'])]
class PrazoRecebimento extends Model
{
    public const NA_HORA = 'na_hora';

    public const D1 = 'd_1';

    public const D14 = 'd_14';

    public const D30 = 'd_30';

    public const PARCELA_A_PARCELA = 'parcela_a_parcela';

    /** Referenciados por constante no codigo - ver GrupoBandeira::RESERVADOS. */
    public const RESERVADOS = [
        self::NA_HORA, self::D1, self::D14, self::D30, self::PARCELA_A_PARCELA,
    ];

    protected function casts(): array
    {
        return [
            'dias' => 'integer',
            'antecipacao_embutida' => 'boolean',
            'ordem' => 'integer',
        ];
    }

    /**
     * Etapa 05, decisao 3: quando o percentual da taxa ja embute a antecipacao
     * automatica, o motor nao pode somar a antecipacao avulsa do plano por
     * cima - seria cobrar o mesmo adiantamento duas vezes.
     */
    public function embuteAntecipacao(): bool
    {
        return (bool) $this->antecipacao_embutida;
    }

    /**
     * Quantos meses de antecipacao avulsa uma venda deste prazo custa, dado o
     * numero de parcelas.
     *
     * - Prazo com dias definidos: dias/30. D+30 = 1 mes, que e a convencao do
     *   proprio campo taxa_antecipacao_mensal (percentual ao mes).
     * - parcela_a_parcela (dias = null): a parcela i cai no mes i, entao
     *   antecipar a venda inteira custa
     *       soma(i = 1..n) de (V/n) x taxa x i  =  V x taxa x (n+1)/2
     *   ou seja, (n+1)/2 meses equivalentes. Para 1x da 1 mes, o que fecha
     *   com o caso de parcela unica.
     */
    public function mesesDeAntecipacao(int $parcelas): float
    {
        if ($this->dias !== null) {
            return $this->dias / 30;
        }

        return ($parcelas + 1) / 2;
    }

    public function taxasDivulgadas(): HasMany
    {
        return $this->hasMany(TaxaDivulgada::class);
    }

    public function faixasReportadas(): HasMany
    {
        return $this->hasMany(FaixaReportada::class);
    }

    public function estaReservado(): bool
    {
        return in_array($this->codigo, self::RESERVADOS, true);
    }

    public function estaEmUso(): bool
    {
        return $this->taxasDivulgadas()->exists() || $this->faixasReportadas()->exists();
    }
}
