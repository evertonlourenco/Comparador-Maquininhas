<?php

namespace App\Support\Saude;

use App\Models\FaixaReportada;
use App\Models\TaxaDivulgada;
use App\Support\Monitor\ResumoSemanal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Etapa 16, prioridade 5: o Painel Inicial já soma "taxas não verificadas há
 * +30 dias" num número só. Isto expande para faixa de idade — os cortes são
 * os que o domínio já usa, não números novos inventados para o painel:
 * ResumoSemanal::DIAS_ALERTA_VERIFICACAO (30, alerta antecipado) e
 * TemFrescor::DIAS_ATE_DEGRADAR (45, regra 8). O corte de 90 é o único novo
 * aqui — o dobro do prazo de degradação, para separar "atrasada" de "há
 * muito tempo sem ninguém olhar".
 */
class FaixasDeFrescor
{
    public const DIAS_MUITO_DESATUALIZADA = 90;

    /** @return array<string, int> chave => contagem (taxas_divulgadas + faixas_reportadas) */
    public static function contagemPorFaixa(): array
    {
        $hoje = Carbon::today();

        // PHP não deixa acessar constante de trait pelo nome do trait
        // (`TemFrescor::DIAS_ATE_DEGRADAR` não compila) — só por uma classe
        // que o usa. TaxaDivulgada serve de âncora; FaixaReportada usa o
        // mesmo trait e teria o mesmo valor.
        $diasAteDegradar = TaxaDivulgada::DIAS_ATE_DEGRADAR;

        $faixas = [
            'fresca' => [null, ResumoSemanal::DIAS_ALERTA_VERIFICACAO],
            'alerta_antecipado' => [ResumoSemanal::DIAS_ALERTA_VERIFICACAO, $diasAteDegradar],
            'desatualizada' => [$diasAteDegradar, self::DIAS_MUITO_DESATUALIZADA],
            'muito_desatualizada' => [self::DIAS_MUITO_DESATUALIZADA, null],
        ];

        $contagem = [];

        foreach ($faixas as $chave => [$de, $ate]) {
            $contagem[$chave] = self::contar(TaxaDivulgada::query(), $hoje, $de, $ate)
                + self::contar(FaixaReportada::query(), $hoje, $de, $ate);
        }

        $contagem['sem_data'] = TaxaDivulgada::query()->whereNull('data_verificacao')->count()
            + FaixaReportada::query()->whereNull('data_verificacao')->count();

        return $contagem;
    }

    private static function contar(Builder $query, Carbon $hoje, ?int $diasDe, ?int $diasAte): int
    {
        $query->whereNotNull('data_verificacao');

        // dias_desde_verificacao = hoje - data_verificacao. Um intervalo
        // [diasDe, diasAte) de dias vira o intervalo oposto de datas.
        if ($diasAte !== null) {
            $query->where('data_verificacao', '>', $hoje->copy()->subDays($diasAte));
        }

        if ($diasDe !== null) {
            $query->where('data_verificacao', '<=', $hoje->copy()->subDays($diasDe));
        }

        return $query->count();
    }
}
