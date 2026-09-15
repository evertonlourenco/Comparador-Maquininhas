<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Painel de saúde do admin (etapa 16)
    |--------------------------------------------------------------------------
    |
    | Tudo aqui é opcional de propósito: campo vazio faz o cartão mostrar
    | "sem dado" com o motivo, nunca um número inventado ou um zero que
    | parece afirmação (regra 6, na forma que este painel aplica a si mesmo).
    |
    */

    // Caminho absoluto do log que scripts/backup-comparador.sh escreve no
    // servidor — não é caminho de código, é da conta (ver CLAUDE.md, "Backup:
    // três artefatos"). Em produção: /home/<usuario>/backups/comparador/backup.log.
    // Sem valor, o cartão de backup mostra "sem dado" — nunca supõe o $HOME.
    'backup_log_path' => env('BACKUP_LOG_PATH'),

    // Host cujo certificado TLS o cartão de operação confere. Cai para o host
    // de APP_URL quando não vier setado — em local isso é
    // comparador-maquininhas.test, sem HTTPS, então o cartão mostra "sem
    // dado" ali (comportamento esperado, não bug).
    'dominio_ssl' => env('SAUDE_DOMINIO_SSL', parse_url((string) env('APP_URL'), PHP_URL_HOST)),

    // Etapa 12 deixou a Cloudflare configurada (DNS, SSL, regras), mas não o
    // token da Analytics API — isso fica para quando o tráfego real existir.
    // Sem os dois, o cartão de tráfego fica vazio com aviso, nunca com
    // número de visita inventado.
    'cloudflare' => [
        'token' => env('CLOUDFLARE_API_TOKEN'),
        'zone_id' => env('CLOUDFLARE_ZONE_ID'),
    ],

];
