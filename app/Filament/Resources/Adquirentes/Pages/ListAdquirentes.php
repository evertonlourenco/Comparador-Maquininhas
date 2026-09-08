<?php

namespace App\Filament\Resources\Adquirentes\Pages;

use App\Filament\Resources\Adquirentes\AdquirenteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdquirentes extends ListRecords
{
    protected static string $resource = AdquirenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
