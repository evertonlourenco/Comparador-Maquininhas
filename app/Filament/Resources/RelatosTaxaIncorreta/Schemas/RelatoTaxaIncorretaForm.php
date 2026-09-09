<?php

namespace App\Filament\Resources\RelatosTaxaIncorreta\Schemas;

use App\Enums\StatusRevisao;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RelatoTaxaIncorretaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('O que foi relatado')
                ->columns(2)
                ->components([
                    TextEntry::make('marca.nome')->label('Marca'),
                    TextEntry::make('contexto')->label('Contexto')->placeholder('—'),
                    TextEntry::make('pagina_url')->label('Página de origem')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('mensagem')->label('Mensagem')->columnSpanFull(),
                    TextEntry::make('email_contato')->label('E-mail para contato')->placeholder('Não informado'),
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
