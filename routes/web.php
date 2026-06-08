<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect(env('FRONTEND_URL', 'https://mantenimiento.recylo.com'));
});

// Ruta temporal para evitar el error "Route [login] not defined"
// Esta ruta nunca debería ser usada en una API, pero evita el error
Route::get('/login', function () {
    return response()->json([
        'message' => 'Esta es una API. Usa POST /api/auth/login para autenticarte.',
        'error' => 'Use API endpoint'
    ], 404);
})->name('login');
