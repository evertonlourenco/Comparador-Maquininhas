<?php

namespace App\Filament\Actions;

use App\Models\Marca;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * A trava da marca (etapa 19, 17/09/2026) — pedido do Everton depois de a
 * mensalidade nula do SidePay/FacilityPay ter passado despercebido numa
 * revisão manual. Só clicável quando App\Support\Saude\CompletudeDaMarca
 * diz que a marca fecha conta nas quatro formas de pagamento, com logo e
 * foto de equipamento — a mesma conferência que o motor faz na tela pública,
 * rodada aqui antes, não depois.
 *
 * Clicar de novo numa marca já aprovada revoga: existe para o caso raro mas
 * real de um dado que ficou completo virar incompleto depois (uma taxa
 * despublicada, um equipamento trocado) — a proxima geracao do JSON ja
 * tiraria a marca sozinha (CatalogoDoComparador::apenasAprovadasECompletas),
 * mas revogar a mao deixa a intencao registrada, e nao so um efeito colateral
 * silencioso da proxima vez que alguem gerar o arquivo.
 */
final class AprovarMarcaAction
{
    public static function make(): Action
    {
        return Action::make('aprovarMarca')
            ->label(fn (Marca $record): string => $record->aprovada_em !== null ? 'Aprovada' : 'Aprovar marca')
            ->icon(fn (Marca $record): Heroicon => $record->aprovada_em !== null
                ? Heroicon::OutlinedCheckBadge
                : Heroicon::OutlinedShieldCheck)
            ->color(fn (Marca $record): string => match (true) {
                $record->aprovada_em !== null && $record->completude['completa'] => 'success',
                $record->aprovada_em !== null => 'danger',
                $record->completude['completa'] => 'warning',
                default => 'gray',
            })
            ->disabled(fn (Marca $record): bool => $record->aprovada_em === null && ! $record->completude['completa'])
            ->tooltip(fn (Marca $record): string => match (true) {
                $record->aprovada_em !== null && $record->completude['completa'] => 'Aprovada em '
                    .$record->aprovada_em->format('d/m/Y H:i').'. Clique para revogar.',
                $record->aprovada_em !== null => 'Aprovada, mas um dado obrigatório ficou incompleto depois — a próxima geração do JSON já tira ela do site sozinha. Clique para revogar agora.',
                $record->completude['completa'] => 'Completa e pronta para o site. Clique para aprovar.',
                default => 'Ainda falta dado obrigatório — veja "Pendências" antes de aprovar.',
            })
            ->requiresConfirmation()
            ->modalHeading(fn (Marca $record): string => $record->aprovada_em !== null
                ? "Revogar aprovação de {$record->nome}?"
                : "Aprovar {$record->nome}?")
            ->modalDescription(fn (Marca $record): string => $record->aprovada_em !== null
                ? 'Ela sai do comparador, da própria página e do cupom assim que o JSON for gerado de novo — mesmo que os dados continuem no banco.'
                : 'Ela passa a poder aparecer no comparador, na própria página e no cupom assim que o JSON for gerado de novo. Nada sai no ar até isso acontecer.')
            ->modalSubmitActionLabel(fn (Marca $record): string => $record->aprovada_em !== null ? 'Revogar' : 'Aprovar')
            ->action(function (Marca $record): void {
                $estavaAprovada = $record->aprovada_em !== null;

                $record->update(['aprovada_em' => $estavaAprovada ? null : now()]);

                Notification::make()
                    ->success()
                    ->title($estavaAprovada ? 'Aprovação revogada' : 'Marca aprovada')
                    ->body('Rode "Gerar JSON do comparador" para o site refletir isso.')
                    ->send();
            });
    }
}
