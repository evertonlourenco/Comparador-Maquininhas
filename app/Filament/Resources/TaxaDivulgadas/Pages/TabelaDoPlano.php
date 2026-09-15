<?php

namespace App\Filament\Resources\TaxaDivulgadas\Pages;

use App\Enums\FonteTipo;
use App\Enums\StatusPublicacao;
use App\Enums\TipoOperacao;
use App\Filament\Resources\TaxaDivulgadas\TaxaDivulgadaResource;
use App\Models\GrupoBandeira;
use App\Models\Plano;
use App\Models\PrazoRecebimento;
use App\Models\TaxaDivulgada;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Etapa 17, pedido do Everton: abrir taxa por taxa pra editar era o maior
 * atrito do dia a dia. Esta tela mostra a tabela inteira de um plano - Pix,
 * débito e crédito de 1x a 21x, por grupo de bandeiras e por prazo de
 * recebimento - numa única tela, pré-preenchida com o que já existe, no
 * mesmo desenho visual da tabela que o site mostra pro lojista (grupo de
 * bandeiras lado a lado, débito no topo, Pix embaixo). O nome do plano
 * também é editável aqui, porque a marca às vezes renomeia o plano sem mudar
 * as taxas - não faz sentido isso forçar uma segunda tela.
 *
 * Só mostra as colunas de grupo de bandeiras que a marca de fato usa
 * (`marca->bandeiras()`, mesma fonte que decide o pivot `bandeira_marca`) -
 * mostrar um grupo que a marca não usa (ex.: "Elo" isolado numa marca que só
 * publica "Elo + Outros" junto) é exatamente o tipo de erro que confunde o
 * cliente no site (achado real: SidePay entrou com o grupo errado numa
 * primeira leitura desta mesma etapa).
 *
 * Célula em branco que tinha valor = a taxa foi apagada (a marca não publica
 * mais aquele número - regra 6, ausência é honesta). Célula alterada grava
 * com a fonte/data/status do bloco "Fonte e verificação" desta tela. Célula
 * que não mudou não é tocada - fonte e data de verificação de quem não editou
 * nada continuam sendo as de quando aquele número foi lido de verdade.
 */
class TabelaDoPlano extends Page
{
    protected static string $resource = TaxaDivulgadaResource::class;

    protected string $view = 'filament.resources.taxa-divulgadas.pages.tabela-do-plano';

    protected static bool $shouldRegisterNavigation = false;

    public Plano $plano;

    public ?array $data = [];

    /** @var Collection<int, GrupoBandeira> */
    public Collection $grupos;

    /** @var Collection<int, PrazoRecebimento> */
    public Collection $prazos;

    public function mount(Plano $plano): void
    {
        $this->plano = $plano->load('marca');
        $this->prazos = PrazoRecebimento::query()->orderBy('ordem')->get();
        $this->grupos = $this->grupoBandeirasRelevantes();

        $this->form->fill([
            'nome' => $plano->nome,
            'fonte_tipo' => FonteTipo::SiteOficial->value,
            'data_verificacao' => now()->toDateString(),
            'status' => StatusPublicacao::Rascunho->value,
            'cells' => $this->paraArvore($this->celulasAtuais()),
        ]);
    }

    /**
     * Lê direto do banco, sempre - nunca de uma propriedade privada guardada
     * no mount(). O Livewire só re-hidrata propriedade PÚBLICA entre uma
     * interação e outra (cada clique/edição é uma request nova); uma
     * propriedade privada setada no mount() volta vazia em todo `salvar()`
     * subsequente. Foi exatamente esse bug que fez a primeira versão desta
     * tela regravar a fonte de células que ninguém tocou, e "apagar" nunca
     * apagar nada - pego pelo teste, não por inspeção.
     *
     * @return array<string, float|null>
     */
    private function celulasAtuais(): array
    {
        $cells = [];

        foreach (TaxaDivulgada::query()->where('plano_id', $this->plano->getKey())->get() as $taxa) {
            $percentual = (float) $taxa->percentual;

            if ($taxa->tipo_operacao === TipoOperacao::Pix) {
                $cells["{$taxa->prazo_recebimento_id}.pix"] = $percentual;

                continue;
            }

            $campo = $taxa->tipo_operacao === TipoOperacao::Debito ? 'debito' : "parcelas.{$taxa->parcelas}";
            $cells["{$taxa->prazo_recebimento_id}.{$taxa->grupo_bandeira_id}.{$campo}"] = $percentual;
        }

        return $cells;
    }

