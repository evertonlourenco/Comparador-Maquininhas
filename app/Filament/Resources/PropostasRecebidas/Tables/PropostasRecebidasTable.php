<?php

namespace App\Filament\Resources\PropostasRecebidas\Tables;

use App\Enums\SegmentoNegocio;
use App\Enums\StatusRevisao;
use App\Models\Marca;
use App\Models\PropostaRecebida;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class PropostasRecebidasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('marca.nome')->label('Marca')->searchable()->sortable(),
                TextColumn::make('data_proposta')->label('Data da proposta')->date('d/m/Y')->sortable(),
                TextColumn::make('estado')->label('UF'),
                TextColumn::make('segmento')->label('Segmento')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('created_at')->label('Recebida em')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(StatusRevisao::class),
                SelectFilter::make('marca_id')->label('Marca')->options(fn () => Marca::orderBy('nome')->pluck('nome', 'id'))->searchable(),
                SelectFilter::make('segmento')->options(SegmentoNegocio::class),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('baixarAnexo')
                    ->label('Baixar anexo')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->visible(fn (PropostaRecebida $record): bool => filled($record->anexo_caminho))
                    ->action(fn (PropostaRecebida $record) => Storage::disk('local')->download($record->anexo_caminho)),
            ]);
    }
}
