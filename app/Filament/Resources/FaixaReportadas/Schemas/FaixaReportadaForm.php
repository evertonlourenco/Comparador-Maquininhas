<?php

namespace App\Filament\Resources\FaixaReportadas\Schemas;

use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class FaixaReportadaForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Chave da faixa')
                    ->description('Regra 1: marca + plano + tipo de operação + parcelas + prazo de recebimento. Regra 4: classe B, para marcas que não publicam tabela (Cielo, Rede, GetNet, Stone).')
                    ->columns(2)
                    ->components([
                        Select::make('marca_id_filtro')
                            ->label('Marca')
                            ->options(fn () => Marca::query()->orderBy('nome')->pluck('nome', 'id'))
                            ->searchable()
                            ->live()
                            ->dehydrated(false)
                            ->afterStateHydrated(function (Set $set, $record): void {
                                if ($record) {
                                    $set('marca_id_filtro', $record->marca_id);
                                }
                            })
                            ->required(),
                        Select::make('plano_id')
                            ->label('Plano')
                            ->options(fn (Get $get) => Plano::query()->where('marca_id', $get('marca_id_filtro'))->pluck('nome', 'id'))
                            ->searchable()
                            ->live()
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('marca_id_filtro'))),
                        Select::make('grupo_bandeira_id')
                            ->label('Grupo de bandeiras')
                            // Etapa 05, decisao 1: Pix so aceita o grupo "pix" e o
                            // grupo "pix" so aceita Pix. O model recusa o contrario
                            // com DomainException; aqui a lista nem oferece o erro.
                            ->options(fn (Get $get) => GrupoBandeira::query()
                                ->paraOperacao($get('tipo_operacao'))
                                ->orderBy('ordem')
                                ->pluck('nome_exibicao', 'id'))
                            ->helperText(fn (Get $get): string => self::tipoOperacaoDe($get('tipo_operacao')) === TipoOperacao::Pix
                                ? 'O Pix não passa por bandeira: o grupo "Pix" é técnico e existe só para dar lugar a esta taxa.'
                                : 'Escolha o tipo de operação primeiro — ele define quais grupos aparecem aqui.')
                            ->required(),
                        Select::make('prazo_recebimento_id')
                            ->label('Prazo de recebimento')
                            ->options(PrazoRecebimento::query()->orderBy('ordem')->pluck('nome_exibicao', 'id'))
                            ->required(),
                        Select::make('tipo_operacao')
                            ->options(TipoOperacao::class)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Set $set, TipoOperacao|string|null $state): void {
                                $set('parcelas', self::tipoOperacaoDe($state)?->parcelaMinima());
                                // O grupo valido muda junto com o tipo; manter o
                                // anterior deixaria o Select com um id fora da lista.
                                $set('grupo_bandeira_id', null);
                            }),
                        TextInput::make('parcelas')
                            ->numeric()
                            ->required()
                            ->minValue(fn (Get $get): int => self::tipoOperacaoDe($get('tipo_operacao'))?->parcelaMinima() ?? 1)
                            ->maxValue(fn (Get $get): int => self::tipoOperacaoDe($get('tipo_operacao'))?->parcelaMaxima() ?? 21)
                            ->unique(
                                table: 'faixas_reportadas',
                                ignoreRecord: true,
                                modifyRuleUsing: fn ($rule, Get $get) => $rule
                                    ->where('plano_id', $get('plano_id'))
                                    ->where('tipo_operacao', $get('tipo_operacao'))
                                    ->where('grupo_bandeira_id', $get('grupo_bandeira_id'))
                                    ->where('prazo_recebimento_id', $get('prazo_recebimento_id')),
                            ),
                    ]),
                Section::make('Faixa (nunca número exato)')
                    ->columns(3)
                    ->components([
                        TextInput::make('percentual_minimo')
                            ->label('Mínimo')
                            ->numeric()
                            ->step(0.0001)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('percentual_mediana')
                            ->label('Mediana')
                            ->numeric()
                            ->step(0.0001)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('percentual_maximo')
                            ->label('Máximo')
                            ->numeric()
                            ->step(0.0001)
                            ->suffix('%')
                            ->required(),
                        TextInput::make('n_relatos')
                            ->label('Número de relatos')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        DatePicker::make('periodo_inicio')
                            ->label('Período - início')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        DatePicker::make('periodo_fim')
                            ->label('Período - fim')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        Textarea::make('metodologia')
                            ->columnSpanFull()
                            ->rows(2),
                    ]),
                Section::make('Fonte e verificação')
                    ->description('Regra 8: nenhuma taxa entra sem fonte e data de verificação.')
                    ->columns(2)
                    ->components([
                        TextInput::make('fonte_descricao')
                            ->label('Descrição da fonte')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('url_fonte')
                            ->label('URL da fonte')
                            ->url()
                            ->maxLength(500),
                        DatePicker::make('data_verificacao')
                            ->label('Verificado em')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->required(),
                        Select::make('verificado_por')
                            ->label('Verificado por')
                            ->relationship('verificadoPor', 'name')
                            ->default(fn () => Auth::id())
                            ->searchable()
                            ->preload(),
                    ]),
                Section::make('Publicação')
                    ->description('Regra 10: nada vai ao ar sem aprovação humana.')
                    ->columns(2)
                    ->components([
                        Select::make('status')
                            ->options(StatusPublicacao::class)
                            ->required()
                            ->default(StatusPublicacao::Rascunho),
                        Textarea::make('observacao')
                            ->rows(2),
                    ]),
            ]);
    }

    /** O state de um Select enum-backed pode chegar como o enum já resolvido ou como o valor cru. */
    private static function tipoOperacaoDe(TipoOperacao|string|null $valor): ?TipoOperacao
    {
        return $valor instanceof TipoOperacao ? $valor : ($valor ? TipoOperacao::from($valor) : null);
    }
}
