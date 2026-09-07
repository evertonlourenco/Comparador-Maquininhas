<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dimensao da taxa. As marcas publicam por grupo (Visa/Master numa tabela,
 * demais bandeiras noutra, voucher noutra) e nao por bandeira individual.
 * Qual bandeira cai em qual grupo e definido por marca, no pivot bandeira_marca.
 */
#[Table('grupos_bandeiras')]
#[Fillable(['codigo', 'nome_exibicao', 'descricao', 'ordem'])]
class GrupoBandeira extends Model
{
    public const VISA_MASTER = 'visa_master';
    public const DEMAIS = 'demais';
    public const VOUCHER = 'voucher';

    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    public function taxasDivulgadas(): HasMany
    {
        return $this->hasMany(TaxaDivulgada::class);
    }

    public function faixasReportadas(): HasMany
    {
        return $this->hasMany(FaixaReportada::class);
    }
}
