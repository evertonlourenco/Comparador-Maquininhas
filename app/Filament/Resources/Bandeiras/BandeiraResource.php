<?php

namespace App\Filament\Resources\Bandeiras;

use App\Filament\Resources\Bandeiras\Pages\CreateBandeira;
use App\Filament\Resources\Bandeiras\Pages\EditBandeira;
use App\Filament\Resources\Bandeiras\Pages\ListBandeiras;
use App\Filament\Resources\Bandeiras\Schemas\BandeiraForm;
use App\Filament\Resources\Bandeiras\Tables\BandeirasTable;
use App\Models\Bandeira;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * A bandeira em si nao carrega grupo: em qual grupo ela cai e decisao de cada
 * marca, no pivot bandeira_marca (ver BandeirasRelationManager em Marcas).
 */
class BandeiraResource extends Resource
{
    protected static ?string $model = Bandeira::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Dimensões';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return BandeiraForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BandeirasTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBandeiras::route('/'),
            'create' => CreateBandeira::route('/create'),
            'edit' => EditBandeira::route('/{record}/edit'),
        ];
    }
}
