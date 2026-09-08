<?php

namespace App\Filament\Resources\PrazoRecebimentos\Tables;

use App\Models\PrazoRecebimento;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrazoRecebimentosTable
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
                TextColumn::make('dias')
                    ->label('Dias')
                    ->numeric()
                    ->placeholder('conforme as parcelas')
                    ->sortable(),
                IconColumn::make('reservado')
                    ->label('Curado')
                    ->boolean()
                    ->state(fn (PrazoRecebimento $record): bool => $record->estaReservado())
                    ->tooltip('Prazo referenciado por constante no código-fonte.'),
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
                    ->disabled(fn (PrazoRecebimento $record): bool => $record->estaReservado() || $record->estaEmUso())
                    ->tooltip(fn (PrazoRecebimento $record): ?string => match (true) {
                        $record->estaReservado() => 'Prazo curado: referenciado por constante no código-fonte.',
                        $record->estaEmUso() => 'Em uso por taxas ou faixas já cadastradas.',
                        default => null,
                    }),
            ]);
    }
}
