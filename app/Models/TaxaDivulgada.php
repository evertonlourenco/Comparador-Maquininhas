<?php

namespace App\Models;

use App\Enums\FonteTipo;
use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Models\Concerns\TemChaveDeTaxa;
use App\Models\Concerns\TemFrescor;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Regra 4, classe A: taxa publicada pela marca.
 *
 * Vive em tabela separada de FaixaReportada de proposito. Se as duas classes
 * dividissem a mesma tabela, um dia uma mediana de relatos seria lida como
 * numero publicado - e o site exibiria como fato o que e estimativa.
 */
#[Table('taxas_divulgadas')]
#[Fillable([
    'plano_id', 'grupo_bandeira_id', 'prazo_recebimento_id',
    'tipo_operacao', 'parcelas', 'percentual', 'valor_fixo', 'condicao',
    'url_fonte', 'fonte_tipo', 'data_verificacao', 'verificado_por',
    'status', 'observacao',
])]
class TaxaDivulgada extends Model
{
    use TemChaveDeTaxa, TemFrescor;

    protected function casts(): array
    {
        return [
            'tipo_operacao' => TipoOperacao::class,
            'parcelas' => 'integer',
            'percentual' => 'decimal:4',
            'valor_fixo' => 'decimal:2',
            'fonte_tipo' => FonteTipo::class,
            'data_verificacao' => 'date',
            'status' => StatusPublicacao::class,
        ];
    }

    protected static function booted(): void
    {
        // Regra 4: marca que nao publica tabela (Cielo, Rede, GetNet, Stone)
        // nunca ganha linha aqui - o dado dela e faixa reportada.
        // Roda depois do hook da trait, que ja preencheu marca_id.
        static::saving(function (self $taxa): void {
            if (! $taxa->marca_id) {
                return;
            }

            $publicaTabela = Marca::withTrashed()->whereKey($taxa->marca_id)->value('publica_tabela');

            if ($publicaTabela !== null && ! $publicaTabela) {
                throw new DomainException(
                    "A marca #{$taxa->marca_id} nao publica tabela de taxas. "
                    .'Cadastre o dado como FaixaReportada (regra 4).'
                );
            }
        });
    }
}
