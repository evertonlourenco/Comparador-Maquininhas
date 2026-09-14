<?php

namespace App\Filament\Resources\DeteccoesDeMudanca\Pages;

use App\Filament\Resources\DeteccoesDeMudanca\DeteccaoDeMudancaResource;
use Filament\Resources\Pages\ListRecords;

class ListDeteccoesDeMudanca extends ListRecords
{
    protected static string $resource = DeteccaoDeMudancaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
