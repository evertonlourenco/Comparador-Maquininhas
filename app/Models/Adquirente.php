<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regra 7: transparencia, nao deduplicacao. Marcas que dividem o adquirente
 * continuam concorrendo como opcoes independentes - suporte, atendimento,
 * politica de adesao e reputacao sao proprios de cada uma.
 */
#[Table('adquirentes')]
#[Fillable(['nome', 'slug', 'observacao'])]
class Adquirente extends Model
{
    public function marcas(): HasMany
    {
        return $this->hasMany(Marca::class);
    }

    /**
     * Marca soft-deletada continua sendo linha no banco, e o restrictOnDelete
     * da FK conta com ela. Por isso withTrashed: o painel precisa saber que o
     * DELETE vai falhar antes de tentar.
     */
    public function estaEmUso(): bool
    {
        return $this->marcas()->withTrashed()->exists();
    }
}
