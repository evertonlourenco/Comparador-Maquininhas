<?php

namespace App\Models;

use App\Enums\StatusRevisao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etapa 10: um relato por clique em "reportar taxa errada", em qualquer
 * <x-tabela-taxas> do site. Staging operacional, no espirito de EventoCupom
 * — nao e entidade de dominio, so fila de revisao humana.
 */
#[Table('relatos_taxa_incorreta')]
#[Fillable([
    'marca_id', 'contexto', 'pagina_url', 'mensagem', 'email_contato',
    'status', 'observacao_admin',
])]
class RelatoTaxaIncorreta extends Model
{
    protected function casts(): array
    {
        return [
            'status' => StatusRevisao::class,
        ];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }
}
