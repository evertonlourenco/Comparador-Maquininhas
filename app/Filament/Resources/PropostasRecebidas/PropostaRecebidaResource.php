<?php

namespace App\Filament\Resources\PropostasRecebidas;

use App\Filament\Resources\PropostasRecebidas\Pages\EditPropostaRecebida;
use App\Filament\Resources\PropostasRecebidas\Pages\ListPropostasRecebidas;
use App\Filament\Resources\PropostasRecebidas\Schemas\PropostaRecebidaForm;
use App\Filament\Resources\PropostasRecebidas\Tables\PropostasRecebidasTable;
use App\Models\PropostaRecebida;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Etapa 10: staging da captação de relatos (/enviar-proposta). Sem create —
 * só chega por aqui quem veio do formulário público. Nunca vira
 * faixa_reportada sozinha (regra 10): um humano lê, decide e cadastra a
 * faixa a mão, se for o caso.
 */
class PropostaRecebidaResource extends Resource
{
    protected static ?string $model = PropostaRecebida::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Relatos de lojistas';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'propostas-recebidas';

    protected static ?string $pluralModelLabel = 'Propostas recebidas';

    protected static ?string $modelLabel = 'Proposta recebida';

    public static function form(Schema $schema): Schema
    {
        return PropostaRecebidaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PropostasRecebidasTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPropostasRecebidas::route('/'),
            'edit' => EditPropostaRecebida::route('/{record}/edit'),
        ];
    }
}
