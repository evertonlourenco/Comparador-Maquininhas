<?php

namespace App\Filament\Resources\FaixaReportadas\Pages;

use App\Filament\Resources\FaixaReportadas\FaixaReportadaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFaixaReportada extends EditRecord
{
    protected static string $resource = FaixaReportadaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
