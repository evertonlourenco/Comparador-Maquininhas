<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

/**
 * Botão para o pedido do Everton em 17/09/2026 (etapa 18, na prática): a
 * regra 9 manda o comparador ler um arquivo gerado, não o banco direto — mas
 * até aqui isso só rodava por SSH (`comparador:gerar-json`), que ele copiou
 * errado do manual e travou o terminal esperando o fecha-aspas. Este botão
 * chama exatamente o mesmo comando artisan, só que em processo (sem SSH,
 * sem shell, sem aspas para errar), a partir de dentro do próprio painel.
 *
 * Não elimina o passo — regenerar o arquivo continua sendo necessário depois
 * de aprovar taxa, pelo motivo que já valia: o comparador não consulta o
 * banco por visita (regra 9), então só automatiza o "apertar o botão", não a
 * decisão de quando apertar.
 */
final class GerarJsonDoComparadorAction
{
    public static function make(): Action
    {
        return Action::make('gerarJsonDoComparador')
            ->label('Gerar JSON do comparador')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->tooltip('O site lê um arquivo gerado, não o banco direto (regra 9) — rode isto depois de aprovar taxas para o site refletir a mudança.')
            ->requiresConfirmation()
            ->modalHeading('Gerar JSON do comparador')
            ->modalDescription('Reescreve o arquivo público que o comparador lê no navegador, com o que está aprovado agora no banco de dados. Não publica nem aprova nada sozinho — só espelha o que você já aprovou.')
            ->modalSubmitActionLabel('Gerar agora')
            ->action(function (): void {
                $codigo = Artisan::call('comparador:gerar-json');

                if ($codigo !== Command::SUCCESS) {
                    Notification::make()
                        ->danger()
                        ->title('Não deu para gerar o JSON')
                        ->body(Artisan::output())
                        ->send();

                    return;
                }

                $caminho = public_path('dados/comparador.json');
                $dados = json_decode(File::get($caminho), true);

                $marcas = count($dados['marcas'] ?? []);
                $taxas = collect($dados['marcas'] ?? [])
                    ->flatMap(fn (array $marca): array => $marca['planos'] ?? [])
                    ->sum(fn (array $plano): int => count($plano['taxas'] ?? []) + count($plano['faixas'] ?? []));

                Notification::make()
                    ->success()
                    ->title('JSON do comparador atualizado')
                    ->body("{$marcas} marca(s), {$taxas} taxa(s)/faixa(s) publicadas. O site já está mostrando o que foi aprovado até agora.")
                    ->send();
            });
    }
}
