<?php

namespace App\Filament\Resources\TaxaDivulgadas\Pages;

use App\Filament\Resources\TaxaDivulgadas\TaxaDivulgadaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaxaDivulgada extends EditRecord
{
    protected static string $resource = TaxaDivulgadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
