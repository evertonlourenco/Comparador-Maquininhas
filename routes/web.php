<?php

use App\Http\Controllers\ComparadorController;
use Illuminate\Support\Facades\Route;

// Etapa 07: o comparador e a home. Nao ha pagina de entrada antes dele — quem
// chega pelo video quer a conta, e uma tela intermediaria so custaria um
// clique. O conteudo de apoio (metodologia, marcas, cupons) sai nas etapas
// 08 a 10 e entra na navegacao quando existir.
Route::get('/', ComparadorController::class)->name('comparador');

// Etapa 06: a folha de amostra da identidade visual. Fora do indice dos
// buscadores (noindex no layout) — e pagina de trabalho, nao de publico.
Route::view('/guia-visual', 'guia-visual')->name('guia-visual');
