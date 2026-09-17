<?php

namespace App\Filament\Pages;

use App\Filament\Actions\GerarJsonDoComparadorAction;
use Filament\Actions\Action;

/**
 * Dashboard padrão do Filament, só com um botão a mais no cabeçalho: gerar o
 * JSON do comparador sem precisar de SSH (ver GerarJsonDoComparadorAction).
 * Fica aqui também, e não só perto de onde a taxa é aprovada, porque é o
 * primeiro lugar que o Everton vê ao entrar no painel — não depende de
 * lembrar em qual tela ele aprovou algo.
 */
class Dashboard extends \Filament\Pages\Dashboard
{
    protected function getHeaderActions(): array
    {
        return [
            GerarJsonDoComparadorAction::make(),
        ];
    }
}
