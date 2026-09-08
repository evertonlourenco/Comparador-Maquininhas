<?php

namespace App\Filament\Resources\Bandeiras\Pages;

use App\Filament\Resources\Bandeiras\BandeiraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBandeiras extends ListRecords
{
    protected static string $resource = BandeiraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
