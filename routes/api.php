<?php

use App\Http\Controllers\Api\MonitorController;
use App\Http\Middleware\AutenticaMonitor;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Etapa 13: API do monitor de mudanças
|--------------------------------------------------------------------------
|
| Única API deste app. Chamada só pelo repositório Node separado do monitor
| de mudanças — nunca pelo navegador. Continua de pé mesmo com
| SITE_EM_BREVE=true, porque esse middleware só alcança routes/web.php.
*/
Route::middleware(AutenticaMonitor::class)->prefix('monitor')->group(function (): void {
    Route::post('deteccoes', [MonitorController::class, 'registrarDeteccao']);
    Route::get('resumo-semanal', [MonitorController::class, 'resumoSemanal']);
});
