<?php

namespace App\Models;

use App\Enums\StatusMarca;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use App\Support\Saude\CompletudeDaMarca;

#[Table('marcas')]
#[Fillable([
    'adquirente_id', 'nome', 'slug', 'site_url', 'logo_path', 'descricao', 'youtube_video_id',
    'reclame_aqui_nota', 'reclame_aqui_situacao', 'reclame_aqui_url', 'reclame_aqui_consultado_em',
    'publica_tabela', 'aceita_relatos', 'status', 'ordem',
    'link_ultimo_status', 'link_ultima_falha', 'link_quebrado', 'link_verificado_em',
    'link_confirmado_manualmente', 'aprovada_em',
])]
class Marca extends Model
{
    use SoftDeletes;

    public const RA_COM_NOTA = 'com_nota';

    public const RA_SEM_NOTA = 'sem_nota';

    public const RA_SEM_PERFIL = 'sem_perfil';

    public const SITUACOES_RECLAME_AQUI = [
        self::RA_COM_NOTA => 'Com nota',
        self::RA_SEM_NOTA => 'Perfil existe, ainda sem nota',
        self::RA_SEM_PERFIL => 'Sem perfil no Reclame Aqui',
    ];

    /** Linha antiga so com nota conta como "com nota"; sem nada, nulo (nao informado). */
    public function situacaoReclameAqui(): ?string
    {
        return $this->reclame_aqui_situacao ?? ($this->reclame_aqui_nota !== null ? self::RA_COM_NOTA : null);
    }

    protected function casts(): array
    {
        return [
            'reclame_aqui_nota' => 'decimal:1',
            'reclame_aqui_consultado_em' => 'date',
            'publica_tabela' => 'boolean',
            'aceita_relatos' => 'boolean',
            'status' => StatusMarca::class,
            'ordem' => 'integer',
            'link_quebrado' => 'boolean',
            'link_verificado_em' => 'datetime',
            'link_confirmado_manualmente' => 'boolean',
            'aprovada_em' => 'datetime',
        ];
    }

    /**
     * A trava (etapa 19, 17/09/2026): so vale como "true" quando alguem
     * clicou em aprovar E a marca continua completa agora — ver
     * App\Support\Saude\CompletudeDaMarca. Aprovar uma vez nao e permanente:
     * se um dado obrigatorio sumir depois (taxa removida, equipamento sem
     * preco), a marca sai do JSON sozinha na proxima geracao, sem ninguem
     * precisar lembrar de desaprovar.
     */
    protected function aprovada(): Attribute
    {
        return Attribute::get(fn (): bool => $this->aprovada_em !== null
            && $this->completude['completa']);
    }

    /**
     * @return array{completa: bool, pendencias: list<string>}
     */
    protected function completude(): Attribute
    {
        return Attribute::get(fn (): array => CompletudeDaMarca::avaliar($this));
    }

    #[Scope]
    protected function aprovadas(Builder $query): void
    {
        $query->whereNotNull('aprovada_em');
    }

    /** logo_path e caminho relativo no disco 'public' — nunca a URL pronta. */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null);
    }

    public function adquirente(): BelongsTo
    {
        return $this->belongsTo(Adquirente::class);
    }

    public function planos(): HasMany
    {
        return $this->hasMany(Plano::class);
    }

    public function equipamentos(): HasMany
    {
        return $this->hasMany(Equipamento::class);
    }

    public function cupons(): HasMany
    {
        return $this->hasMany(Cupom::class);
    }

    /** Regra 7: bandeiras aceitas. O grupo de cada uma e definido no pivot. */
    public function bandeiras(): BelongsToMany
    {
        return $this->belongsToMany(Bandeira::class, 'bandeira_marca')
            ->withPivot('grupo_bandeira_id')
            ->withTimestamps();
    }

    public function taxasDivulgadas(): HasMany
    {
        return $this->hasMany(TaxaDivulgada::class);
    }

    public function faixasReportadas(): HasMany
    {
        return $this->hasMany(FaixaReportada::class);
    }

    /**
     * Regra 4: marca que nao publica tabela (Cielo, Rede, GetNet, Stone) so
     * admite faixa_reportada. As duas classes nunca se misturam.
     */
    public function aceitaTaxaDivulgada(): bool
    {
        return (bool) $this->publica_tabela;
    }

    #[Scope]
    protected function ativas(Builder $query): void
    {
        $query->where('status', StatusMarca::Ativa);
    }

    /**
     * A trava (etapa 19, 17/09/2026 — sem exceção desde a revisão do mesmo
     * dia), para as páginas públicas que consultam o banco direto (etapas
     * 08/09: `/maquininha/{slug}`, `/maquininhas`, `/cupom/{slug}`,
     * `/cupons`). A home lê o JSON estático (regra 9) e é
     * `CatalogoDoComparador::apenasAprovadasECompletas()` que aplica a mesma
     * regra lá. Marca só aparece com `aprovada_em` preenchido — inclusive a
     * que não tem taxa nem faixa nenhuma ainda, que antes era exceção e
     * deixou de ser: o Everton pediu o mesmo critério para todas, sem "sem
     * dado publicado" como estado visível sem aprovação.
     *
     * De propósito NÃO confere completude de novo aqui (o que exigiria rodar
     * o motor por marca a cada visita) — a completude só vale no momento do
     * clique em "Aprovar marca" e na geração do JSON, que são ações raras,
     * não páginas de visitante.
     */
    #[Scope]
    protected function visiveisNoSite(Builder $query): void
    {
        $query->whereNotNull('aprovada_em');
    }

    /** Etapa 10: marca que aparece no select de /enviar-proposta. */
    #[Scope]
    protected function aceitamRelatos(Builder $query): void
    {
        $query->where('aceita_relatos', true);
    }

    /** Etapa 16: link de afiliado (site_url) que o verificador marcou como fora do ar. */
    #[Scope]
    protected function comLinkQuebrado(Builder $query): void
    {
        $query->where('link_quebrado', true);
    }

    public function propostasRecebidas(): HasMany
    {
        return $this->hasMany(PropostaRecebida::class);
    }

    public function relatosTaxaIncorreta(): HasMany
    {
        return $this->hasMany(RelatoTaxaIncorreta::class);
    }
}
