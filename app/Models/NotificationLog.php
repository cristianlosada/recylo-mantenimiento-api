<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'notification_logs';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'notification_type',
        'event_type',
        'channel',
        'status',
        'work_order_id',
        'work_request_id',
        'asset_id',
        'recipient_email',
        'recipient_user_id',
        'subject',
        'message',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'opened_at',
        'error_message',
        'retry_count',
        'metadata',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Constantes para tipos de notificación
     */
    const TYPE_WORK_ORDER = 'work_order';
    const TYPE_WORK_REQUEST = 'work_request';

    /**
     * Constantes para eventos
     */
    const EVENT_CREATE = 'create';
    const EVENT_OPEN = 'open';
    const EVENT_CLOSE = 'close';
    const EVENT_APPROVE = 'approve';
    const EVENT_REJECT = 'reject';

    /**
     * Constantes para canales
     */
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_PUSH = 'push';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_IN_APP = 'in_app';

    /**
     * Constantes para estados
     */
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';

    // ===================================
    // RELATIONSHIPS
    // ===================================

    /**
     * Orden de trabajo relacionada
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    /**
     * Solicitud de trabajo relacionada
     */
    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class, 'work_request_id');
    }

    /**
     * Activo relacionado
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Usuario destinatario (opcional)
     */
    public function recipientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    // ===================================
    // SCOPES
    // ===================================

    /**
     * Scope para notificaciones de órdenes de trabajo
     */
    public function scopeWorkOrderNotifications($query)
    {
        return $query->where('notification_type', self::TYPE_WORK_ORDER);
    }

    /**
     * Scope para notificaciones de solicitudes
     */
    public function scopeWorkRequestNotifications($query)
    {
        return $query->where('notification_type', self::TYPE_WORK_REQUEST);
    }

    /**
     * Scope para notificaciones pendientes
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope para notificaciones enviadas
     */
    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope para notificaciones fallidas
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope para un evento específico
     */
    public function scopeByEvent($query, string $event)
    {
        return $query->where('event_type', $event);
    }

    /**
     * Scope para un activo específico
     */
    public function scopeByAsset($query, int $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    /**
     * Scope para un email específico
     */
    public function scopeByEmail($query, string $email)
    {
        return $query->where('recipient_email', $email);
    }

    /**
     * Scope para un rango de fechas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    // ===================================
    // MÉTODOS AUXILIARES
    // ===================================

    /**
     * Marcar notificación como enviada
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Marcar notificación como fallida
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Marcar notificación como entregada
     */
    public function markAsDelivered(): void
    {
        $this->update([
            'delivered_at' => now(),
        ]);
    }

    /**
     * Marcar notificación como abierta
     */
    public function markAsOpened(): void
    {
        $this->update([
            'opened_at' => now(),
        ]);
    }

    /**
     * Obtener label del tipo de notificación
     */
    public function getNotificationTypeLabel(): string
    {
        return match ($this->notification_type) {
            self::TYPE_WORK_ORDER => 'Orden de Trabajo',
            self::TYPE_WORK_REQUEST => 'Solicitud de Trabajo',
            default => $this->notification_type,
        };
    }

    /**
     * Obtener label del evento
     */
    public function getEventLabel(): string
    {
        return match ($this->event_type) {
            self::EVENT_CREATE => 'Creación',
            self::EVENT_OPEN => 'Inicio',
            self::EVENT_CLOSE => 'Cierre/Completado',
            self::EVENT_APPROVE => 'Aprobación',
            self::EVENT_REJECT => 'Rechazo',
            default => $this->event_type,
        };
    }

    /**
     * Obtener label del estado
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_SENT => 'Enviada',
            self::STATUS_FAILED => 'Fallida',
            self::STATUS_BOUNCED => 'Rechazada',
            default => $this->status,
        };
    }
}
