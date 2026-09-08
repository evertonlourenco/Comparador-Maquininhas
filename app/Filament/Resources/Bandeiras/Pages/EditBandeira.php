<?php

namespace App\Filament\Resources\Bandeiras\Pages;

use App\Filament\Resources\Bandeiras\BandeiraResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBandeira extends EditRecord
{
    protected static string $resource = BandeiraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
