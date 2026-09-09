<?php

namespace App\Filament\Resources\EventosCupom\Tables;

use App\Enums\PaginaOrigemCupom;
use App\Enums\TipoEventoCupom;
use App\Models\Marca;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventosCupomTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Quando')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('marca.nome')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable(),
                TextColumn::make('tipo_evento')
                    ->label('Evento')
                    ->badge(),
                TextColumn::make('pagina_origem')
                    ->label('Página de origem')
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->options(fn () => Marca::orderBy('nome')->pluck('nome', 'id'))
                    ->searchable(),
                SelectFilter::make('tipo_evento')->options(TipoEventoCupom::class),
                SelectFilter::make('pagina_origem')->options(PaginaOrigemCupom::class),
                Filter::make('periodo')
                    ->schema([
                        DatePicker::make('de')->label('De'),
                        DatePicker::make('ate')->label('Até'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['de'] ?? null, fn ($q, $de) => $q->whereDate('created_at', '>=', $de))
                            ->when($data['ate'] ?? null, fn ($q, $ate) => $q->whereDate('created_at', '<=', $ate));
                    })
                    ->columns(2),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
