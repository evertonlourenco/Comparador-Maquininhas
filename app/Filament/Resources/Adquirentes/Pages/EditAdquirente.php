<?php

namespace App\Filament\Resources\Adquirentes\Pages;

use App\Filament\Resources\Adquirentes\AdquirenteResource;
use App\Models\Adquirente;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAdquirente extends EditRecord
{
    protected static string $resource = AdquirenteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (Adquirente $record): bool => $record->estaEmUso()),
        ];
    }
}
