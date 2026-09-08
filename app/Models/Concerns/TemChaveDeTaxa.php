<?php

namespace App\Models\Concerns;

use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Regra 1: a chave de uma taxa e plano + tipo de operacao + grupo de bandeiras
 * + numero de parcelas + prazo de recebimento.
 *
 * Compartilhado por TaxaDivulgada e FaixaReportada - que sao tabelas separadas
 * (regra 4) mas indexadas pelas mesmas dimensoes.
 */
trait TemChaveDeTaxa
{
    public static function bootTemChaveDeTaxa(): void
    {
        // Decisao A: marca_id e denormalizado a partir do plano. Nunca e
        // preenchido a mao - aqui e o unico lugar que o escreve.
        static::saving(function (Model $taxa): void {
            if ($taxa->plano_id && ($taxa->isDirty('plano_id') || $taxa->marca_id === null)) {
                $taxa->marca_id = Plano::whereKey($taxa->plano_id)->value('marca_id');
            }
        });

        // Etapa 05, decisao 1: o grupo pix existe so para dar lugar a taxa de
        // Pix, que nao tem bandeira. Ele nao vale para cartao, e nenhum grupo
        // de cartao vale para Pix. Sem esta guarda o grupo tecnico viraria um
        // balde solto onde qualquer linha caberia.
        static::saving(function (Model $taxa): void {
            if (! $taxa->grupo_bandeira_id || ! $taxa->tipo_operacao) {
                return;
            }

            $codigo = GrupoBandeira::whereKey($taxa->grupo_bandeira_id)->value('codigo');

            if ($codigo === null) {
                return;
            }

            $ehPix = $taxa->tipo_operacao === TipoOperacao::Pix;
            $grupoEhDePix = $codigo === GrupoBandeira::PIX;

            if ($ehPix && ! $grupoEhDePix) {
                throw new DomainException(
                    'Taxa de Pix so entra no grupo "'.GrupoBandeira::PIX.'": Pix nao tem bandeira. '
                    .'Grupo recebido: "'.$codigo.'".'
                );
            }

            if (! $ehPix && $grupoEhDePix) {
                throw new DomainException(
                    'O grupo "'.GrupoBandeira::PIX.'" so aceita taxa de Pix. '
                    .'Tipo de operacao recebido: "'.$taxa->tipo_operacao->value.'".'
                );
            }
        });
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function plano(): BelongsTo
    {
        return $this->belongsTo(Plano::class);
    }

    public function grupoBandeira(): BelongsTo
    {
        return $this->belongsTo(GrupoBandeira::class);
    }

    public function prazoRecebimento(): BelongsTo
    {
        return $this->belongsTo(PrazoRecebimento::class);
    }

    public function verificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verificado_por');
    }

    /** Regra 10: so o que passou por aprovacao humana. */
    #[Scope]
    protected function publicadas(Builder $query): void
    {
        $query->where('status', StatusPublicacao::Publicado);
    }

    #[Scope]
    protected function daMarca(Builder $query, Marca|int $marca): void
    {
        $query->where('marca_id', $marca instanceof Marca ? $marca->getKey() : $marca);
    }

    #[Scope]
    protected function doTipo(Builder $query, TipoOperacao $tipo): void
    {
        $query->where('tipo_operacao', $tipo);
    }
}
