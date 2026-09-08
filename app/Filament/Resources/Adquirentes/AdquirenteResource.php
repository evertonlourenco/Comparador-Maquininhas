<?php

namespace App\Filament\Resources\Adquirentes;

use App\Filament\Resources\Adquirentes\Pages\CreateAdquirente;
use App\Filament\Resources\Adquirentes\Pages\EditAdquirente;
use App\Filament\Resources\Adquirentes\Pages\ListAdquirentes;
use App\Filament\Resources\Adquirentes\Schemas\AdquirenteForm;
use App\Filament\Resources\Adquirentes\Tables\AdquirentesTable;
use App\Models\Adquirente;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Regra 7: quem processa por tras da marca. E informacao de transparencia,
 * nunca de deduplicacao - marcas que dividem adquirente seguem concorrendo
 * como opcoes independentes.
 */
class AdquirenteResource extends Resource
{
    protected static ?string $model = Adquirente::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected static string|UnitEnum|null $navigationGroup = 'Dimensões';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return AdquirenteForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdquirentesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdquirentes::route('/'),
            'create' => CreateAdquirente::route('/create'),
            'edit' => EditAdquirente::route('/{record}/edit'),
        ];
    }
}
