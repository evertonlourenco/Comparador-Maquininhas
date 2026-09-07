<?php

namespace App\Filament\Resources\Marcas\RelationManagers;

use App\Filament\Resources\Planos\Schemas\PlanoForm;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlanosRelationManager extends RelationManager
{
    protected static string $relationship = 'planos';

    public function form(Schema $schema): Schema
    {
        return $schema->components(PlanoForm::camposBase());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                TextColumn::make('nome')->searchable(),
                TextColumn::make('tipo_enquadramento')->badge(),
                TextColumn::make('mensalidade')->money('BRL', locale: 'pt_BR'),
                TextColumn::make('status')->badge(),
            ])
            ->defaultSort('ordem')
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
