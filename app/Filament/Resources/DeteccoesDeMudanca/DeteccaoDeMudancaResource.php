<?php

namespace App\Filament\Resources\DeteccoesDeMudanca;

use App\Filament\Resources\DeteccoesDeMudanca\Pages\EditDeteccaoDeMudanca;
use App\Filament\Resources\DeteccoesDeMudanca\Pages\ListDeteccoesDeMudanca;
use App\Filament\Resources\DeteccoesDeMudanca\Schemas\DeteccaoDeMudancaForm;
use App\Filament\Resources\DeteccoesDeMudanca\Tables\DeteccoesDeMudancaTable;
use App\Models\DeteccaoDeMudanca;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Etapa 13: fila de revisão do monitor de mudanças (repositório Node
 * separado). Só chega aqui por POST /api/monitor/deteccoes — sem create no
 * painel, no mesmo espírito de RelatoTaxaIncorretaResource.
 */
class DeteccaoDeMudancaResource extends Resource
{
    protected static ?string $model = DeteccaoDeMudanca::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'Monitor de mudanças';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'deteccoes-de-mudanca';

    protected static ?string $pluralModelLabel = 'Detecções';

    protected static ?string $modelLabel = 'Detecção';

    public static function form(Schema $schema): Schema
    {
        return DeteccaoDeMudancaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DeteccoesDeMudancaTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeteccoesDeMudanca::route('/'),
            'edit' => EditDeteccaoDeMudanca::route('/{record}/edit'),
        ];
    }
}
