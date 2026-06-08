<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;
use App\Models\Asset;
use App\Observers\AssetObserver;

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
        Asset::observe(AssetObserver::class);

        // Registrar /broadcasting/auth bajo middleware api (incluye EnsureCookieToken)
        // para que el token de la cookie HttpOnly sea reconocido por Sanctum.
        // No se usa channels: en withRouting() para evitar que el framework
        // lo registre primero con middleware web (que ignora la cookie).
        Broadcast::routes(['middleware' => ['api', 'auth:sanctum']]);
        require base_path('routes/channels.php');
    }
}
