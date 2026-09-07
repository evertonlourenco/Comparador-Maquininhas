<?php

namespace App\Filament\Resources\Planos\Pages;

use App\Filament\Resources\Planos\PlanoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlanos extends ListRecords
{
    protected static string $resource = PlanoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
