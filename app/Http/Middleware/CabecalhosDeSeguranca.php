<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Etapa 12: X-Content-Type-Options, Referrer-Policy e Permissions-Policy em
 * toda resposta. A Content-Security-Policy so entra fora do /admin -- o
 * Filament embute o proprio Alpine e mexe com estilo inline em varios
 * componentes (seletor de cor, por exemplo), e o painel e usado todo dia
 * para aprovar taxa: testar uma politica estrita ali sem a cobertura que
 * ComponentesDoPortalTest da ao site publico arrisca quebrar o que ja
 * funciona. Global (bootstrap/app.php), nao so no grupo `web`, para cobrir
 * tambem o `/up` de health-check e qualquer rota futura.
 */
class CabecalhosDeSeguranca
{
    public function handle(Request $request, Closure $next): Response
    {
        // Compartilhado antes do proximo middleware/controller rodar, para
        // que a view (renderizada mais adiante na mesma requisicao) tenha
        // o valor pronto quando ligar :nonce no <script> inline do layout.
        $nonce = Str::random(32);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Nega por padrao capacidades que este site nao usa em lugar nenhum
        // — nem geolocalizacao, nem microfone/camera, nem os outros dois.
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=(), payment=(), usb=()',
        );

        if (! $request->is('admin*')) {
            $response->headers->set('Content-Security-Policy', $this->politica($nonce));
        }

        return $response;
    }

    /**
     * Um domínio por script/estilo/conexao de verdade, nada generico.
     *
     * `'unsafe-eval'` e o único item que pesa: o Alpine.js (comparador,
     * etapa 07) resolve `x-data`/`x-on`/`x-text` com `new Function()` na
     * build padrao dele — so a build dedicada @alpinejs/csp evita isso, e
     * trocar exigiria reescrever varias expressoes JS direto no Blade
     * (campoTexto, ternarios, concatenacao de slug) sem garantia de que
     * tudo continua igual. Fica registrado como divida, nao decisao final.
     */
    private function politica(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://www.googletagmanager.com https://www.clarity.ms",
            // 'unsafe-inline' aqui, nao em script-src: o Alpine (x-show,
            // x-transition) escreve direto em element.style via JS, e CSP
            // trata isso como estilo inline. Risco bem menor que a mesma
            // permissao em script — nao da para rodar codigo por CSS.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https://www.googletagmanager.com https://www.google-analytics.com https://*.google-analytics.com",
            "font-src 'self'",
            "connect-src 'self' https://www.googletagmanager.com https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://www.clarity.ms https://*.clarity.ms",
            "frame-src https://www.youtube-nocookie.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
