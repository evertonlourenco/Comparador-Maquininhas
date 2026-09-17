<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Etapa 18: manual de uso do painel para quem não é programador. Página
 * dentro do próprio painel (não PDF — PDF envelhece numa pasta e ninguém
 * reabre), sempre na versão que bate com o código no ar.
 */
class Manual extends Page
{
    protected static ?string $slug = 'manual';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?string $navigationLabel = 'Manual do administrador';

    protected static ?int $navigationSort = -1;

    protected string $view = 'filament.pages.manual';

    public function getTitle(): string
    {
        return 'Manual do administrador';
    }
}
