<?php

namespace App\Models;

use App\Enums\SegmentoNegocio;
use App\Enums\StatusRevisao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etapa 10: staging da captacao de relatos, formulario publico e anonimo em
 * /enviar-proposta. Nunca vira faixa_reportada sozinha — um humano le no
 * painel e cadastra a faixa a mao, se decidir que o relato sustenta isso
 * (regra 10, na sua forma mais forte: aqui nem publicacao semiautomatica ha).
 */
#[Table('propostas_recebidas')]
#[Fillable([
    'marca_id', 'prazo_recebimento_id', 'taxas_relatadas', 'mensalidade',
    'data_proposta', 'estado', 'segmento', 'faturamento_aproximado',
    'anexo_caminho', 'anexo_mime', 'consentimento_uso_agregado',
    'status', 'observacao_admin',
])]
class PropostaRecebida extends Model
{
    protected function casts(): array
    {
        return [
            'taxas_relatadas' => 'array',
            'mensalidade' => 'decimal:2',
            'data_proposta' => 'date',
            'faturamento_aproximado' => 'decimal:2',
            'consentimento_uso_agregado' => 'boolean',
            'segmento' => SegmentoNegocio::class,
            'status' => StatusRevisao::class,
        ];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function prazoRecebimento(): BelongsTo
    {
        return $this->belongsTo(PrazoRecebimento::class);
    }
}
