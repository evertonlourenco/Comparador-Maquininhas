<?php

namespace App\Filament\Widgets;

use App\Models\Adquirente;
use App\Models\Bandeira;
use App\Models\Cupom;
use App\Models\Equipamento;
use App\Models\EventoCupom;
use App\Models\FaixaReportada;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PropostaRecebida;
use App\Models\RelatoTaxaIncorreta;
use App\Models\TaxaDivulgada;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Etapa 16, prioridade 4: tamanho por tabela (direto do information_schema,
 * funciona igual em MySQL local e MariaDB de produção) e contagem por
 * entidade do domínio — não existia lugar nenhum pra ver isso de relance.
 */
class BancoWidget extends Widget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.banco';

    /**
     * `information_schema.tables` é MySQL/MariaDB — os dois motores que este
     * projeto usa de verdade (local e produção, ver CLAUDE.md). Os testes
     * rodam em SQLite (phpunit.xml), que não tem esse schema; a coleção vazia
     * ali vira "sem dado" na view, nunca uma exceção quebrando o painel.
     *
     * @return Collection<int, array{tabela: string, linhas: int, tamanho_mb: float}>
     */
    public function getTabelas(): Collection
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return collect();
        }

        return collect(DB::select(
            'select table_name as tabela,
                    table_rows as linhas,
                    round(((data_length + index_length) / 1024 / 1024), 2) as tamanho_mb
             from information_schema.tables
             where table_schema = ?
             order by (data_length + index_length) desc',
            [DB::connection()->getDatabaseName()],
        ))->map(fn (object $linha): array => (array) $linha);
    }

    /** @return array<string, int> */
    public function getEntidades(): array
    {
        return [
            'Marcas' => Marca::withTrashed()->count(),
            'Adquirentes' => Adquirente::count(),
            'Bandeiras' => Bandeira::count(),
            'Planos' => Plano::count(),
            'Equipamentos' => Equipamento::count(),
            'Taxas divulgadas' => TaxaDivulgada::count(),
            'Faixas reportadas' => FaixaReportada::count(),
            'Cupons' => Cupom::count(),
            'Eventos de cupom' => EventoCupom::count(),
            'Propostas recebidas' => PropostaRecebida::count(),
            'Relatos de taxa incorreta' => RelatoTaxaIncorreta::count(),
            'Usuários do painel' => User::count(),
        ];
    }
}
