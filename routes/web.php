<?php

use App\Http\Controllers\ComparadorController;
use App\Http\Controllers\CupomController;
use App\Http\Controllers\EventoCupomController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\PropostaController;
use App\Http\Controllers\RelatoTaxaIncorretoController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\SiteEmBreve;
use Illuminate\Support\Facades\Route;

/*
| SiteEmBreve fecha TODAS as rotas publicas de uma vez (503) enquanto
| SITE_EM_BREVE estiver ligado no .env — ver config/site.php. O painel do
| Filament registra as proprias rotas no AdminPanelProvider e fica de fora
| deste grupo de proposito: e por ele que as taxas sao aprovadas enquanto o
| site publico espera a identidade visual.
|
| Nao se usa `artisan down` aqui porque aquele derruba o painel junto.
*/
Route::middleware(SiteEmBreve::class)->group(function (): void {

// Etapa 07: o comparador e a home. Nao ha pagina de entrada antes dele — quem
// chega pelo video quer a conta, e uma tela intermediaria so custaria um
// clique.
Route::get('/', ComparadorController::class)->name('comparador');

// Etapa 08: listagem de marcas e a pagina individual de cada uma.
Route::get('/maquininhas', [MarcaController::class, 'index'])->name('maquininhas.index');
Route::get('/maquininha/{marca}', [MarcaController::class, 'show'])->name('maquininhas.show');

// Etapa 09: a área de cupons e o rastreamento de clique/cópia.
Route::get('/cupons', [CupomController::class, 'index'])->name('cupons.index');
Route::get('/cupom/{marca}', [CupomController::class, 'show'])->name('cupons.show');
Route::post('/eventos/cupons', EventoCupomController::class)->name('eventos.cupons');

// Etapa 10: metodologia, LGPD, captação de relatos e o botão "reportar taxa
// errada" reaproveitável (ver x-tabela-taxas e x-formulario-taxa-incorreta).
Route::view('/metodologia', 'metodologia')->name('metodologia');
Route::view('/privacidade', 'privacidade')->name('privacidade');
Route::view('/termos', 'termos')->name('termos');

Route::get('/enviar-proposta', [PropostaController::class, 'create'])->name('propostas.create');
Route::post('/enviar-proposta', [PropostaController::class, 'store'])
    ->middleware('throttle:propostas')->name('propostas.store');

Route::post('/eventos/taxa-incorreta', RelatoTaxaIncorretoController::class)
    ->middleware('throttle:relatos-taxa')->name('eventos.taxa-incorreta');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Etapa 06: a folha de amostra da identidade visual. Fora do indice dos
// buscadores (noindex no layout) — e pagina de trabalho, nao de publico.
Route::view('/guia-visual', 'guia-visual')->name('guia-visual');

});
