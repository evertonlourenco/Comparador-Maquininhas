<?php

namespace App\Filament\Resources\Equipamentos\Pages;

use App\Filament\Resources\Equipamentos\EquipamentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEquipamentos extends ListRecords
{
    protected static string $resource = EquipamentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
