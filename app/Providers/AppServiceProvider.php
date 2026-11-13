<?php

namespace App\Providers;

use Illuminate\Support\Facades\Redis;
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
        // Limpiar anomalías residuales de Redis al iniciar la aplicación
        // Esto previene que usuarios nuevos vean alertas de sesiones anteriores
        try {
            Redis::del('telemetry:anomaly');
        } catch (\Throwable $e) {
            // Si Redis no está disponible aún, no es crítico
            // La aplicación seguirá funcionando
        }
    }
}
