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
        // Zona horaria del servidor configurada desde el sistema (Configuración > Hora del servidor).
        // Si no hay ajuste guardado, se usa APP_TIMEZONE del .env.
        try {
            $zona = \App\Models\AjusteSistema::obtener('zona_horaria');
            if ($zona && in_array($zona, \DateTimeZone::listIdentifiers(), true)) {
                config(['app.timezone' => $zona]);
                date_default_timezone_set($zona);
            }
        } catch (\Throwable $e) {
            // Sin base de datos disponible (p. ej. antes de migrar): se mantiene APP_TIMEZONE.
        }

        // Límite de intentos de login: 5 por minuto por combinación email + IP
        // (frena ataques de fuerza bruta y de relleno de credenciales).
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        // Límite general para la API autenticada y pública.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
