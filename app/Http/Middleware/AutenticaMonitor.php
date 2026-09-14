<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Etapa 13: protege /api/monitor/* — a unica porta de entrada que o
 * repositorio Node do monitor de mudancas usa para falar com este app.
 * Token fixo comparado por hash_equals (evita timing attack), nunca
 * Sanctum/sessao: quem chama nao e um navegador, e uma Action do GitHub ou
 * um cron no Mac do Everton.
 */
class AutenticaMonitor
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = config('services.monitor.token');
        $recebido = (string) $request->bearerToken();

        if (! $token || ! hash_equals($token, $recebido)) {
            abort(401, 'Token do monitor ausente ou invalido.');
        }

        return $next($request);
    }
}
