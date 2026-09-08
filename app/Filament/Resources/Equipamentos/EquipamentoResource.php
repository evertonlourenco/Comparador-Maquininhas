<?php

namespace App\Filament\Resources\Equipamentos;

use App\Filament\Resources\Equipamentos\Pages\CreateEquipamento;
use App\Filament\Resources\Equipamentos\Pages\EditEquipamento;
use App\Filament\Resources\Equipamentos\Pages\ListEquipamentos;
use App\Filament\Resources\Equipamentos\Schemas\EquipamentoForm;
use App\Filament\Resources\Equipamentos\Tables\EquipamentosTable;
use App\Models\Equipamento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EquipamentoResource extends Resource
{
    protected static ?string $model = Equipamento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDeviceTablet;

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'nome';

    public static function form(Schema $schema): Schema
    {
        return EquipamentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EquipamentosTable::configure($table);
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
            'index' => ListEquipamentos::route('/'),
            'create' => CreateEquipamento::route('/create'),
            'edit' => EditEquipamento::route('/{record}/edit'),
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
