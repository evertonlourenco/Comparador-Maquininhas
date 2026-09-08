<?php

namespace App\Filament\Resources\GrupoBandeiras\Schemas;

use App\Models\GrupoBandeira;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class GrupoBandeiraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Grupo')
                    ->columns(3)
                    ->components([
                        TextInput::make('nome_exibicao')
                            ->label('Nome de exibição')
                            ->required()
                            ->maxLength(60)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, ?GrupoBandeira $record) {
                                // Codigo de grupo curado nunca e reescrito pelo nome.
                                if ($record?->estaReservado()) {
                                    return;
                                }

                                $set('codigo', Str::of($state ?? '')->slug('_')->limit(30, '')->value());
                            }),
                        TextInput::make('codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(30)
                            ->unique(GrupoBandeira::class, 'codigo', ignoreRecord: true)
                            ->disabled(fn (?GrupoBandeira $record): bool => (bool) $record?->estaReservado())
                            ->dehydrated(fn (?GrupoBandeira $record): bool => ! $record?->estaReservado())
                            ->helperText(fn (?GrupoBandeira $record): string => $record?->estaReservado()
                                ? 'Grupo curado: este código é referenciado por constante no código-fonte e não pode ser alterado.'
                                : 'Identificador técnico, em minúsculas com underline. Ex.: amex_isolada'),
                        TextInput::make('ordem')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->columnSpanFull()
                            ->rows(2)
                            ->maxLength(255)
                            ->helperText('Quais bandeiras costumam cair aqui. A composição real é decidida marca a marca, no cadastro de cada marca.'),
                    ]),
            ]);
    }
}
