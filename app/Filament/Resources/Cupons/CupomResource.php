<?php

namespace App\Filament\Resources\Cupons;

use App\Filament\Resources\Cupons\Pages\CreateCupom;
use App\Filament\Resources\Cupons\Pages\EditCupom;
use App\Filament\Resources\Cupons\Pages\ListCupons;
use App\Filament\Resources\Cupons\Schemas\CupomForm;
use App\Filament\Resources\Cupons\Tables\CuponsTable;
use App\Models\Cupom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CupomResource extends Resource
{
    protected static ?string $model = Cupom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'codigo';

    /** Str::plural('cupom') dá "cupoms" (regra inglesa) — o certo em português é "cupons". */
    protected static ?string $slug = 'cupons';

    protected static ?string $pluralModelLabel = 'Cupons';

    protected static ?string $modelLabel = 'Cupom';

    public static function form(Schema $schema): Schema
    {
        return CupomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CuponsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCupons::route('/'),
            'create' => CreateCupom::route('/create'),
            'edit' => EditCupom::route('/{record}/edit'),
        ];
    }
}
