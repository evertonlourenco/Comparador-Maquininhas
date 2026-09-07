<?php

namespace App\Filament\Resources\FaixaReportadas\Tables;

use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Models\FaixaReportada;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\PrazoRecebimento;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FaixaReportadasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('marca.nome')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plano.nome')
                    ->label('Plano')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo_operacao')
                    ->badge()
                    ->sortable(),
                TextColumn::make('parcelas')
                    ->label('Parc.')
                    ->formatStateUsing(fn (int $state): string => "{$state}x")
                    ->sortable(),
                TextColumn::make('percentual_minimo')
                    ->label('Mín.')
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2, ',', '.').'%'),
                TextColumn::make('percentual_mediana')
                    ->label('Mediana')
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2, ',', '.').'%'),
                TextColumn::make('percentual_maximo')
                    ->label('Máx.')
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2, ',', '.').'%'),
                TextColumn::make('n_relatos')
                    ->label('Relatos')
                    ->sortable(),
                TextColumn::make('data_verificacao')
                    ->label('Verificado em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn (FaixaReportada $record): string => $record->esta_fresca ? 'success' : 'danger')
                    ->description(fn (FaixaReportada $record): ?string => $record->esta_fresca
                        ? null
                        : "{$record->dias_desde_verificacao} dias sem verificar"),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('data_verificacao', 'desc')
            ->filters([
                SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->options(fn () => Marca::orderBy('nome')->pluck('nome', 'id'))
                    ->searchable(),
                SelectFilter::make('tipo_operacao')->options(TipoOperacao::class),
                SelectFilter::make('grupo_bandeira_id')
                    ->label('Grupo de bandeiras')
                    ->options(GrupoBandeira::pluck('nome_exibicao', 'id')),
                SelectFilter::make('prazo_recebimento_id')
                    ->label('Prazo')
                    ->options(PrazoRecebimento::pluck('nome_exibicao', 'id')),
                SelectFilter::make('status')->options(StatusPublicacao::class),
                Filter::make('desatualizadas')
                    ->label('Desatualizada (> 45 dias)')
                    ->toggle()
                    ->query(fn ($query) => $query->desatualizadas()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
