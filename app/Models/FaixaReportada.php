<?php

namespace App\Models;

use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Models\Concerns\TemChaveDeTaxa;
use App\Models\Concerns\TemFrescor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Regra 4, classe B: para Cielo, Rede, GetNet e Stone, que nao publicam tabela.
 *
 * Nenhuma coluna se chama "percentual" - so mediana, minimo e maximo. Isso
 * torna impossivel um $taxa->percentual acidental numa view e obriga a
 * exibicao como faixa. Nunca exibir como numero exato.
 */
#[Table('faixas_reportadas')]
#[Fillable([
    'plano_id', 'grupo_bandeira_id', 'prazo_recebimento_id',
    'tipo_operacao', 'parcelas',
    'percentual_mediana', 'percentual_minimo', 'percentual_maximo',
    'n_relatos', 'periodo_inicio', 'periodo_fim', 'metodologia',
    'fonte_descricao', 'url_fonte', 'data_verificacao', 'verificado_por',
    'status', 'observacao',
])]
class FaixaReportada extends Model
{
    use TemChaveDeTaxa, TemFrescor;

    protected function casts(): array
    {
        return [
            'tipo_operacao' => TipoOperacao::class,
            'parcelas' => 'integer',
            'percentual_mediana' => 'decimal:4',
            'percentual_minimo' => 'decimal:4',
            'percentual_maximo' => 'decimal:4',
            'n_relatos' => 'integer',
            'periodo_inicio' => 'date',
            'periodo_fim' => 'date',
            'data_verificacao' => 'date',
            'status' => StatusPublicacao::class,
        ];
    }

    /**
     * Amplitude da faixa, em pontos percentuais. Faixa larga com poucos
     * relatos e sinal de que o dado ainda nao sustenta recomendacao.
     */
    protected function amplitude(): Attribute
    {
        return Attribute::get(fn (): string => bcsub(
            (string) $this->percentual_maximo,
            (string) $this->percentual_minimo,
            4
        ));
    }
}
