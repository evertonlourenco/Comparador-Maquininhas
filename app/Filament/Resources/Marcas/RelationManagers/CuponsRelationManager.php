<?php

namespace App\Filament\Resources\Marcas\RelationManagers;

use App\Enums\StatusItem;
use App\Filament\Resources\Cupons\Schemas\CupomForm;
use App\Models\Equipamento;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class CuponsRelationManager extends RelationManager
{
    protected static string $relationship = 'cupons';

    private const DIAS_ALERTA_VENCIMENTO = 7;

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Equipamento')
                    ->components([
                        Select::make('equipamento_id')
                            ->label('Equipamento')
                            ->options(fn () => Equipamento::query()->where('marca_id', $this->getOwnerRecord()->id)->pluck('nome', 'id'))
                            ->searchable()
                            ->helperText('Deixe em branco para valer no catálogo todo da marca.'),
                    ]),
                ...CupomForm::camposBase(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('codigo')
            ->columns([
                TextColumn::make('equipamento.nome')->label('Equipamento')->default('Catálogo todo'),
                TextColumn::make('codigo')->weight(FontWeight::Bold),
                TextColumn::make('valido_ate')
                    ->label('Válido até')
                    ->date('d/m/Y')
                    ->badge()
                    ->color(function ($record): string {
                        $hoje = Carbon::today();

                        if ($record->valido_ate->lt($hoje)) {
                            return 'gray';
                        }

                        return $hoje->diffInDays($record->valido_ate) <= self::DIAS_ALERTA_VENCIMENTO
                            ? 'danger'
                            : 'success';
                    }),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('valido_ate')
            ->filters([
                SelectFilter::make('status')->options(StatusItem::class),
                Filter::make('vencidos')
                    ->label('Vencidos')
                    ->toggle()
                    ->query(fn ($query) => $query->vencidos()),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
