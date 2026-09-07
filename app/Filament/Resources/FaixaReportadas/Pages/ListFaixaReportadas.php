<?php

namespace App\Filament\Resources\FaixaReportadas\Pages;

use App\Filament\Resources\FaixaReportadas\FaixaReportadaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFaixaReportadas extends ListRecords
{
    protected static string $resource = FaixaReportadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
