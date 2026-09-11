<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Modo "em breve"
    |--------------------------------------------------------------------------
    |
    | Fecha o site publico e deixa o painel (/admin) de pe. Existe porque o
    | portal foi para producao na etapa 11 antes de ter identidade visual
    | propria, e nao faz sentido receber visita — nem ser indexado — com a
    | cara provisoria da etapa 06 e sem nenhuma taxa publicada.
    |
    | NAO e o `artisan down` do Laravel: aquele derruba tudo, inclusive o
    | painel, que precisa continuar acessivel para aprovar taxas e cadastrar
    | dados enquanto o site publico espera.
    |
    | Desligar e trocar SITE_EM_BREVE para false no .env e refazer o
    | config:cache (o deploy.sh ja faz).
    |
    */

    'em_breve' => env('SITE_EM_BREVE', false),

];
