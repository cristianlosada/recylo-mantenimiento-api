<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // channels se registra manualmente en AppServiceProvider con middleware api+sanctum
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
        
        // Habilitar CORS y leer tokens desde cookies para rutas API
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\EnsureCookieToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Manejar errores de autenticación en rutas API
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Http\Responses\ApiResponse::unauthorized('No autenticado. Token inválido o faltante.');
            }
        });

        // Manejar errores de validación
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Http\Responses\ApiResponse::validation($e->errors(), 'Datos de validación incorrectos');
            }
        });

        // Manejar errores de ruta no encontrada
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return \App\Http\Responses\ApiResponse::notFound('Endpoint no encontrado');
            }
        });

        // Manejar errores generales
        $exceptions->render(function (\Exception $e, $request) {
            if (($request->is('api/*') || $request->expectsJson()) && !config('app.debug')) {
                return \App\Http\Responses\ApiResponse::error('Error interno del servidor', 500);
            }
        });
    })->create();
