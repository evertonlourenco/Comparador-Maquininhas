<?php

namespace App\Filament\Resources\Planos\Schemas;

use App\Enums\StatusItem;
use App\Enums\TipoEnquadramento;
use App\Models\Marca;
use App\Models\Plano;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PlanoForm
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

    /**
     * Campos comuns entre o resource independente (que precisa do select de
     * marca_id) e o relation manager de planos dentro de uma marca (onde a
     * marca já é o registro-pai e o campo seria redundante).
     */
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
                        ->maxLength(120)
                        ->unique(
                            table: 'planos',
                            column: 'slug',
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule, Get $get) => $rule->where('marca_id', $get('marca_id')),
                        )
                        ->helperText('Único dentro da marca.'),
                    Select::make('tipo_enquadramento')
                        ->label('Enquadramento')
                        ->options(TipoEnquadramento::class)
                        ->required()
                        ->live(),
                    Select::make('status')
                        ->options(StatusItem::class)
                        ->required()
                        ->default(StatusItem::Ativo),
                    TextInput::make('ordem')
                        ->numeric()
                        ->default(0)
                        ->required(),
                ]),
            Section::make('Faixa de faturamento')
                ->description('Regra 3: só se aplica ao enquadramento automático.')
                ->columns(2)
                ->visible(fn (Get $get): bool => self::enquadramentoDe($get('tipo_enquadramento')) === TipoEnquadramento::Automatico)
                ->components([
                    TextInput::make('faturamento_min')
                        ->label('Faturamento mínimo')
                        ->numeric()
                        ->prefix('R$'),
                    TextInput::make('faturamento_max')
                        ->label('Faturamento máximo')
                        ->numeric()
                        ->prefix('R$'),
                ]),
            Section::make('Promoção de entrada')
                ->description('Etapa 05: tabela de entrada, com prazo para acabar. O comparador nunca '
                    .'ranqueia isto junto dos planos permanentes — mostra em bloco próprio, com a validade à vista.')
                ->columns(3)
                ->visible(fn (Get $get): bool => self::enquadramentoDe($get('tipo_enquadramento')) === TipoEnquadramento::Promocional)
                ->components([
                    TextInput::make('promocional_dias')
                        ->label('Dura quantos dias')
                        ->numeric()
                        ->minValue(1)
                        ->suffix('dias')
                        ->helperText('Vazio = a marca não publica prazo em dias.'),
                    TextInput::make('promocional_valor_processado')
                        ->label('Ou até processar')
                        ->numeric()
                        ->prefix('R$')
                        ->helperText('Teto de volume processado na maquininha. Não é faixa de faturamento.'),
                    Select::make('promocional_sucessor_id')
                        ->label('Depois cai no plano')
                        ->options(fn (Get $get) => Plano::query()
                            ->where('marca_id', $get('marca_id'))
                            ->where('tipo_enquadramento', '!=', TipoEnquadramento::Promocional)
                            ->pluck('nome', 'id'))
                        ->searchable()
                        ->helperText('Vazio = cai no enquadramento automático da marca, conforme o faturamento.'),
                ]),
            Section::make('Compromisso')
                ->visible(fn (Get $get): bool => self::enquadramentoDe($get('tipo_enquadramento')) === TipoEnquadramento::Escolhido)
                ->components([
                    Textarea::make('compromisso')
                        ->label('Compromisso de volume assumido pelo lojista')
                        ->rows(3),
                ]),
            Section::make('Custos da conta')
                ->columns(3)
                ->components([
                    TextInput::make('mensalidade')
                        ->numeric()
                        ->prefix('R$'),
                    TextInput::make('tarifa_saque')
                        ->label('Tarifa de saque')
                        ->numeric()
                        ->prefix('R$'),
                    TextInput::make('tarifa_ted')
                        ->label('Tarifa de TED')
                        ->numeric()
                        ->prefix('R$'),
                    TextInput::make('tarifa_pix_recebimento')
                        ->label('Tarifa de Pix (recebimento)')
                        ->numeric()
                        ->prefix('R$'),
                    TextInput::make('tarifa_pix_envio')
                        ->label('Tarifa de Pix (envio)')
                        ->numeric()
                        ->prefix('R$'),
                    TextInput::make('taxa_antecipacao_mensal')
                        ->label('Antecipação avulsa (% a.m.)')
                        ->numeric()
                        ->suffix('%')
                        ->helperText('Antecipação automática já vem embutida na taxa de prazo "na hora" ou "D+1".'),
                ]),
            Section::make('Condição de isenção')
                ->components([
                    Textarea::make('condicao_isencao')
                        ->label('Condição de isenção de mensalidade/tarifas')
                        ->rows(3),
                ]),
        ];
    }

    /** O state de um Select enum-backed pode chegar como o enum já resolvido ou como o valor cru. */
    private static function enquadramentoDe(TipoEnquadramento|string|null $valor): ?TipoEnquadramento
    {
        return $valor instanceof TipoEnquadramento ? $valor : ($valor ? TipoEnquadramento::from($valor) : null);
    }
}
