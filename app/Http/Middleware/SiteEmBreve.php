<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fecha o site publico enquanto a identidade visual nao existe (etapa 11+).
 *
 * Responde 503, e nao 200, de proposito. Um "em breve" servido com 200 e uma
 * pagina real aos olhos do Google: ele indexa, e depois o dominio aparece na
 * busca com esse texto por semanas. 503 com Retry-After e o sinal de
 * indisponibilidade temporaria — o buscador nao indexa e volta depois. E como
 * o sinal e o proprio codigo de status, ele se desfaz sozinho quando a flag
 * sair; nao ha robots.txt para lembrar de reverter.
 *
 * So alcanca as rotas de routes/web.php. O painel do Filament registra as
 * proprias rotas no AdminPanelProvider e continua de pe.
 */
class SiteEmBreve
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('site.em_breve')) {
            return $next($request);
        }

        return response()
            ->view('em-breve', status: 503)
            ->header('Retry-After', (string) (60 * 60 * 24 * 7));
    }
}
