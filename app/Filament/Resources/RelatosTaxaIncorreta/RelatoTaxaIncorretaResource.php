<?php

namespace App\Filament\Resources\RelatosTaxaIncorreta;

use App\Filament\Resources\RelatosTaxaIncorreta\Pages\EditRelatoTaxaIncorreta;
use App\Filament\Resources\RelatosTaxaIncorreta\Pages\ListRelatosTaxaIncorreta;
use App\Filament\Resources\RelatosTaxaIncorreta\Schemas\RelatoTaxaIncorretaForm;
use App\Filament\Resources\RelatosTaxaIncorreta\Tables\RelatosTaxaIncorretaTable;
use App\Models\RelatoTaxaIncorreta;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Etapa 10: um relato por clique em "reportar taxa errada" (ver
 * x-formulario-taxa-incorreta). Staging operacional, sem create — só chega
 * por aqui quem veio do formulário público.
 */
class RelatoTaxaIncorretaResource extends Resource
{
    protected static ?string $model = RelatoTaxaIncorreta::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Relatos de lojistas';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'relatos-taxa-incorreta';

    protected static ?string $pluralModelLabel = 'Relatos de taxa incorreta';

    protected static ?string $modelLabel = 'Relato de taxa incorreta';

    public static function form(Schema $schema): Schema
    {
        return RelatoTaxaIncorretaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelatosTaxaIncorretaTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRelatosTaxaIncorreta::route('/'),
            'edit' => EditRelatoTaxaIncorreta::route('/{record}/edit'),
        ];
    }
}
