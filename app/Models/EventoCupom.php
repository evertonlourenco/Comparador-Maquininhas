<?php

namespace App\Models;

use App\Enums\PaginaOrigemCupom;
use App\Enums\TipoEventoCupom;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Etapa 09: um clique em "usar cupom" ou uma cópia de código, para
 * reconciliar com os relatórios dos parceiros no fim do mês. Log de eventos,
 * não entidade de domínio — não entra em CRUD do painel, só listagem.
 */
#[Table('eventos_cupom')]
#[Fillable(['marca_id', 'cupom_id', 'codigo', 'tipo_evento', 'pagina_origem'])]
class EventoCupom extends Model
{
    protected function casts(): array
    {
        return [
            'tipo_evento' => TipoEventoCupom::class,
            'pagina_origem' => PaginaOrigemCupom::class,
        ];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function cupom(): BelongsTo
    {
        return $this->belongsTo(Cupom::class);
    }
}
