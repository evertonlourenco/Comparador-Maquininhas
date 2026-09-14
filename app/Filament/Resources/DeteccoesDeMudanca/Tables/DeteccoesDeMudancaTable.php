<?php

namespace App\Filament\Resources\DeteccoesDeMudanca\Tables;

use App\Enums\CategoriaFonteMonitorada;
use App\Enums\StatusRevisao;
use App\Enums\TipoDeteccaoDeMudanca;
use App\Models\Marca;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DeteccoesDeMudancaTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('detectado_em')->label('Detectado em')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge()
                    ->color(fn (TipoDeteccaoDeMudanca $state): string => $state === TipoDeteccaoDeMudanca::Falha ? 'danger' : 'warning'),
                TextColumn::make('marca.nome')->label('Marca')->placeholder('—')->searchable()->sortable(),
                TextColumn::make('categoria')->label('Categoria'),
                TextColumn::make('resumo')->label('Resumo')->limit(60)->placeholder('—'),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->defaultSort('detectado_em', 'desc')
            ->filters([
                SelectFilter::make('status')->options(StatusRevisao::class),
                SelectFilter::make('tipo')->options(TipoDeteccaoDeMudanca::class),
                SelectFilter::make('categoria')->options(CategoriaFonteMonitorada::class),
                SelectFilter::make('marca_id')->label('Marca')->options(fn () => Marca::orderBy('nome')->pluck('nome', 'id'))->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
