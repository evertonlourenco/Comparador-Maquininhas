<?php

namespace App\Filament\Resources\TaxaDivulgadas\Pages;

use App\Filament\Actions\GerarJsonDoComparadorAction;
use App\Filament\Resources\TaxaDivulgadas\TaxaDivulgadaResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTaxaDivulgadas extends ListRecords
{
    protected static string $resource = TaxaDivulgadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lancamentoEmLote')
                ->label('Lançamento em lote')
                ->icon(Heroicon::OutlinedTableCells)
                ->color('gray')
                ->url(fn () => TaxaDivulgadaResource::getUrl('lancamento-em-lote')),
            GerarJsonDoComparadorAction::make(),
            CreateAction::make(),
        ];
    }
}
