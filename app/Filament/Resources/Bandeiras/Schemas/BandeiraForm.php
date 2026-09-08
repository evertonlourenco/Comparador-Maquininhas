<?php

namespace App\Filament\Resources\Bandeiras\Schemas;

use App\Models\Bandeira;
use App\Support\Uploads\ImagemSeguraWebp;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class BandeiraForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificação')
                    ->columns(3)
                    ->components([
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(80)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(80)
                            ->unique(Bandeira::class, 'slug', ignoreRecord: true),
                        TextInput::make('ordem')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Ordem de exibição na lista de bandeiras aceitas.'),
                    ]),
                Section::make('Logo')
                    ->components([
                        FileUpload::make('logo_path')
                            ->label('Logo da bandeira')
                            ->image()
                            ->disk('public')
                            ->directory('bandeiras')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->rules([fn () => ImagemSeguraWebp::regraDeValidacao()])
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): ?string => ImagemSeguraWebp::salvar($file, 'bandeiras'))
                            ->helperText('JPEG, PNG, GIF ou WebP — convertido para WebP automaticamente. O tipo é validado pelo conteúdo real do arquivo, não pela extensão.'),
                    ]),
            ]);
    }
}
