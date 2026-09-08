<?php

namespace App\Filament\Resources\TaxaDivulgadas\Pages;

use App\Enums\FonteTipo;
use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Filament\Resources\TaxaDivulgadas\TaxaDivulgadaResource;
use App\Models\GrupoBandeira;
use App\Models\Marca;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lançamento em lote: a tabela inteira de uma marca (1x a 21x, por prazo de
 * recebimento) numa única tela, em vez de um registro de taxa por vez.
 *
 * 1x é sempre crédito à vista; 2x a 21x é crédito parcelado (regra 2). Débito
 * e Pix não entram aqui, por não terem a dimensão "parcelas" — continuam no
 * cadastro normal do recurso de Taxas.
 */
class LancamentoEmLote extends Page
{
    protected static string $resource = TaxaDivulgadaResource::class;

    protected string $view = 'filament.resources.taxa-divulgadas.pages.lancamento-em-lote';

    protected static ?string $title = 'Lançamento em lote';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'fonte_tipo' => FonteTipo::SiteOficial->value,
            'data_verificacao' => now()->toDateString(),
            'status' => StatusPublicacao::Rascunho->value,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Chave comum do lote')
                    ->description('Regra 1: marca + plano + grupo de bandeiras. Parcelas e prazo variam por célula da tabela abaixo.')
                    ->columns(3)
                    ->components([
                        Select::make('marca_id')
                            ->label('Marca')
                            ->options(fn () => Marca::query()->where('publica_tabela', true)->orderBy('nome')->pluck('nome', 'id'))
                            ->searchable()
                            ->live()
                            ->required()
                            ->afterStateUpdated(fn (Set $set) => $set('plano_id', null))
                            ->helperText('Só marcas com publica_tabela = true (regra 4).'),
                        Select::make('plano_id')
                            ->label('Plano')
                            ->options(fn (Get $get) => Plano::query()->where('marca_id', $get('marca_id'))->pluck('nome', 'id'))
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('marca_id'))),
                        Select::make('grupo_bandeira_id')
                            ->label('Grupo de bandeiras')
                            // A grade e de credito (1x a 21x): o grupo tecnico do
                            // Pix nao cabe aqui (etapa 05, decisao 1).
                            ->options(GrupoBandeira::query()->deCartao()->orderBy('ordem')->pluck('nome_exibicao', 'id'))
                            ->required(),
                    ]),
                Section::make('Fonte e verificação')
                    ->description('Regra 8: nenhuma taxa entra sem fonte e data de verificação. Aplicada a todas as células preenchidas.')
                    ->columns(4)
                    ->components([
                        TextInput::make('url_fonte')
                            ->label('URL da fonte')
                            ->url()
                            ->required()
                            ->maxLength(500),
                        Select::make('fonte_tipo')
                            ->label('Tipo de fonte')
                            ->options(FonteTipo::class)
                            ->required(),
                        DatePicker::make('data_verificacao')
                            ->label('Verificado em')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->required(),
                        Select::make('status')
                            ->options(StatusPublicacao::class)
                            ->required()
                            ->helperText('Regra 10: nada vai ao ar sem aprovação humana.'),
                    ]),
                ...self::gradeDePrazos(),
                Section::make('Observação')
                    ->components([
                        Textarea::make('observacao')->rows(2),
                    ]),
            ])
            ->statePath('data');
    }

    /** Uma seção por prazo de recebimento cadastrado, cada uma com colunas 1x a 21x. */
    private static function gradeDePrazos(): array
    {
        return PrazoRecebimento::query()
            ->orderBy('ordem')
            ->get()
            ->map(fn (PrazoRecebimento $prazo) => Section::make($prazo->nome_exibicao)
                ->columns(7)
                ->collapsible()
                ->components(
                    collect(range(1, 21))
                        ->map(fn (int $parcela) => TextInput::make("percentuais.{$prazo->id}.{$parcela}")
                            ->label($parcela === 1 ? '1x (à vista)' : "{$parcela}x")
                            ->numeric()
                            ->step(0.0001)
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%'))
                        ->all()
                ))
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lancar')
                ->label('Lançar tabela')
                ->icon(Heroicon::OutlinedCheck)
                ->action('lancar'),
        ];
    }

    public function lancar(): void
    {
        $state = $this->form->getState();

        $celulas = collect($state['percentuais'] ?? [])
            ->flatMap(fn (array $porParcela, $prazoId) => collect($porParcela)
                ->filter(fn ($percentual) => filled($percentual))
                ->mapWithKeys(fn ($percentual, $parcela) => ["{$prazoId}:{$parcela}" => $percentual]));

        if ($celulas->isEmpty()) {
            Notification::make()
                ->warning()
                ->title('Nenhuma célula preenchida')
                ->body('Preencha ao menos um percentual na tabela antes de lançar.')
                ->send();

            return;
        }

        try {
            $lancadas = DB::transaction(function () use ($state, $celulas): int {
                $total = 0;

                foreach ($celulas as $chave => $percentual) {
                    [$prazoId, $parcela] = explode(':', $chave);
                    $parcela = (int) $parcela;
                    $tipoOperacao = $parcela === 1 ? TipoOperacao::CreditoAvista : TipoOperacao::CreditoParcelado;

                    TaxaDivulgada::updateOrCreate(
                        [
                            'plano_id' => $state['plano_id'],
                            'tipo_operacao' => $tipoOperacao->value,
                            'grupo_bandeira_id' => $state['grupo_bandeira_id'],
                            'parcelas' => $parcela,
                            'prazo_recebimento_id' => (int) $prazoId,
                        ],
                        [
                            'percentual' => $percentual,
                            'valor_fixo' => 0,
                            'url_fonte' => $state['url_fonte'],
                            'fonte_tipo' => $state['fonte_tipo'],
                            'data_verificacao' => $state['data_verificacao'],
                            'verificado_por' => Auth::id(),
                            'status' => $state['status'],
                            'observacao' => $state['observacao'] ?? null,
                        ],
                    );

                    $total++;
                }

                return $total;
            });
        } catch (DomainException $exception) {
            Notification::make()
                ->danger()
                ->title('Não foi possível lançar')
                ->body($exception->getMessage())
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title("{$lancadas} taxa(s) lançada(s)")
            ->send();

        $this->form->fill([
            ...$state,
            'percentuais' => [],
        ]);
    }
}
