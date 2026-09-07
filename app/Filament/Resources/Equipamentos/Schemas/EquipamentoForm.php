<?php

namespace App\Filament\Resources\Equipamentos\Schemas;

use App\Enums\StatusItem;
use App\Enums\TipoEquipamento;
use App\Support\Uploads\ImagemSeguraWebp;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class EquipamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Marca')
                    ->components([
                        Select::make('marca_id')
                            ->label('Marca')
                            ->relationship('marca', 'nome')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),
                ...self::camposBase(),
            ]);
    }

    public static function camposBase(): array
    {
        return [
            Section::make('Identificação')
                ->columns(2)
                ->components([
                    TextInput::make('nome')
                        ->required()
                        ->maxLength(120)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(120),
                    Select::make('tipo')
                        ->options(TipoEquipamento::class)
                        ->required(),
                    Select::make('status')
                        ->options(StatusItem::class)
                        ->required()
                        ->default(StatusItem::Ativo),
                    Textarea::make('descricao')
                        ->columnSpanFull()
                        ->rows(3),
                ]),
            Section::make('Características')
                ->columns(4)
                ->components([
                    Toggle::make('tem_chip_gratis')->label('Chip grátis')->inline(false),
                    Toggle::make('imprime_comprovante')->label('Imprime comprovante')->inline(false),
                    Toggle::make('aceita_nfc')->label('Aceita NFC')->default(true)->inline(false),
                    Toggle::make('exige_celular')->label('Exige celular')->inline(false),
                ]),
            Section::make('Foto')
                ->components([
                    FileUpload::make('imagem_path')
                        ->label('Foto do equipamento')
                        ->image()
                        ->disk('public')
                        ->directory('equipamentos')
                        ->visibility('public')
                        ->maxSize(2048)
                        ->rules([fn () => ImagemSeguraWebp::regraDeValidacao()])
                        ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): ?string => ImagemSeguraWebp::salvar($file, 'equipamentos'))
                        ->helperText('JPEG, PNG, GIF ou WebP — convertido para WebP automaticamente. O tipo é validado pelo conteúdo real do arquivo, não pela extensão.'),
                ]),
        ];
    }
}
