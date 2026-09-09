<?php

namespace App\Filament\Resources\PropostasRecebidas\Pages;

use App\Filament\Resources\PropostasRecebidas\PropostaRecebidaResource;
use Filament\Resources\Pages\ListRecords;

class ListPropostasRecebidas extends ListRecords
{
    protected static string $resource = PropostaRecebidaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
