<?php

namespace App\Filament\Widgets;

use App\Support\Saude\ClienteAnalyticsCloudflare;
use Filament\Widgets\Widget;

/**
 * Etapa 16, prioridade 6: tráfego real, lido da Analytics API da Cloudflare
 * quando ela estiver configurada (server-side, independe de consentimento de
 * cookie). Sem token/zona, o cartão fica preparado e vazio, com o motivo à
 * vista — nunca um número de visita inventado.
 */
class TrafegoWidget extends Widget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.trafego';

    /** @return array{sem_dado: bool, motivo: ?string, dias: array} */
    public function getAcessos(): array
    {
        return ClienteAnalyticsCloudflare::acessosDiarios();
    }
}
