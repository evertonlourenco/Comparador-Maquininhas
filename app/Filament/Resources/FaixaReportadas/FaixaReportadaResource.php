<?php

namespace App\Filament\Resources\FaixaReportadas;

use App\Filament\Resources\FaixaReportadas\Pages\CreateFaixaReportada;
use App\Filament\Resources\FaixaReportadas\Pages\EditFaixaReportada;
use App\Filament\Resources\FaixaReportadas\Pages\ListFaixaReportadas;
use App\Filament\Resources\FaixaReportadas\Schemas\FaixaReportadaForm;
use App\Filament\Resources\FaixaReportadas\Tables\FaixaReportadasTable;
use App\Models\FaixaReportada;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FaixaReportadaResource extends Resource
{
    protected static ?string $model = FaixaReportada::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Taxas';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Faixa reportada';

    protected static ?string $pluralModelLabel = 'Faixas reportadas';

    public static function form(Schema $schema): Schema
    {
        return FaixaReportadaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FaixaReportadasTable::configure($table);
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
            'index' => ListFaixaReportadas::route('/'),
            'create' => CreateFaixaReportada::route('/create'),
            'edit' => EditFaixaReportada::route('/{record}/edit'),
        ];
    }
}
