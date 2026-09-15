<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\FaixaReportadas\FaixaReportadaResource;
use App\Filament\Resources\TaxaDivulgadas\TaxaDivulgadaResource;
use App\Support\Saude\FaixasDeFrescor;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Etapa 16, prioridade 5: expande o alerta único do Painel Inicial ("taxas
 * não verificadas há +30 dias") em faixa de idade — soma taxas_divulgadas e
 * faixas_reportadas, as duas classes de taxa da regra 4.
 */
class FrescorWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 6;

    protected function getStats(): array
    {
        $faixas = FaixasDeFrescor::contagemPorFaixa();

        return [
            Stat::make('Verificadas há até 30 dias', $faixas['fresca'])
                ->color('success')
                ->icon('heroicon-o-check-circle'),
            Stat::make('31 a 45 dias', $faixas['alerta_antecipado'])
                ->description('Alerta antecipado — ainda dentro dos 45 dias da regra 8')
                ->color($faixas['alerta_antecipado'] > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock'),
            Stat::make('46 a 90 dias', $faixas['desatualizada'])
                ->description('Selo de frescor já degradado')
                ->color($faixas['desatualizada'] > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-exclamation-triangle')
                ->url(TaxaDivulgadaResource::getUrl()),
            Stat::make('Mais de 90 dias', $faixas['muito_desatualizada'])
                ->description('Há muito tempo sem verificação')
                ->color($faixas['muito_desatualizada'] > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-fire')
                ->url(TaxaDivulgadaResource::getUrl()),
            Stat::make('Sem data de verificação', $faixas['sem_data'])
                ->description($faixas['sem_data'] > 0 ? 'Não deveria existir — regra 6' : 'Nenhuma')
                ->color($faixas['sem_data'] > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-question-mark-circle')
                ->url(FaixaReportadaResource::getUrl()),
        ];
    }
}
