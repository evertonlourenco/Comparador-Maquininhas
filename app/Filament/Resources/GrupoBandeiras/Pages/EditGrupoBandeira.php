<?php

namespace App\Filament\Resources\GrupoBandeiras\Pages;

use App\Filament\Resources\GrupoBandeiras\GrupoBandeiraResource;
use App\Models\GrupoBandeira;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGrupoBandeira extends EditRecord
{
    protected static string $resource = GrupoBandeiraResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (GrupoBandeira $record): bool => $record->estaReservado() || $record->estaEmUso()),
        ];
    }
}
