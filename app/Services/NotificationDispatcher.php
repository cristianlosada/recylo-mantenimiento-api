<?php

namespace App\Services;

use App\Events\SlaAlertTriggered;
use App\Events\WorkOrderUpdated;
use App\Events\WorkRequestUpdated;
use App\Models\WorkOrder;
use App\Models\WorkRequest;

/**
 * Punto central de control para todas las notificaciones en tiempo real.
 *
 * Para activar o desactivar un tipo de notificación, basta con cambiar
 * su valor a true/false en los arrays de configuración de esta clase.
 * Los controladores no contienen lógica de activación — solo llaman
 * a los métodos estáticos de este dispatcher.
 */
class NotificationDispatcher
{
    // ── Work Order ────────────────────────────────────────────────────────────
    // Controla qué eventos de OT disparan broadcast (WebSocket) + notificación.
    private static array $workOrder = [
        'assigned'       => true,   // técnico asignado
        'completed'      => true,   // OT completada
        'validated'      => true,   // OT validada/rechazada por supervisor
        'cancelled'      => true,   // OT cancelada
        'reopened'       => true,   // OT reabierta
        'status_changed' => false,  // start / pause / resume  (aún no)
        'comment_added'  => false,  // nuevo comentario         (aún no)
    ];

    // ── Work Request ──────────────────────────────────────────────────────────
    private static array $workRequest = [
        'created'        => true,   // nueva solicitud creada
        'approved'       => true,   // solicitud aprobada
        'rejected'       => true,   // solicitud rechazada
        'comment_added'  => false,  // nuevo comentario         (aún no)
    ];

    // ── SLA ───────────────────────────────────────────────────────────────────
    private static array $sla = [
        'sla_warning'    => true,   // próximo a vencer (≤ 30 min)
        'sla_breached'   => true,   // SLA incumplido
    ];

    // ── Dispatchers ───────────────────────────────────────────────────────────

    public static function workOrder(WorkOrder $workOrder, string $eventType): void
    {
        if (self::$workOrder[$eventType] ?? false) {
            WorkOrderUpdated::dispatch($workOrder, $eventType);
        }
    }

    public static function workRequest(WorkRequest $workRequest, string $eventType): void
    {
        if (self::$workRequest[$eventType] ?? false) {
            WorkRequestUpdated::dispatch($workRequest, $eventType);
        }
    }

    public static function sla(WorkRequest $workRequest, string $alertType): void
    {
        if (self::$sla[$alertType] ?? false) {
            SlaAlertTriggered::dispatch($workRequest, $alertType);
        }
    }
}
