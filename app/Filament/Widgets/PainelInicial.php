<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Cupons\CupomResource;
use App\Filament\Resources\Marcas\MarcaResource;
use App\Filament\Resources\TaxaDivulgadas\TaxaDivulgadaResource;
use App\Models\Cupom;
use App\Models\FaixaReportada;
use App\Models\Marca;
use App\Models\TaxaDivulgada;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class PainelInicial extends StatsOverviewWidget
{
    /** Alerta do painel: mais cedo que o selo de frescor (45 dias, regra 8) para dar tempo de agir. */
    private const DIAS_ALERTA_VERIFICACAO = 30;

    private const DIAS_ALERTA_CUPOM = 7;

    protected function getStats(): array
    {
        $limiteVerificacao = Carbon::today()->subDays(self::DIAS_ALERTA_VERIFICACAO);

        $taxasNaoVerificadas = TaxaDivulgada::query()->where('data_verificacao', '<', $limiteVerificacao)->count()
            + FaixaReportada::query()->where('data_verificacao', '<', $limiteVerificacao)->count();

        $cuponsVencendo = Cupom::query()
            ->vigentes()
            ->whereDate('valido_ate', '<=', Carbon::today()->addDays(self::DIAS_ALERTA_CUPOM))
            ->count();

        $marcasSemTaxa = Marca::query()
            ->whereDoesntHave('taxasDivulgadas')
            ->whereDoesntHave('faixasReportadas')
            ->count();

        return [
            Stat::make('Taxas não verificadas há +30 dias', $taxasNaoVerificadas)
                ->description('Somando taxas divulgadas e faixas reportadas')
                ->color($taxasNaoVerificadas > 0 ? 'danger' : 'success')
                ->url(TaxaDivulgadaResource::getUrl())
                ->icon('heroicon-o-exclamation-triangle'),
            Stat::make('Cupons vencendo em 7 dias', $cuponsVencendo)
                ->description('Cupons vigentes perto do vencimento')
                ->color($cuponsVencendo > 0 ? 'warning' : 'success')
                ->url(CupomResource::getUrl())
                ->icon('heroicon-o-ticket'),
            Stat::make('Marcas sem taxa cadastrada', $marcasSemTaxa)
                ->description('Nem taxa divulgada, nem faixa reportada')
                ->color($marcasSemTaxa > 0 ? 'warning' : 'success')
                ->url(MarcaResource::getUrl())
                ->icon('heroicon-o-building-storefront'),
        ];
    }
}
