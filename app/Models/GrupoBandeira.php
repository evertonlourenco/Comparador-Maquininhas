<?php

namespace App\Models;

use App\Enums\TipoOperacao;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
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
     * Etapa 05, decisao 1. O Pix nao tem bandeira, e grupo_bandeira_id e NOT
     * NULL nas duas tabelas de taxa - entao a taxa de Pix nao tinha onde
     * entrar. Das duas saidas possiveis, esta e a escolhida:
     *
     * - Tornar a coluna nullable custaria a chave unica da regra 1: tanto no
     *   MySQL quanto no SQLite, NULL e distinto de NULL dentro de um indice
     *   UNIQUE. A mesma linha de Pix poderia ser gravada duas vezes para o
     *   mesmo plano e prazo, sem o banco reclamar, e todo leitor passaria a
     *   precisar de LEFT JOIN e de tratar o nulo.
     * - Um grupo proprio mantem a coluna NOT NULL, mantem a chave unica
     *   valendo e nao mexe em migration nenhuma - grupo e linha, nao enum.
     *
     * O que essa escolha custa: uma linha na tabela "grupos de bandeiras" que
     * nao agrupa bandeira nenhuma. O preco e pago com duas guardas - o grupo
     * pix nunca aparece no pivot bandeira_marca (o cadastro de bandeiras da
     * marca o esconde) e nenhuma taxa de cartao pode apontar para ele, nem
     * uma taxa de Pix apontar para outro (TemChaveDeTaxa).
     *
     * O que nao se fez: empurrar Pix para dentro de visa_master. Isso somaria
     * Pix no mesmo balde do cartao e faria o comparador exibir a taxa de Pix
     * como se fosse de Visa.
     */
    public const PIX = 'pix';

    /**
     * Codigos referenciados por constante no codigo (DimensoesSeeder, gerador
     * de JSON, motor de calculo). Renomear um deles quebra o que compara com
     * a constante, entao o painel oferece o cadastro de grupos novos mas
     * tranca estes.
     */
    public const RESERVADOS = [self::VISA_MASTER, self::DEMAIS, self::VOUCHER, self::PIX];

    /**
     * Grupos que agrupam bandeira de verdade - os unicos que fazem sentido no
     * pivot bandeira_marca e numa taxa de cartao.
     */
    public const DE_CARTAO = [self::VISA_MASTER, self::DEMAIS, self::VOUCHER];

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

    /** O grupo tecnico do Pix nao agrupa bandeira e nao entra no pivot. */
    public function ehDePix(): bool
    {
        return $this->codigo === self::PIX;
    }

    /**
     * Todo grupo que agrupa bandeira de verdade - inclusive os criados no
     * painel. E o complemento de dePix(), nao a lista fixa DE_CARTAO: um
     * grupo "amex_isolada" cadastrado amanha precisa aparecer aqui sozinho.
     */
    #[Scope]
    protected function deCartao(Builder $query): void
    {
        $query->where('codigo', '!=', self::PIX);
    }

    #[Scope]
    protected function dePix(Builder $query): void
    {
        $query->where('codigo', self::PIX);
    }

    /** Os grupos que aceitam uma taxa daquele tipo de operacao. */
    #[Scope]
    protected function paraOperacao(Builder $query, TipoOperacao|string|null $tipo): void
    {
        $tipo = $tipo instanceof TipoOperacao ? $tipo : ($tipo ? TipoOperacao::tryFrom($tipo) : null);

        $tipo === TipoOperacao::Pix ? $query->dePix() : $query->deCartao();
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
