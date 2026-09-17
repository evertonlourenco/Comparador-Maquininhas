<?php

namespace App\Filament\Resources\Marcas\Tables;

use App\Enums\StatusMarca;
use App\Filament\Actions\AprovarMarcaAction;
use App\Filament\Actions\BuscarImagemPorUrlAction;
use App\Filament\Actions\VerPendenciasDaMarcaAction;
use App\Models\Marca;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class MarcasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo_path')
                    ->label('Logo')
                    ->disk('public')
                    ->square(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('adquirente.nome')
                    ->label('Adquirente')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('publica_tabela')
                    ->label('Publica tabela')
                    ->boolean(),
                TextColumn::make('link_quebrado')
                    ->label('Link')
                    ->badge()
                    ->formatStateUsing(fn (Marca $record): string => match (true) {
                        $record->link_verificado_em === null => 'Não verificado',
                        (bool) $record->link_quebrado => 'Quebrado',
                        (bool) $record->link_confirmado_manualmente && (int) $record->link_ultimo_status >= 400 => 'Confirmado manualmente',
                        default => 'No ar',
                    })
                    ->color(fn (Marca $record): string => match (true) {
                        $record->link_verificado_em === null => 'gray',
                        (bool) $record->link_quebrado => 'danger',
                        (bool) $record->link_confirmado_manualmente && (int) $record->link_ultimo_status >= 400 => 'warning',
                        default => 'success',
                    })
                    ->description(fn (Marca $record): ?string => $record->link_verificado_em?->format('d/m/Y H:i'))
                    ->toggleable(),
                TextColumn::make('taxas_divulgadas_count')
                    ->label('Taxas')
                    ->counts('taxasDivulgadas')
                    ->sortable(),
                TextColumn::make('faixas_reportadas_count')
                    ->label('Faixas')
                    ->counts('faixasReportadas')
                    ->sortable(),
                TextColumn::make('reclame_aqui_nota')
                    ->label('RA')
                    ->numeric(1)
                    ->sortable(),
                // Etapa 19: a trava da marca. "Completa" é o que
                // App\Support\Saude\CompletudeDaMarca calcula rodando o motor
                // de verdade contra a marca — não é um campo salvo, é
                // conferido de novo a cada carregamento da tela, para nunca
                // mostrar uma completude que já ficou desatualizada.
                TextColumn::make('completude')
                    ->label('Completude')
                    ->state(fn (Marca $record): string => $record->completude['completa']
                        ? 'Completa'
                        : count($record->completude['pendencias']).' pendência(s)')
                    ->badge()
                    ->color(fn (Marca $record): string => $record->completude['completa'] ? 'success' : 'warning'),
                TextColumn::make('aprovada_em')
                    ->label('No site')
                    ->badge()
                    ->state(fn (Marca $record): string => $record->aprovada
                        ? 'Aprovada'
                        : ($record->aprovada_em !== null ? 'Aprovação desatualizada' : 'Não aprovada'))
                    ->color(fn (Marca $record): string => match (true) {
                        $record->aprovada => 'success',
                        $record->aprovada_em !== null => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (Marca $record): ?string => $record->aprovada_em?->format('\A\p\r\o\v\a\d\a \e\m d/m/Y \à\s H:i')),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('ordem')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordem')
            ->filters([
                SelectFilter::make('status')->options(StatusMarca::class),
                Filter::make('nao_aprovadas')
                    ->label('Não aprovadas')
                    ->toggle()
                    ->query(fn ($query) => $query->whereNull('aprovada_em')),
                Filter::make('link_quebrado')
                    ->label('Link quebrado')
                    ->toggle()
                    ->query(fn ($query) => $query->comLinkQuebrado()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                VerPendenciasDaMarcaAction::make(),
                AprovarMarcaAction::make(),
                EditAction::make(),
                BuscarImagemPorUrlAction::make('logo_path', 'marcas/logos', 'logo'),
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
