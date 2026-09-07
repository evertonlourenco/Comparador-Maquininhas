<?php

namespace App\Filament\Resources\Planos\RelationManagers;

use App\Enums\StatusItem;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Regra 6, custos 2 e 3: adesão e aluguel são do par equipamento+plano, não
 * do aparelho. Único lugar que edita a linha de equipamento_plano.
 */
class EquipamentosRelationManager extends RelationManager
{
    protected static string $relationship = 'equipamentos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('preco_adesao')
                    ->label('Adesão')
                    ->numeric()
                    ->prefix('R$'),
                TextInput::make('preco_adesao_promocional')
                    ->label('Adesão promocional')
                    ->numeric()
                    ->prefix('R$'),
                TextInput::make('aluguel_mensal')
                    ->label('Aluguel mensal')
                    ->numeric()
                    ->prefix('R$'),
                Select::make('status')
                    ->options(StatusItem::class)
                    ->required()
                    ->default(StatusItem::Ativo),
                Textarea::make('observacao')
                    ->columnSpanFull()
                    ->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome'),
                TextColumn::make('pivot.preco_adesao')
                    ->label('Adesão')
                    ->money('BRL', locale: 'pt_BR'),
                TextColumn::make('pivot.preco_adesao_promocional')
                    ->label('Adesão promo.')
                    ->money('BRL', locale: 'pt_BR'),
                TextColumn::make('pivot.aluguel_mensal')
                    ->label('Aluguel')
                    ->money('BRL', locale: 'pt_BR'),
                TextColumn::make('pivot.status')
                    ->label('Status')
                    ->badge(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(
                        fn ($query) => $query->where('marca_id', $this->getOwnerRecord()->marca_id)
                    )
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('preco_adesao')->label('Adesão')->numeric()->prefix('R$'),
                        TextInput::make('preco_adesao_promocional')->label('Adesão promocional')->numeric()->prefix('R$'),
                        TextInput::make('aluguel_mensal')->label('Aluguel mensal')->numeric()->prefix('R$'),
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
