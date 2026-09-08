<?php

namespace App\Filament\Resources\PrazoRecebimentos\Schemas;

use App\Models\PrazoRecebimento;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PrazoRecebimentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Prazo')
                    ->columns(4)
                    ->components([
                        TextInput::make('nome_exibicao')
                            ->label('Nome de exibição')
                            ->required()
                            ->maxLength(60)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, ?string $state, ?PrazoRecebimento $record) {
                                if ($record?->estaReservado()) {
                                    return;
                                }

                                $set('codigo', Str::of($state ?? '')->slug('_')->limit(30, '')->value());
                            }),
                        TextInput::make('codigo')
                            ->label('Código')
                            ->required()
                            ->maxLength(30)
                            ->unique(PrazoRecebimento::class, 'codigo', ignoreRecord: true)
                            ->disabled(fn (?PrazoRecebimento $record): bool => (bool) $record?->estaReservado())
                            ->dehydrated(fn (?PrazoRecebimento $record): bool => ! $record?->estaReservado())
                            ->helperText(fn (?PrazoRecebimento $record): string => $record?->estaReservado()
                                ? 'Prazo curado: este código é referenciado por constante no código-fonte e não pode ser alterado.'
                                : 'Identificador técnico, em minúsculas com underline. Ex.: d_7'),
                        TextInput::make('dias')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(365)
                            ->helperText('Deixe vazio quando o prazo não é um número de dias — é o caso de "conforme as parcelas", em que cada parcela cai no mês dela.'),
                        TextInput::make('ordem')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Textarea::make('descricao')
                            ->label('Descrição')
                            ->columnSpanFull()
                            ->rows(2)
                            ->maxLength(255),
                    ]),
            ]);
    }
}
