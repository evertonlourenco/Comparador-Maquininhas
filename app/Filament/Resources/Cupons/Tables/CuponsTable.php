<?php

namespace App\Filament\Resources\Cupons\Tables;

use App\Enums\StatusItem;
use App\Models\Cupom;
use App\Models\Marca;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class CuponsTable
{
    /** Regra 5, destaque visual: vence em menos de 7 dias. */
    private const DIAS_ALERTA_VENCIMENTO = 7;

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('marca.nome')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('equipamento.nome')
                    ->label('Equipamento')
                    ->default('Catálogo todo')
                    ->searchable(),
                TextColumn::make('codigo')
                    ->weight(FontWeight::Bold)
                    ->searchable(),
                TextColumn::make('valor')
                    ->formatStateUsing(fn (Cupom $record): string => match (true) {
                        // Etapa 17: PagBank/Mercado Pago tem desconto real,
                        // valor desconhecido - a descricao e que carrega isso.
                        $record->valor === null => 'Variável',
                        $record->tipo_desconto?->value === 'percentual' => number_format((float) $record->valor, 2, ',', '.').'%',
                        default => 'R$ '.number_format((float) $record->valor, 2, ',', '.'),
                    }),
                TextColumn::make('valido_ate')
                    ->label('Válido até')
                    ->date('d/m/Y')
                    ->placeholder('Sem prazo')
                    ->sortable()
                    ->badge()
                    ->color(function (Cupom $record): string {
                        if ($record->valido_ate === null) {
                            return 'success';
                        }

                        $hoje = Carbon::today();

                        if ($record->valido_ate->lt($hoje)) {
                            return 'gray';
                        }

                        return $hoje->diffInDays($record->valido_ate) <= self::DIAS_ALERTA_VENCIMENTO
                            ? 'danger'
                            : 'success';
                    })
                    ->description(function (Cupom $record): ?string {
                        if ($record->valido_ate === null) {
                            return null;
                        }

                        $hoje = Carbon::today();

                        if ($record->valido_ate->lt($hoje)) {
                            return 'Vencido';
                        }

                        $dias = $hoje->diffInDays($record->valido_ate);

                        return $dias <= self::DIAS_ALERTA_VENCIMENTO
                            ? "Vence em {$dias} dia(s)"
                            : null;
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('link_quebrado')
                    ->label('Link')
                    ->badge()
                    ->formatStateUsing(fn (Cupom $record): string => match (true) {
                        $record->link_verificado_em === null => 'Não verificado',
                        (bool) $record->link_quebrado => 'Quebrado',
                        (bool) $record->link_confirmado_manualmente && (int) $record->link_ultimo_status >= 400 => 'Confirmado manualmente',
                        default => 'No ar',
                    })
                    ->color(fn (Cupom $record): string => match (true) {
                        $record->link_verificado_em === null => 'gray',
                        (bool) $record->link_quebrado => 'danger',
                        (bool) $record->link_confirmado_manualmente && (int) $record->link_ultimo_status >= 400 => 'warning',
                        default => 'success',
                    })
                    ->description(fn (Cupom $record): ?string => $record->link_verificado_em?->format('d/m/Y H:i'))
                    ->toggleable(),
                TextColumn::make('ordem')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('valido_ate')
            ->filters([
                SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->options(fn () => Marca::orderBy('nome')->pluck('nome', 'id'))
                    ->searchable(),
                SelectFilter::make('status')->options(StatusItem::class),
                Filter::make('vencidos')
                    ->label('Vencidos')
                    ->toggle()
                    ->query(fn ($query) => $query->vencidos()),
                Filter::make('link_quebrado')
                    ->label('Link quebrado')
                    ->toggle()
                    ->query(fn ($query) => $query->comLinkQuebrado()),
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
