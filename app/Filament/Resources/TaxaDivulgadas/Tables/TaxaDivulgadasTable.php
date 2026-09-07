<?php

namespace App\Filament\Resources\TaxaDivulgadas\Tables;

use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TaxaDivulgadasTable
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
                TextColumn::make('grupoBandeira.nome_exibicao')
                    ->label('Bandeiras')
                    ->sortable(),
                TextColumn::make('prazoRecebimento.nome_exibicao')
                    ->label('Prazo')
                    ->sortable(),
                TextColumn::make('percentual')
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2, ',', '.').'%')
                    ->sortable(),
                TextColumn::make('data_verificacao')
                    ->label('Verificado em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->badge()
                    ->color(fn (TaxaDivulgada $record): string => $record->esta_fresca ? 'success' : 'danger')
                    ->description(fn (TaxaDivulgada $record): ?string => $record->esta_fresca
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
