<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Etapa 06: a folha de amostra da identidade visual. Fora do indice dos
// buscadores (noindex no layout) — e pagina de trabalho, nao de publico.
Route::view('/guia-visual', 'guia-visual')->name('guia-visual');
