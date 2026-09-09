<?php

namespace App\Filament\Resources\RelatosTaxaIncorreta\Pages;

use App\Filament\Resources\RelatosTaxaIncorreta\RelatoTaxaIncorretaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRelatoTaxaIncorreta extends EditRecord
{
    protected static string $resource = RelatoTaxaIncorretaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
