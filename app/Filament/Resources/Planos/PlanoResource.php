<?php

namespace App\Filament\Resources\Planos;

use App\Filament\Resources\Planos\Pages\CreatePlano;
use App\Filament\Resources\Planos\Pages\EditPlano;
use App\Filament\Resources\Planos\Pages\ListPlanos;
use App\Filament\Resources\Planos\RelationManagers\EquipamentosRelationManager;
use App\Filament\Resources\Planos\Schemas\PlanoForm;
use App\Filament\Resources\Planos\Tables\PlanosTable;
use App\Models\Plano;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PlanoResource extends Resource
{
    protected static ?string $model = Plano::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return PlanoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlanosTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            EquipamentosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlanos::route('/'),
            'create' => CreatePlano::route('/create'),
            'edit' => EditPlano::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
