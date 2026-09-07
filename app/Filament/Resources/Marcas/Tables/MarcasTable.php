<?php

namespace App\Filament\Resources\Marcas\Tables;

use App\Enums\StatusMarca;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MarcasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->square(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('adquirente.nome')
                    ->label('Adquirente')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('publica_tabela')
                    ->label('Publica tabela')
                    ->boolean(),
                TextColumn::make('taxas_divulgadas_count')
                    ->label('Taxas')
                    ->counts('taxasDivulgadas')
                    ->sortable(),
                TextColumn::make('faixas_reportadas_count')
                    ->label('Faixas')
                    ->counts('faixasReportadas')
                    ->sortable(),
                TextColumn::make('reclame_aqui_nota')
                    ->label('RA')
                    ->numeric(1)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('ordem')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordem')
            ->filters([
                SelectFilter::make('status')->options(StatusMarca::class),
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
