<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Etapa 10: só carrega o gtag.js depois do aceite no banner de cookies
    // (resources/views/components/banner-cookies.blade.php). Vazio até a
    // etapa de lançamento — o gate já funciona, só falta o ID.
    'ga4' => [
        'id' => env('GA4_MEASUREMENT_ID'),
    ],

    // Etapa 12: mesmo gate do GA4 — so carrega depois do aceite no banner de
    // cookies, e so existe a meta tag quando o ID esta preenchido.
    'clarity' => [
        'id' => env('CLARITY_PROJECT_ID'),
    ],

    // Etapa 10: identidade do controlador para /privacidade e /termos.
    // Ainda não definida — nome final da ferramenta, CNPJ e e-mail de
    // contato ficam para a etapa de lançamento (ver "Pendente da etapa 10"
    // no CLAUDE.md). As views mostram um texto honesto de "a definir"
    // enquanto isto estiver vazio — nunca um dado inventado.
    'legal' => [
        'razao_social' => env('LEGAL_RAZAO_SOCIAL'),
        'cnpj' => env('LEGAL_CNPJ'),
        'email_contato' => env('LEGAL_EMAIL_CONTATO'),
    ],

];
