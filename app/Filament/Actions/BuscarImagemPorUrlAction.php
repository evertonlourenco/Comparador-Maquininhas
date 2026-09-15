<?php

namespace App\Filament\Actions;

use App\Support\ImagensExternas\BuscaDeImagemExterna;
use App\Support\Uploads\ImagemSeguraWebp;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions as AcoesDoFormulario;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Model;

/**
 * Etapa 15: acao de linha para Marca, Equipamento e Bandeira. Recebe uma URL
 * (kit de midia, pagina de imprensa, ou a propria pagina do produto), busca
 * o candidato com App\Support\ImagensExternas\BuscaDeImagemExterna e mostra
 * a pre-visualizacao — nada e gravado ate o admin clicar "Aprovar e salvar".
 *
 * O candidato fica em base64, num campo oculto do proprio formulario: nunca
 * toca em disco antes da aprovacao (regra 10, no sentido mais literal — nem
 * um caminho publico provisorio existe).
 */
final class BuscarImagemPorUrlAction
{
    public static function make(string $campo, string $diretorio, string $rotuloImagem): Action
    {
        return Action::make('buscarImagemPorUrl')
            ->label('Buscar imagem por URL')
            ->icon('heroicon-o-photo')
            ->color('gray')
            ->modalHeading("Buscar {$rotuloImagem} por URL")
            ->modalDescription('Cole a URL do kit de mídia, da página de imprensa ou da própria página do produto. A busca acontece no servidor; nada é gravado até você aprovar a pré-visualização abaixo.')
            ->modalSubmitActionLabel('Aprovar e salvar')
            ->modalWidth('lg')
            ->schema([
                TextInput::make('url')
                    ->label('URL')
                    ->placeholder('https://exemplo.com/imprensa/kit-de-midia')
                    ->required()
                    ->maxLength(2048),
                AcoesDoFormulario::make([
                    Action::make('buscarCandidato')
                        ->label('Buscar')
                        ->color('gray')
                        ->action(function (Get $get, Set $set): void {
                            self::buscarCandidato($get, $set);
                        }),
                ]),
                Hidden::make('candidato_base64'),
                Hidden::make('candidato_origem'),
                Hidden::make('candidato_erro'),
                View::make('filament.imagem-externa.preview')
                    ->viewData(fn (Get $get): array => [
                        'base64' => $get('candidato_base64'),
                        'origem' => $get('candidato_origem'),
                        'erro' => $get('candidato_erro'),
                    ]),
            ])
            ->action(function (array $data, Model $record) use ($campo, $diretorio): void {
                self::aprovarEGravar($data, $record, $campo, $diretorio);
            });
    }

    /** Publico para ser testavel direto, sem montar o modal do Livewire (ver BuscarImagemPorUrlActionTest). */
    public static function buscarCandidato(Get $get, Set $set): void
    {
        $url = trim((string) $get('url'));

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            $set('candidato_erro', 'Cole uma URL válida antes de buscar.');
            $set('candidato_base64', null);
            $set('candidato_origem', null);

            return;
        }

        $resultado = (new BuscaDeImagemExterna())->buscar($url);

        if (! $resultado->sucesso) {
            $set('candidato_erro', $resultado->erro);
            $set('candidato_base64', null);
            $set('candidato_origem', null);

            return;
        }

        $set('candidato_base64', $resultado->webpBase64);
        $set('candidato_origem', $resultado->encontradaEm);
        $set('candidato_erro', null);
    }

    /**
     * Publico para ser testavel direto (ver BuscarImagemPorUrlActionTest): a
     * garantia central da etapa 15 e que nada e gravado sem passar por aqui,
     * e isso precisa ser provado sem depender da mecanica do modal do
     * Livewire.
     *
     * @param  array<string, mixed>  $data
     */
    public static function aprovarEGravar(array $data, Model $record, string $campo, string $diretorio): void
    {
        $base64 = $data['candidato_base64'] ?? null;

        if (blank($base64)) {
            Notification::make()
                ->danger()
                ->title('Busque uma imagem e confira a pré-visualização antes de aprovar.')
                ->send();

            return;
        }

        $bytesWebp = base64_decode((string) $base64, strict: true);

        if ($bytesWebp === false) {
            Notification::make()
                ->danger()
                ->title('Não foi possível ler a imagem buscada — busque de novo.')
                ->send();

            return;
        }

        $caminho = ImagemSeguraWebp::gravar($bytesWebp, $diretorio);

        if ($caminho === null) {
            Notification::make()
                ->danger()
                ->title('A imagem buscada não passou na validação final — tente outra URL.')
                ->send();

            return;
        }

        $record->update([$campo => $caminho]);

        Notification::make()
            ->success()
            ->title('Imagem salva.')
            ->send();
    }
}
