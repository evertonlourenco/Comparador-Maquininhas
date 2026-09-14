<?php

namespace App\Filament\Resources\DeteccoesDeMudanca\Schemas;

use App\Enums\StatusRevisao;
use App\Enums\TipoDeteccaoDeMudanca;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeteccaoDeMudancaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('O que o monitor relatou')
                ->columns(2)
                ->components([
                    TextEntry::make('marca.nome')->label('Marca')->placeholder('Não identificada'),
                    TextEntry::make('categoria')->label('Categoria'),
                    TextEntry::make('url')->label('Fonte')->url(fn ($record) => $record->url)->openUrlInNewTab()->columnSpanFull(),
                    TextEntry::make('resumo')
                        ->label('Resumo do que mudou (Gemini)')
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record->tipo === TipoDeteccaoDeMudanca::Mudanca),
                    TextEntry::make('trecho_alterado')
                        ->label('Trecho enviado ao Gemini')
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record->tipo === TipoDeteccaoDeMudanca::Mudanca),
                    TextEntry::make('mensagem_erro')
                        ->label('Erro relatado pelo monitor')
                        ->placeholder('—')
                        ->columnSpanFull()
                        ->visible(fn ($record) => $record->tipo === TipoDeteccaoDeMudanca::Falha),
                    TextEntry::make('detectado_em')->label('Detectado em')->dateTime('d/m/Y H:i'),
                ]),

            Section::make('Revisão')
                ->columns(2)
                ->components([
                    Select::make('status')
                        ->options(StatusRevisao::class)
                        ->required(),
                    Textarea::make('observacao_admin')
                        ->label('Observação interna')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
