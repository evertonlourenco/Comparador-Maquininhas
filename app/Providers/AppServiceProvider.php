<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Etapa 10: sem CAPTCHA de terceiro — limite por IP e a segunda
        // camada, ao lado do honeypot de App\Support\Antispam\FormularioProtegido.
        RateLimiter::for('propostas', fn (Request $request) => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('relatos-taxa', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
    }
}
