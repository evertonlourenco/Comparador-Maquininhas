<?php

namespace App\Filament\Resources\PropostasRecebidas\Pages;

use App\Filament\Resources\PropostasRecebidas\PropostaRecebidaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPropostaRecebida extends EditRecord
{
    protected static string $resource = PropostaRecebidaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
