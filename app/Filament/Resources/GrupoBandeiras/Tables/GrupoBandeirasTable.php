<?php

namespace App\Filament\Resources\GrupoBandeiras\Tables;

use App\Models\GrupoBandeira;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GrupoBandeirasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['taxasDivulgadas', 'faixasReportadas']))
            ->columns([
                TextColumn::make('nome_exibicao')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('codigo')
                    ->label('Código')
                    ->badge()
                    ->searchable(),
                IconColumn::make('reservado')
                    ->label('Curado')
                    ->boolean()
                    ->state(fn (GrupoBandeira $record): bool => $record->estaReservado())
                    ->tooltip('Grupo referenciado por constante no código-fonte.'),
                TextColumn::make('taxas_divulgadas_count')
                    ->label('Taxas')
                    ->badge()
                    ->sortable(),
                TextColumn::make('faixas_reportadas_count')
                    ->label('Faixas')
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
                DeleteAction::make()
                    ->disabled(fn (GrupoBandeira $record): bool => $record->estaReservado() || $record->estaEmUso())
                    ->tooltip(fn (GrupoBandeira $record): ?string => match (true) {
                        $record->estaReservado() => 'Grupo curado: referenciado por constante no código-fonte.',
                        $record->estaEmUso() => 'Em uso por taxas, faixas ou pelo agrupamento de bandeiras de alguma marca.',
                        default => null,
                    }),
            ]);
    }
}
