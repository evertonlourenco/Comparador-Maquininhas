<?php

namespace App\Filament\Resources\PrazoRecebimentos;

use App\Filament\Resources\PrazoRecebimentos\Pages\CreatePrazoRecebimento;
use App\Filament\Resources\PrazoRecebimentos\Pages\EditPrazoRecebimento;
use App\Filament\Resources\PrazoRecebimentos\Pages\ListPrazoRecebimentos;
use App\Filament\Resources\PrazoRecebimentos\Schemas\PrazoRecebimentoForm;
use App\Filament\Resources\PrazoRecebimentos\Tables\PrazoRecebimentosTable;
use App\Models\PrazoRecebimento;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Regra 1: o prazo e dimensao da taxa, nao atributo da marca. Cadastrar um
 * prazo novo aqui faz ele aparecer sozinho como secao no lancamento em lote.
 */
class PrazoRecebimentoResource extends Resource
{
    protected static ?string $model = PrazoRecebimento::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Dimensões';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'prazos-recebimento';

    protected static ?string $modelLabel = 'Prazo de recebimento';

    protected static ?string $pluralModelLabel = 'Prazos de recebimento';

    protected static ?string $recordTitleAttribute = 'nome_exibicao';

    public static function form(Schema $schema): Schema
    {
        return PrazoRecebimentoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PrazoRecebimentosTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPrazoRecebimentos::route('/'),
            'create' => CreatePrazoRecebimento::route('/create'),
            'edit' => EditPrazoRecebimento::route('/{record}/edit'),
        ];
    }
}
