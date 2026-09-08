<?php

namespace App\Filament\Resources\GrupoBandeiras\Pages;

use App\Filament\Resources\GrupoBandeiras\GrupoBandeiraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrupoBandeiras extends ListRecords
{
    protected static string $resource = GrupoBandeiraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
