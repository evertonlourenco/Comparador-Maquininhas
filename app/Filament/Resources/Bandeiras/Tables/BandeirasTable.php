<?php

namespace App\Filament\Resources\Bandeiras\Tables;

use App\Filament\Actions\BuscarImagemPorUrlAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BandeirasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount('marcas'))
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->square(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('marcas_count')
                    ->label('Marcas que aceitam')
                    ->badge()
                    ->sortable(),
                TextColumn::make('ordem')
                    ->numeric()
                    ->sortable(),
            ])
            ->defaultSort('ordem')
            ->reorderable('ordem')
            ->recordActions([
                EditAction::make(),
                BuscarImagemPorUrlAction::make('logo_path', 'bandeiras', 'logo'),
                DeleteAction::make()
                    ->modalDescription('Excluir a bandeira também a desvincula de todas as marcas que a aceitam. As taxas não são afetadas: elas são por grupo, não por bandeira.'),
            ]);
    }
}
