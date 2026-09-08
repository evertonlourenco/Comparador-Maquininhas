<?php

namespace App\Filament\Resources\GrupoBandeiras;

use App\Filament\Resources\GrupoBandeiras\Pages\CreateGrupoBandeira;
use App\Filament\Resources\GrupoBandeiras\Pages\EditGrupoBandeira;
use App\Filament\Resources\GrupoBandeiras\Pages\ListGrupoBandeiras;
use App\Filament\Resources\GrupoBandeiras\Schemas\GrupoBandeiraForm;
use App\Filament\Resources\GrupoBandeiras\Tables\GrupoBandeirasTable;
use App\Models\GrupoBandeira;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Dimensao da taxa, em tabela e nao em enum justamente para admitir um grupo
 * novo sem migration: se uma marca publicar Amex separada de Elo, cria-se o
 * grupo aqui e as taxas passam a poder referencia-lo.
 */
class GrupoBandeiraResource extends Resource
{
    protected static ?string $model = GrupoBandeira::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Dimensões';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'grupos-bandeiras';

    protected static ?string $modelLabel = 'Grupo de bandeiras';

    protected static ?string $pluralModelLabel = 'Grupos de bandeiras';

    protected static ?string $recordTitleAttribute = 'nome_exibicao';

    public static function form(Schema $schema): Schema
    {
        return GrupoBandeiraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GrupoBandeirasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGrupoBandeiras::route('/'),
            'create' => CreateGrupoBandeira::route('/create'),
            'edit' => EditGrupoBandeira::route('/{record}/edit'),
        ];
    }
}
