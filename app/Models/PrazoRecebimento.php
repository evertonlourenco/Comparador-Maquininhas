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
#[Fillable(['codigo', 'nome_exibicao', 'descricao', 'dias', 'ordem'])]
class PrazoRecebimento extends Model
{
    public const NA_HORA = 'na_hora';
    public const D1 = 'd_1';
    public const D14 = 'd_14';
    public const D30 = 'd_30';
    public const PARCELA_A_PARCELA = 'parcela_a_parcela';

    protected function casts(): array
    {
        return [
            'dias' => 'integer',
            'ordem' => 'integer',
        ];
    }

    public function taxasDivulgadas(): HasMany
    {
        return $this->hasMany(TaxaDivulgada::class);
    }

    public function faixasReportadas(): HasMany
    {
        return $this->hasMany(FaixaReportada::class);
    }
}
