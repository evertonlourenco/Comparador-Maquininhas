<?php

namespace App\Filament\Resources\Equipamentos\Tables;

use App\Enums\StatusItem;
use App\Enums\TipoEquipamento;
use App\Filament\Actions\BuscarImagemPorUrlAction;
use App\Models\Marca;
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

class EquipamentosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('imagem_path')
                    ->label('Foto')
                    ->disk('public')
                    ->square(),
                TextColumn::make('marca.nome')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo')
                    ->badge()
                    ->sortable(),
                IconColumn::make('aceita_nfc')
                    ->label('NFC')
                    ->boolean(),
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
                SelectFilter::make('tipo')->options(TipoEquipamento::class),
                SelectFilter::make('status')->options(StatusItem::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                BuscarImagemPorUrlAction::make('imagem_path', 'equipamentos', 'foto'),
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
