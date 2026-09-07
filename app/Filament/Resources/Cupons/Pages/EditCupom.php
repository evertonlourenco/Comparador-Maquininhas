<?php

namespace App\Filament\Resources\Cupons\Pages;

use App\Filament\Resources\Cupons\CupomResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCupom extends EditRecord
{
    protected static string $resource = CupomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
