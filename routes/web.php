<?php

use App\Http\Controllers\ComparadorController;
use App\Http\Controllers\CupomController;
use App\Http\Controllers\EventoCupomController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Etapa 07: o comparador e a home. Nao ha pagina de entrada antes dele — quem
// chega pelo video quer a conta, e uma tela intermediaria so custaria um
// clique. A metodologia (etapa 10) ainda falta e entra na navegacao quando
// existir.
Route::get('/', ComparadorController::class)->name('comparador');

// Etapa 08: listagem de marcas e a pagina individual de cada uma.
Route::get('/maquininhas', [MarcaController::class, 'index'])->name('maquininhas.index');
Route::get('/maquininha/{marca}', [MarcaController::class, 'show'])->name('maquininhas.show');

// Etapa 09: a área de cupons e o rastreamento de clique/cópia.
Route::get('/cupons', [CupomController::class, 'index'])->name('cupons.index');
Route::get('/cupom/{marca}', [CupomController::class, 'show'])->name('cupons.show');
Route::post('/eventos/cupons', EventoCupomController::class)->name('eventos.cupons');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Etapa 06: a folha de amostra da identidade visual. Fora do indice dos
// buscadores (noindex no layout) — e pagina de trabalho, nao de publico.
Route::view('/guia-visual', 'guia-visual')->name('guia-visual');
