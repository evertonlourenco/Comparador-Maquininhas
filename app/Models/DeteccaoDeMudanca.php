<?php

namespace App\Models;

use App\Enums\CategoriaFonteMonitorada;
use App\Enums\StatusRevisao;
use App\Enums\TipoDeteccaoDeMudanca;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etapa 13: staging do monitor de mudancas — ver a migration para o porque
 * de nao existir "fonte_monitorada" como tabela neste banco. Uma linha por
 * mudanca detectada ou por falha de coleta, criada só pela API do monitor
 * (POST /api/monitor/deteccoes), nunca pelo painel.
 */
#[Table('deteccoes_de_mudanca')]
#[Fillable([
    'marca_id', 'fonte_id', 'categoria', 'tipo', 'url',
    'resumo', 'trecho_alterado', 'hash_anterior', 'hash_novo',
    'mensagem_erro', 'status', 'observacao_admin', 'detectado_em',
])]
class DeteccaoDeMudanca extends Model
{
    protected function casts(): array
    {
        return [
            'categoria' => CategoriaFonteMonitorada::class,
            'tipo' => TipoDeteccaoDeMudanca::class,
            'status' => StatusRevisao::class,
            'detectado_em' => 'datetime',
        ];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    #[Scope]
    protected function pendentes(Builder $query): void
    {
        $query->where('status', StatusRevisao::Pendente);
    }
}
