<?php

namespace App\Filament\Resources\RelatosTaxaIncorreta\Tables;

use App\Enums\StatusRevisao;
use App\Models\Marca;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RelatosTaxaIncorretaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('marca.nome')->label('Marca')->searchable()->sortable(),
                TextColumn::make('contexto')->label('Contexto')->limit(40)->placeholder('—'),
                TextColumn::make('mensagem')->label('Mensagem')->limit(60),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('created_at')->label('Recebido em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(StatusRevisao::class),
                SelectFilter::make('marca_id')->label('Marca')->options(fn () => Marca::orderBy('nome')->pluck('nome', 'id'))->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
