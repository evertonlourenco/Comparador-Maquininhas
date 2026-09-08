<?php

namespace App\Filament\Resources\Adquirentes\Schemas;

use App\Models\Adquirente;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class AdquirenteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(2)
                    ->components([
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(80)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(80)
                            ->unique(Adquirente::class, 'slug', ignoreRecord: true),
                        Textarea::make('observacao')
                            ->label('Observação')
                            ->columnSpanFull()
                            ->rows(3)
                            ->helperText('Contexto de transparência: quem processa, desde quando, sob qual arranjo. Não é critério de desempate entre marcas (regra 7).'),
                    ]),
            ]);
    }
}
