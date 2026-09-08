<?php

namespace App\Filament\Resources\Planos\Tables;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Models\Marca;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class PlanosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('marca.nome')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo_enquadramento')
                    ->label('Enquadramento')
                    ->badge()
                    ->formatStateUsing(fn (TipoEnquadramento $state): string => $state->rotuloCurto())
                    // A promoção é o único enquadramento que expira, e o
                    // comparador a trata em bloco próprio. Vale destacar.
                    ->color(fn (TipoEnquadramento $state): string => $state->ehTemporario() ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('mensalidade')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('ordem')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordem')
            ->filters([
                SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->options(fn () => Marca::orderBy('nome')->pluck('nome', 'id'))
                    ->searchable(),
                SelectFilter::make('tipo_enquadramento')->options(TipoEnquadramento::class),
                SelectFilter::make('status')->options(StatusItem::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
