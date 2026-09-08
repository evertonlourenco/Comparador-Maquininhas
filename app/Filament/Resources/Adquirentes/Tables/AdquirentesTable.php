<?php

namespace App\Filament\Resources\Adquirentes\Tables;

use App\Models\Adquirente;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdquirentesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('marcas'))
            ->columns([
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('marcas_count')
                    ->label('Marcas')
                    ->badge()
                    ->sortable(),
                TextColumn::make('observacao')
                    ->label('Observação')
                    ->limit(60)
                    ->toggleable(),
            ])
            ->defaultSort('nome')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->disabled(fn (Adquirente $record): bool => $record->estaEmUso())
                    ->tooltip(fn (Adquirente $record): ?string => $record->estaEmUso()
                        ? 'Há marcas apontando para este adquirente. Reaponte-as antes de excluir.'
                        : null),
            ]);
    }
}
