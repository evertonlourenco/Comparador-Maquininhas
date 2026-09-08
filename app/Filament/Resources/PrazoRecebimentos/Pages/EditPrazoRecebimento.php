<?php

namespace App\Filament\Resources\PrazoRecebimentos\Pages;

use App\Filament\Resources\PrazoRecebimentos\PrazoRecebimentoResource;
use App\Models\PrazoRecebimento;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPrazoRecebimento extends EditRecord
{
    protected static string $resource = PrazoRecebimentoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->disabled(fn (PrazoRecebimento $record): bool => $record->estaReservado() || $record->estaEmUso()),
        ];
    }
}
