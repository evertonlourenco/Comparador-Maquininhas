<?php

namespace App\Filament\Resources\Cupons\Schemas;

use App\Enums\IncideSobre;
use App\Enums\StatusItem;
use App\Enums\TipoDesconto;
use App\Models\Equipamento;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CupomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Marca')
                    ->columns(2)
                    ->components([
                        Select::make('marca_id')
                            ->label('Marca')
                            ->relationship('marca', 'nome')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        Select::make('equipamento_id')
                            ->label('Equipamento')
                            ->options(fn (Get $get) => Equipamento::query()->where('marca_id', $get('marca_id'))->pluck('nome', 'id'))
                            ->searchable()
                            ->disabled(fn (Get $get): bool => blank($get('marca_id')))
                            ->helperText('Deixe em branco para valer no catálogo todo da marca.'),
                    ]),
                ...self::camposBase(),
            ]);
    }

    public static function camposBase(): array
    {
        return [
            Section::make('Cupom')
                ->columns(2)
                ->components([
                    TextInput::make('codigo')
                        ->required()
                        ->maxLength(60),
                    TextInput::make('descricao')
                        ->maxLength(255),
                    Select::make('tipo_desconto')
                        ->options(TipoDesconto::class)
                        ->live()
                        ->helperText('Deixe em branco quando o desconto é real mas o valor exato não '
                            .'é conhecido (varia por equipamento, por mês etc.) — o cupom mostra a '
                            .'descrição abaixo em vez de um número.'),
                    Select::make('incide_sobre')
                        ->options(IncideSobre::class)
                        ->required(),
                    TextInput::make('valor')
                        ->numeric()
                        ->prefix(function (Get $get): string {
                            $tipo = $get('tipo_desconto');
                            $valor = $tipo instanceof TipoDesconto ? $tipo->value : $tipo;

                            return $valor === TipoDesconto::Percentual->value ? '%' : 'R$';
                        }),
                    Select::make('status')
                        ->options(StatusItem::class)
                        ->required()
                        ->default(StatusItem::Ativo),
                ]),
            Section::make('Vigência')
                ->description('A maioria dos cupons de afiliado não tem prazo — deixe "Válido até" em '
                    .'branco nesse caso (regra 5: um cupom com data some sozinho quando ela passa; sem '
                    .'data, ele fica vigente até alguém marcar como inativo aqui).')
                ->columns(3)
                ->components([
                    DatePicker::make('valido_de')
                        ->label('Válido de')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->default(now())
                        ->required(),
                    DatePicker::make('valido_ate')
                        ->label('Válido até (opcional)')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->afterOrEqual('valido_de'),
                    TextInput::make('ordem')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ]),
            Section::make('Link e termos')
                ->components([
                    TextInput::make('link_afiliado')
                        ->label('Link de afiliado')
                        ->url()
                        ->required()
                        ->maxLength(500)
                        ->helperText('Regra 5: a taxa é a mesma do site oficial — a vantagem do link é este cupom.'),
                    Toggle::make('link_confirmado_manualmente')
                        ->label('Confirmado manualmente')
                        ->helperText('Marque só se você mesmo abriu o link e viu que funciona, mesmo que o '
                            .'verificador automático (php artisan links:verificar) marque como quebrado. Alguns '
                            .'sites devolvem um status HTTP de erro mesmo funcionando de verdade no navegador.'),
                    Textarea::make('termos')
                        ->rows(2),
                ]),
        ];
    }
}
