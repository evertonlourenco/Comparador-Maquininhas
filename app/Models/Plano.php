<?php

namespace App\Models;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Regra 3. Guarda os custos da conta (regra 6, itens 1, 4, 5 e 6).
 * Adesao e aluguel ficam no pivot equipamento_plano, porque variam por plano
 * para o mesmo aparelho.
 */
#[Table('planos')]
#[Fillable([
    'marca_id', 'nome', 'slug', 'tipo_enquadramento',
    'faturamento_min', 'faturamento_max', 'compromisso',
    'mensalidade', 'tarifa_saque', 'tarifa_ted',
    'tarifa_pix_recebimento', 'tarifa_pix_envio',
    'taxa_antecipacao_mensal', 'condicao_isencao', 'status', 'ordem',
])]
class Plano extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'tipo_enquadramento' => TipoEnquadramento::class,
            'faturamento_min' => 'decimal:2',
            'faturamento_max' => 'decimal:2',
            'mensalidade' => 'decimal:2',
            'tarifa_saque' => 'decimal:2',
            'tarifa_ted' => 'decimal:2',
            'tarifa_pix_recebimento' => 'decimal:2',
            'tarifa_pix_envio' => 'decimal:2',
            'taxa_antecipacao_mensal' => 'decimal:4',
            'status' => StatusItem::class,
            'ordem' => 'integer',
        ];
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    /** Custos 2 e 3 da regra 6 vivem no pivot. */
    public function equipamentos(): BelongsToMany
    {
        return $this->belongsToMany(Equipamento::class, 'equipamento_plano')
            ->using(EquipamentoPlano::class)
            ->withPivot([
                'id', 'preco_adesao', 'preco_adesao_promocional',
                'aluguel_mensal', 'observacao', 'status',
            ])
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

    #[Scope]
    protected function ativos(Builder $query): void
    {
        $query->where('status', StatusItem::Ativo);
    }

    /**
     * So o enquadramento automatico usa faixa de faturamento; nos demais o
     * lojista opta (escolhido) ou negocia.
     */
    #[Scope]
    protected function paraFaturamento(Builder $query, float|string $faturamentoMensal): void
    {
        $query->where('tipo_enquadramento', TipoEnquadramento::Automatico)
            ->where(fn (Builder $q) => $q->whereNull('faturamento_min')
                ->orWhere('faturamento_min', '<=', $faturamentoMensal))
            ->where(fn (Builder $q) => $q->whereNull('faturamento_max')
                ->orWhere('faturamento_max', '>=', $faturamentoMensal));
    }
}
