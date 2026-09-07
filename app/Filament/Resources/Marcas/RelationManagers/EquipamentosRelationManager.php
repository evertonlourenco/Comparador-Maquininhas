<?php

namespace App\Filament\Resources\Marcas\RelationManagers;

use App\Filament\Resources\Equipamentos\Schemas\EquipamentoForm;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EquipamentosRelationManager extends RelationManager
{
    protected static string $relationship = 'equipamentos';

    public function form(Schema $schema): Schema
    {
        return $schema->components(EquipamentoForm::camposBase());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('nome')
            ->columns([
                ImageColumn::make('imagem_path')->label('Foto')->disk('public')->square(),
                TextColumn::make('nome')->searchable(),
                TextColumn::make('tipo')->badge(),
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
