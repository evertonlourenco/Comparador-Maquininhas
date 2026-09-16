<?php

namespace App\Filament\Resources\Marcas\Schemas;

use App\Enums\StatusMarca;
use App\Models\Adquirente;
use App\Support\Uploads\ImagemSeguraWebp;
use Filament\Forms\Components\DatePicker;
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

class MarcaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identidade')
                    ->columns(2)
                    ->components([
                        Select::make('adquirente_id')
                            ->label('Adquirente subjacente')
                            ->relationship('adquirente', 'nome')
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('nome')->required()->maxLength(80),
                                TextInput::make('slug')->required()->maxLength(80)->unique(Adquirente::class, 'slug'),
                                Textarea::make('observacao')->rows(2),
                            ])
                            ->helperText('Transparência, não deduplicação (regra 7): marcas que dividem adquirente continuam concorrendo entre si.'),
                        TextInput::make('nome')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state ?? ''))),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(120)
                            ->unique(ignoreRecord: true),
                        TextInput::make('site_url')
                            ->label('Site oficial')
                            ->url()
                            ->maxLength(255),
                        Toggle::make('link_confirmado_manualmente')
                            ->label('Link confirmado manualmente')
                            ->helperText('Marque só se você mesmo abriu o site e viu que funciona, mesmo que o '
                                .'verificador automático (php artisan links:verificar) marque como quebrado. '
                                .'Alguns sites devolvem um status HTTP de erro mesmo funcionando de verdade no '
                                .'navegador.')
                            ->inline(false),
                        Toggle::make('publica_tabela')
                            ->label('Publica tabela de taxas')
                            ->helperText('Se desligado, a marca só admite faixas reportadas (regra 4) — Cielo, Rede, GetNet e Stone, por exemplo.')
                            ->default(true)
                            ->inline(false),
                        Toggle::make('aceita_relatos')
                            ->label('Aceita relatos de lojistas')
                            ->helperText('Etapa 10: aparece no select de /enviar-proposta. Hoje, só faz sentido para marca que não publica tabela.')
                            ->default(false)
                            ->inline(false),
                        Select::make('status')
                            ->options(StatusMarca::class)
                            ->required()
                            ->default(StatusMarca::Ativa),
                        TextInput::make('ordem')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Textarea::make('descricao')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),
                Section::make('Logo')
                    ->components([
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('marcas/logos')
                            ->visibility('public')
                            ->maxSize(2048)
                            ->rules([fn () => ImagemSeguraWebp::regraDeValidacao()])
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): ?string => ImagemSeguraWebp::salvar($file->getRealPath(), 'marcas/logos'))
                            ->helperText('JPEG, PNG, GIF ou WebP — convertido para WebP automaticamente. O tipo é validado pelo conteúdo real do arquivo, não pela extensão.'),
                    ]),
                Section::make('Vídeo')
                    ->description('Etapa 08: o vídeo do canal embutido na página da marca. Vazio, a seção não aparece.')
                    ->components([
                        TextInput::make('youtube_video_id')
                            ->label('ID do vídeo no YouTube')
                            ->helperText('Só o ID — o trecho depois de "v=" no link do vídeo, ex.: dQw4w9WgXcQ.')
                            ->maxLength(20),
                    ]),
                Section::make('Reclame Aqui')
                    ->columns(3)
                    ->description('Regra 8: nota manual, com data de consulta e link. Nunca raspar.')
                    ->components([
                        TextInput::make('reclame_aqui_nota')
                            ->label('Nota')
                            ->numeric()
                            ->step(0.1)
                            ->minValue(0)
                            ->maxValue(10),
                        TextInput::make('reclame_aqui_url')
                            ->label('Link do perfil')
                            ->url()
                            ->maxLength(500),
                        DatePicker::make('reclame_aqui_consultado_em')
                            ->label('Consultado em')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ]),
            ]);
    }
}
