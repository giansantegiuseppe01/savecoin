<?php

namespace App\Providers;

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
        // Crear directorio de backups si no existe
        $backupDir = storage_path('backups');
        if (! file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
    }
}
