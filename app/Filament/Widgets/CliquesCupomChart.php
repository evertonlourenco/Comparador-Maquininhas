<?php

namespace App\Filament\Widgets;

use App\Enums\TipoEventoCupom;
use App\Models\EventoCupom;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Etapa 16, prioridade 2: `eventos_cupom` existe desde a etapa 09 e nunca foi
 * visualizada. Este gráfico é só o primeiro corte (por dia) — o corte por
 * marca e por cupom sai em tabela, em CliquesCupomPorMarcaTable e
 * CliquesCupomPorCupomTable, porque um ranking não se lê bem em barra.
 */
class CliquesCupomChart extends ChartWidget
{
    protected const DIAS = 30;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Cliques em cupom — últimos 30 dias';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $inicio = Carbon::today()->subDays(self::DIAS - 1);

        $porDia = EventoCupom::query()
            ->where('created_at', '>=', $inicio->copy()->startOfDay())
            ->select([
                DB::raw('DATE(created_at) as dia'),
                'tipo_evento',
                DB::raw('COUNT(*) as total'),
            ])
            ->groupBy('dia', 'tipo_evento')
            ->get()
            ->groupBy('tipo_evento');

        $dias = collect(range(0, self::DIAS - 1))
            ->map(fn (int $i): string => $inicio->copy()->addDays($i)->toDateString());

        $serie = fn (TipoEventoCupom $tipo) => $dias->map(function (string $dia) use ($porDia, $tipo) {
            $linha = $porDia->get($tipo->value, collect())->firstWhere('dia', $dia);

            return $linha->total ?? 0;
        });

        return [
            'datasets' => [
                [
                    'label' => TipoEventoCupom::UsarCupom->getLabel(),
                    'data' => $serie(TipoEventoCupom::UsarCupom)->values(),
                    'backgroundColor' => '#16a34a',
                ],
                [
                    'label' => TipoEventoCupom::CopiarCodigo->getLabel(),
                    'data' => $serie(TipoEventoCupom::CopiarCodigo)->values(),
                    'backgroundColor' => '#d97706',
                ],
            ],
            'labels' => $dias->map(fn (string $dia): string => Carbon::parse($dia)->format('d/m'))->values(),
        ];
    }
}
