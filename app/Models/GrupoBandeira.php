<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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

    /**
     * Codigos referenciados por constante no codigo (DimensoesSeeder, gerador
     * de JSON). Renomear um deles quebra o que compara com a constante, entao
     * o painel oferece o cadastro de grupos novos mas tranca estes tres.
     */
    public const RESERVADOS = [self::VISA_MASTER, self::DEMAIS, self::VOUCHER];

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

    public function estaReservado(): bool
    {
        return in_array($this->codigo, self::RESERVADOS, true);
    }

    /**
     * O pivot bandeira_marca aponta para ca com nullOnDelete: apagar o grupo
     * nao daria erro, so esvaziaria o agrupamento das marcas em silencio.
     */
    public function estaEmUso(): bool
    {
        return $this->taxasDivulgadas()->exists()
            || $this->faixasReportadas()->exists()
            || DB::table('bandeira_marca')->where('grupo_bandeira_id', $this->getKey())->exists();
    }
}
