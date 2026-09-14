<?php

namespace App\Filament\Resources\DeteccoesDeMudanca\Pages;

use App\Filament\Resources\DeteccoesDeMudanca\DeteccaoDeMudancaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDeteccaoDeMudanca extends EditRecord
{
    protected static string $resource = DeteccaoDeMudancaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
