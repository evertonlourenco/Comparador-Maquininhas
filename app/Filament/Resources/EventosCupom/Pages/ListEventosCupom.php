<?php

namespace App\Filament\Resources\EventosCupom\Pages;

use App\Filament\Resources\EventosCupom\EventoCupomResource;
use Filament\Resources\Pages\ListRecords;

class ListEventosCupom extends ListRecords
{
    protected static string $resource = EventoCupomResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
