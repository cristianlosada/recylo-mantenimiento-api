<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetActivityLog extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'asset_activity_log';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'asset_id',
        'activity_type',
        'title',
        'description',
        'work_order_id',
        'work_request_id',
        'maintenance_plan_id',
        'metadata',
        'performed_by',
        'performed_at',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'metadata' => 'array',
        'performed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Constantes para tipos de actividad
    const TYPE_WORK_ORDER_CREATED = 'work_order_created';
    const TYPE_WORK_ORDER_STARTED = 'work_order_started';
    const TYPE_WORK_ORDER_COMPLETED = 'work_order_completed';
    const TYPE_WORK_ORDER_CANCELLED = 'work_order_cancelled';
    
    const TYPE_WORK_REQUEST_CREATED = 'work_request_created';
    const TYPE_WORK_REQUEST_APPROVED = 'work_request_approved';
    const TYPE_WORK_REQUEST_REJECTED = 'work_request_rejected';
    
    const TYPE_MAINTENANCE_PLAN_ADDED = 'maintenance_plan_added';
    const TYPE_MAINTENANCE_PLAN_EXECUTED = 'maintenance_plan_executed';
    
    const TYPE_MEASUREMENT_ADDED = 'measurement_added';
    const TYPE_MEASUREMENT_ALERT = 'measurement_alert';
    
    const TYPE_NOTE_ADDED = 'note_added';
    const TYPE_ATTACHMENT_ADDED = 'attachment_added';
    const TYPE_SPARE_PART_ADDED = 'spare_part_added';
    
    const TYPE_ASSET_STATUS_CHANGED = 'asset_status_changed';
    const TYPE_ASSET_UPDATED = 'asset_updated';

    // ===================================
    // RELATIONSHIPS
    // ===================================

    /**
     * Activo al que pertenece la actividad
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Orden de trabajo relacionada
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Solicitud relacionada
     */
    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class);
    }

    /**
     * Plan de mantenimiento relacionado
     */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /**
     * Usuario que realizó la acción
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    // ===================================
    // MÉTODOS ESTÁTICOS
    // ===================================

    /**
     * Registrar una actividad en el log del activo
     *
     * @param int $assetId
     * @param string $activityType
     * @param string $title
     * @param string|null $description
     * @param array $options Opciones: work_order_id, work_request_id, maintenance_plan_id, metadata, performed_by
     * @return self
     */
    public static function log(
        int $assetId,
        string $activityType,
        string $title,
        ?string $description = null,
        array $options = []
    ): self {
        return self::create([
            'asset_id' => $assetId,
            'activity_type' => $activityType,
            'title' => $title,
            'description' => $description,
            'work_order_id' => $options['work_order_id'] ?? null,
            'work_request_id' => $options['work_request_id'] ?? null,
            'maintenance_plan_id' => $options['maintenance_plan_id'] ?? null,
            'metadata' => $options['metadata'] ?? null,
            'performed_by' => $options['performed_by'] ?? auth()->id(),
            'performed_at' => now(),
        ]);
    }
}
