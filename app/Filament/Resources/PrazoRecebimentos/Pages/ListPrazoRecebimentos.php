<?php

namespace App\Filament\Resources\PrazoRecebimentos\Pages;

use App\Filament\Resources\PrazoRecebimentos\PrazoRecebimentoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPrazoRecebimentos extends ListRecords
{
    protected static string $resource = PrazoRecebimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
