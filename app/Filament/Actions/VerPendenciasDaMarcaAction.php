<?php

namespace App\Filament\Actions;

use App\Models\Marca;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * O lado "fácil e visual" da trava (etapa 19, 17/09/2026): antes disso, saber
 * o que falta numa marca exigia ler JSON gerado ou reconferir campo a campo
 * no formulário. Aqui é a mesma lista que App\Support\Saude\CompletudeDaMarca
 * calcula — a que também decide se "Aprovar marca" fica clicável.
 */
final class VerPendenciasDaMarcaAction
{
    public static function make(): Action
    {
        return Action::make('verPendenciasDaMarca')
            ->label('Pendências')
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->color(fn (Marca $record): string => $record->completude['completa'] ? 'success' : 'warning')
            ->badge(fn (Marca $record): ?string => $record->completude['completa']
                ? null
                : (string) count($record->completude['pendencias']))
            ->badgeColor('warning')
            ->modalHeading(fn (Marca $record): string => "Pendências — {$record->nome}")
            ->modalContent(fn (Marca $record) => view('filament.actions.pendencias-da-marca', [
                'pendencias' => $record->completude['pendencias'],
                'completa' => $record->completude['completa'],
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar');
    }
}
