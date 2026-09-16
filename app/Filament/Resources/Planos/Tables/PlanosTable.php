<?php

namespace App\Filament\Resources\Planos\Tables;

use App\Enums\StatusItem;
use App\Enums\StatusPublicacao;
use App\Enums\TipoEnquadramento;
use App\Filament\Resources\TaxaDivulgadas\TaxaDivulgadaResource;
use App\Models\Marca;
use App\Models\Plano;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pedido do Everton, sessão de 16/09/2026, junto do conserto da Tabela do
 * Plano (etapa 17): "controle visual mais fácil de identificar o status
 * atual de todos os planos de todas as marcas". `situacao_taxas` conta
 * quantas taxas do plano já estão publicadas contra o total - a mesma
 * pergunta que a Tabela do Plano deixava difícil de responder sem abrir
 * cada plano um por um.
 */
class PlanosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'taxasDivulgadas as taxas_total_count',
                'taxasDivulgadas as taxas_publicadas_count' => fn (Builder $q) => $q->where('status', StatusPublicacao::Publicado),
            ]))
            ->columns([
                TextColumn::make('marca.nome')
                    ->label('Marca')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nome')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tipo_enquadramento')
                    ->label('Enquadramento')
                    ->badge()
                    ->formatStateUsing(fn (TipoEnquadramento $state): string => $state->rotuloCurto())
                    // A promoção é o único enquadramento que expira, e o
                    // comparador a trata em bloco próprio. Vale destacar.
                    ->color(fn (TipoEnquadramento $state): string => $state->ehTemporario() ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('mensalidade')
                    ->money('BRL', locale: 'pt_BR')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Plano')
                    ->badge()
                    ->sortable(),
                TextColumn::make('situacao_taxas')
                    ->label('Situação das taxas')
                    ->state(fn (Plano $record): string => $record->taxas_total_count === 0
                        ? 'Sem taxa cadastrada'
                        : "{$record->taxas_publicadas_count}/{$record->taxas_total_count} publicadas")
                    ->badge()
                    ->color(function (Plano $record): string {
                        if ($record->taxas_total_count === 0) {
                            return 'gray';
                        }

                        if ($record->taxas_publicadas_count === 0) {
                            return 'danger';
                        }

                        return $record->taxas_publicadas_count === $record->taxas_total_count
                            ? 'success'
                            : 'warning';
                    })
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('taxas_publicadas_count', $direction)),
                TextColumn::make('ordem')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordem')
            ->groups([
                Group::make('marca.nome')->label('Marca'),
            ])
            ->filters([
                SelectFilter::make('marca_id')
                    ->label('Marca')
                    ->options(fn () => Marca::orderBy('nome')->pluck('nome', 'id'))
                    ->searchable(),
                SelectFilter::make('tipo_enquadramento')->options(TipoEnquadramento::class),
                SelectFilter::make('status')->label('Status do plano')->options(StatusItem::class),
                Filter::make('com_taxa_em_rascunho')
                    ->label('Com taxas em rascunho')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereHas(
                        'taxasDivulgadas',
                        fn (Builder $q) => $q->where('status', StatusPublicacao::Rascunho),
                    )),
                Filter::make('sem_taxa_cadastrada')
                    ->label('Sem taxa cadastrada')
                    ->toggle()
                    ->query(fn (Builder $query) => $query->whereDoesntHave('taxasDivulgadas')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('tabelaDeTaxas')
                    ->label('Tabela de taxas')
                    ->icon(Heroicon::OutlinedTableCells)
                    ->color('primary')
                    ->url(fn (Plano $record) => TaxaDivulgadaResource::getUrl('tabela-do-plano', ['plano' => $record])),
                EditAction::make(),
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
