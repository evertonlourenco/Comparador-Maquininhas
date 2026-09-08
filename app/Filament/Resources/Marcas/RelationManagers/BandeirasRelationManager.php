<?php

namespace App\Filament\Resources\Marcas\RelationManagers;

use App\Models\Bandeira;
use App\Models\GrupoBandeira;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Regra: o grupo de bandeiras mora no pivot, não em bandeiras — cada marca
 * decide onde Elo e Amex caem.
 */
class BandeirasRelationManager extends RelationManager
{
    protected static string $relationship = 'bandeiras';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('grupo_bandeira_id')
                    ->label('Grupo de bandeiras')
                    // O grupo tecnico do Pix nao agrupa bandeira nenhuma (etapa 05, decisao 1).
                    ->options(GrupoBandeira::query()->deCartao()->orderBy('ordem')->pluck('nome_exibicao', 'id'))
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome'),
                TextColumn::make('pivot.grupo_bandeira_id')
                    ->label('Grupo')
                    ->formatStateUsing(fn (?int $state): string => $state
                        ? GrupoBandeira::find($state)?->nome_exibicao ?? '—'
                        : '—'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelect(fn (Select $select) => $select->createOptionForm([
                        TextInput::make('nome')->required()->maxLength(80),
                        TextInput::make('slug')->required()->maxLength(80)->unique(Bandeira::class, 'slug'),
                    ]))
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Select::make('grupo_bandeira_id')
                            ->label('Grupo de bandeiras')
                            // O grupo tecnico do Pix nao agrupa bandeira nenhuma (etapa 05, decisao 1).
                            ->options(GrupoBandeira::query()->deCartao()->orderBy('ordem')->pluck('nome_exibicao', 'id'))
                            ->required(),
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
