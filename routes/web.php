<?php

use App\Http\Controllers\ComparadorController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Etapa 07: o comparador e a home. Nao ha pagina de entrada antes dele — quem
// chega pelo video quer a conta, e uma tela intermediaria so custaria um
// clique. O conteudo de apoio (metodologia, marcas, cupons) sai nas etapas
// 08 a 10 e entra na navegacao quando existir.
Route::get('/', ComparadorController::class)->name('comparador');

// Etapa 08: listagem de marcas e a pagina individual de cada uma.
Route::get('/maquininhas', [MarcaController::class, 'index'])->name('maquininhas.index');
Route::get('/maquininha/{marca}', [MarcaController::class, 'show'])->name('maquininhas.show');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Etapa 06: a folha de amostra da identidade visual. Fora do indice dos
// buscadores (noindex no layout) — e pagina de trabalho, nao de publico.
Route::view('/guia-visual', 'guia-visual')->name('guia-visual');
