<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SendInductionReminders;


// SLA — verificación cada 15 minutos
Schedule::command('cmms:check-sla')
    ->everyFifteenMinutes()
    ->name('check-sla-breaches')
    ->withoutOverlapping();

// Comandos artisan para el módulo de Planes de Mantenimientoenv
Schedule::command('maintenance:check-due-plans')
    ->twiceDaily(6, 18) // 6 AM y 6 PM
    ->name('check-due-maintenance-plans')
    ->withoutOverlapping()
    ->onSuccess(function () {
        Log::info('Scheduler: Verificación de planes de mantenimiento completada exitosamente');
    })
    ->onFailure(function () {
        Log::error('Scheduler: Error al verificar planes de mantenimiento');
    });
