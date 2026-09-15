<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Etapa 16: registrado aqui para quem rodar `schedule:work` em local ou tiver
// `schedule:run` no cron. Em produção este servidor não tem `schedule:run`
// agendado (só o script de backup, direto no cron do hPanel — ver
// CLAUDE.md) — até essa peça existir, o jeito que funciona de fato lá é um
// Cron Job do hPanel chamando `php artisan links:verificar` direto, diário.
Schedule::command('links:verificar')->dailyAt('07:00')->timezone('America/Sao_Paulo');
