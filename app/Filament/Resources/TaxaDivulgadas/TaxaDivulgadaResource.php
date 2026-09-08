<?php

namespace App\Filament\Resources\TaxaDivulgadas;

use App\Filament\Resources\TaxaDivulgadas\Pages\CreateTaxaDivulgada;
use App\Filament\Resources\TaxaDivulgadas\Pages\EditTaxaDivulgada;
use App\Filament\Resources\TaxaDivulgadas\Pages\LancamentoEmLote;
use App\Filament\Resources\TaxaDivulgadas\Pages\ListTaxaDivulgadas;
use App\Filament\Resources\TaxaDivulgadas\Schemas\TaxaDivulgadaForm;
use App\Filament\Resources\TaxaDivulgadas\Tables\TaxaDivulgadasTable;
use App\Models\TaxaDivulgada;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TaxaDivulgadaResource extends Resource
{
    protected static ?string $model = TaxaDivulgada::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|UnitEnum|null $navigationGroup = 'Taxas';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $modelLabel = 'Taxa divulgada';

    protected static ?string $pluralModelLabel = 'Taxas divulgadas';

    public static function form(Schema $schema): Schema
    {
        return TaxaDivulgadaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxaDivulgadasTable::configure($table);
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
            'index' => ListTaxaDivulgadas::route('/'),
            'create' => CreateTaxaDivulgada::route('/create'),
            'edit' => EditTaxaDivulgada::route('/{record}/edit'),
            'lancamento-em-lote' => LancamentoEmLote::route('/lancamento-em-lote'),
        ];
    }
}
