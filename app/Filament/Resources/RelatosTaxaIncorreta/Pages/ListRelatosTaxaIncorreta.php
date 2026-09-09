<?php

namespace App\Filament\Resources\RelatosTaxaIncorreta\Pages;

use App\Filament\Resources\RelatosTaxaIncorreta\RelatoTaxaIncorretaResource;
use Filament\Resources\Pages\ListRecords;

class ListRelatosTaxaIncorreta extends ListRecords
{
    protected static string $resource = RelatoTaxaIncorretaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