    /**
     * Os grupos de bandeira que a marca deste plano realmente usa, mais
     * qualquer grupo que já tenha taxa gravada aqui mesmo sem estar no
     * cadastro de bandeiras da marca (nunca esconder dado que já existe).
     */
    private function grupoBandeirasRelevantes(): Collection
    {
        $doCadastro = $this->plano->marca->bandeiras()->pluck('bandeira_marca.grupo_bandeira_id');

        $daCarga = TaxaDivulgada::query()
            ->where('plano_id', $this->plano->getKey())
            ->where('tipo_operacao', '!=', TipoOperacao::Pix->value)
            ->pluck('grupo_bandeira_id');

        $ids = $doCadastro->merge($daCarga)->unique()->values();

        return GrupoBandeira::query()->whereIn('id', $ids)->deCartao()->orderBy('ordem')->get();
    }

    /** @param  array<string, float|null>  $flat */
    private function paraArvore(array $flat): array
    {
        $arvore = [];

        foreach ($flat as $chave => $valor) {
            data_set($arvore, $chave, $valor);
        }

        return $arvore;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plano')
                    ->columns(3)
                    ->components([
                        TextInput::make('nome')
                            ->label('Nome do plano')
                            ->required()
                            ->maxLength(120)
                            ->helperText('A marca às vezes renomeia sem mudar as taxas - troque aqui sem sair da tabela.'),
                    ]),
                Section::make('Fonte e verificação')
                    ->description('Aplicada só às células que você mudar nesta tela. Célula não tocada mantém a fonte e a data que já tinha.')
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
                ...$this->gradeDePrazos(),
            ])
            ->statePath('data');
    }

    private function gradeDePrazos(): array
    {
        $temDadoPorPrazo = collect($this->celulasAtuais())->keys();

        return $this->prazos
            ->map(function (PrazoRecebimento $prazo) use ($temDadoPorPrazo): Section {
                $temDado = $temDadoPorPrazo->contains(fn (string $chave) => str_starts_with($chave, "{$prazo->id}."));

                return Section::make($prazo->nome_exibicao)
                    ->collapsible()
                    ->collapsed(! $temDado)
                    ->components([
                        ...$this->grupos->map(fn (GrupoBandeira $grupo) => Section::make($grupo->nome_exibicao)
                            ->columns(7)
                            ->components([
                                TextInput::make("cells.{$prazo->id}.{$grupo->id}.debito")
                                    ->label('Débito')
                                    ->numeric()->step(0.0001)->minValue(0)->maxValue(100)->suffix('%'),
                                ...collect(range(1, 21))
                                    ->map(fn (int $parcela) => TextInput::make("cells.{$prazo->id}.{$grupo->id}.parcelas.{$parcela}")
                                        ->label($parcela === 1 ? '1x (à vista)' : "{$parcela}x")
                                        ->numeric()->step(0.0001)->minValue(0)->maxValue(100)->suffix('%'))
                                    ->all(),
                            ]))
                            ->all(),
                        TextInput::make("cells.{$prazo->id}.pix")
                            ->label('Pix')
                            ->numeric()->step(0.0001)->minValue(0)->maxValue(100)->suffix('%')
                            ->helperText('Grupo de bandeiras não se aplica ao Pix (grupo técnico próprio).'),
                    ]);
            })
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('voltar')
                ->label('Voltar para a listagem')
                ->color('gray')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->url(fn () => TaxaDivulgadaResource::getUrl()),
        ];
    }

    public function salvar(): void
    {
        $state = $this->form->getState();

        $novo = collect($this->achatarArvore($state['cells'] ?? []));
        $original = collect($this->celulasAtuais());

        $chaves = $novo->keys()->merge($original->keys())->unique();

        $paraGravar = [];
        $paraApagar = [];

        foreach ($chaves as $chave) {
            $valorNovo = $novo->get($chave);
            $valorOriginal = $original->get($chave);

            if (blank($valorNovo) && ! blank($valorOriginal)) {
                $paraApagar[] = $chave;

                continue;
            }

            if (blank($valorNovo)) {
                continue;
            }

            $novoFloat = (float) $valorNovo;
            $mudou = $valorOriginal === null || abs($novoFloat - (float) $valorOriginal) > 0.00001;

            if ($mudou) {
                $paraGravar[$chave] = $novoFloat;
            }
        }

        if (empty($paraGravar) && empty($paraApagar) && $state['nome'] === $this->plano->nome) {
            Notification::make()->warning()->title('Nada mudou')->send();

            return;
        }

        try {
            [$gravadas, $apagadas] = DB::transaction(function () use ($state, $paraGravar, $paraApagar): array {
                if ($state['nome'] !== $this->plano->nome) {
                    $this->plano->update(['nome' => $state['nome']]);
                }

                foreach ($paraGravar as $chave => $percentual) {
                    $this->gravarCelula($chave, $percentual, $state);
                }

                foreach ($paraApagar as $chave) {
                    $this->apagarCelula($chave);
                }

                return [count($paraGravar), count($paraApagar)];
            });
        } catch (DomainException $exception) {
            Notification::make()->danger()->title('Não foi possível salvar')->body($exception->getMessage())->send();

            return;
        }

        $partes = array_filter([
            $gravadas ? "{$gravadas} célula(s) gravada(s)" : null,
            $apagadas ? "{$apagadas} removida(s)" : null,
        ]);

        Notification::make()->success()->title(implode(', ', $partes) ?: 'Nome do plano atualizado')->send();

        $this->mount($this->plano->fresh());
    }

    private function chaveParaTaxa(string $chave): array
    {
        $partes = explode('.', $chave);
        $prazoId = (int) $partes[0];

        if ($partes[1] === 'pix') {
            return [
                'tipo_operacao' => TipoOperacao::Pix->value,
                'grupo_bandeira_id' => GrupoBandeira::where('codigo', GrupoBandeira::PIX)->value('id'),
                'parcelas' => 1,
                'prazo_recebimento_id' => $prazoId,
            ];
        }

        $grupoId = (int) $partes[1];

        if ($partes[2] === 'debito') {
            return [
                'tipo_operacao' => TipoOperacao::Debito->value,
                'grupo_bandeira_id' => $grupoId,
                'parcelas' => 1,
                'prazo_recebimento_id' => $prazoId,
            ];
        }

        $parcela = (int) $partes[3];

        return [
            'tipo_operacao' => ($parcela === 1 ? TipoOperacao::CreditoAvista : TipoOperacao::CreditoParcelado)->value,
            'grupo_bandeira_id' => $grupoId,
            'parcelas' => $parcela,
            'prazo_recebimento_id' => $prazoId,
        ];
    }

    private function gravarCelula(string $chave, float $percentual, array $state): void
    {
        $chaveTaxa = $this->chaveParaTaxa($chave);

        TaxaDivulgada::updateOrCreate(
            ['plano_id' => $this->plano->getKey(), ...$chaveTaxa],
            [
                'percentual' => $percentual,
                'valor_fixo' => 0,
                'url_fonte' => $state['url_fonte'],
                'fonte_tipo' => $state['fonte_tipo'],
                'data_verificacao' => $state['data_verificacao'],
                'verificado_por' => Auth::id(),
                'status' => $state['status'],
            ],
        );
    }

    private function apagarCelula(string $chave): void
    {
        $chaveTaxa = $this->chaveParaTaxa($chave);

        TaxaDivulgada::query()
            ->where('plano_id', $this->plano->getKey())
            ->where($chaveTaxa)
            ->delete();
    }

    /** @return array<string, float|null> */
    private function achatarArvore(array $arvore, string $prefixo = ''): array
    {
        $flat = [];

        foreach ($arvore as $chave => $valor) {
            $caminho = $prefixo === '' ? (string) $chave : "{$prefixo}.{$chave}";

            if (is_array($valor)) {
                $flat = [...$flat, ...$this->achatarArvore($valor, $caminho)];
            } else {
                $flat[$caminho] = $valor;
            }
        }

        return $flat;
    }
}
