<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanExecution extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'maintenance_plan_executions';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'maintenance_plan_id',
        'work_order_id',
        'scheduled_date',
        'executed_date',
        'meter_reading_at_execution',
        'status',
        'notes',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'scheduled_date' => 'datetime',
        'executed_date' => 'datetime',
        'meter_reading_at_execution' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =====================================================
    // CONSTANTES: Estados de Ejecución
    // =====================================================

    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_SKIPPED = 'skipped';
    const STATUS_OVERDUE = 'overdue';

    const STATUSES = [
        self::STATUS_SCHEDULED => 'Programado',
        self::STATUS_COMPLETED => 'Completado',
        self::STATUS_SKIPPED => 'Omitido',
        self::STATUS_OVERDUE => 'Atrasado',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Plan de mantenimiento
     */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /**
     * Work Order generada
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    // =====================================================
    // SCOPES
    // =====================================================

    /**
     * Scope: Ejecuciones programadas
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope: Ejecuciones completadas
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope: Ejecuciones atrasadas
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    /**
     * Scope: Ejecuciones en un rango de fechas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_date', [$startDate, $endDate]);
    }

    // =====================================================
    // MÉTODOS DE INSTANCIA
    // =====================================================

    /**
     * Marcar como completada cuando se completa la Work Order
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'executed_date' => now(),
        ]);

        // Actualizar lectura del medidor si aplica
        if ($this->maintenancePlan->plan_type !== MaintenancePlan::TYPE_TIME_BASED) {
            $meter = $this->maintenancePlan->asset->meters()
                ->where('meter_type', $this->maintenancePlan->meter_type)
                ->first();

            if ($meter) {
                $this->update([
                    'meter_reading_at_execution' => $meter->current_reading,
                ]);
            }
        }
    }

    /**
     * Marcar como atrasada
     */
    public function markAsOverdue(): void
    {
        $this->update([
            'status' => self::STATUS_OVERDUE,
        ]);
    }

    /**
     * Marcar como omitida
     */
    public function markAsSkipped(string $reason = null): void
    {
        $this->update([
            'status' => self::STATUS_SKIPPED,
            'notes' => $reason,
        ]);
    }

    // =====================================================
    // ACCESSORS
    // =====================================================

    /**
     * Obtener nombre del estado
     */
    public function getStatusNameAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Verificar si está completada
     */
    public function getIsCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Verificar si está atrasada
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === self::STATUS_OVERDUE ||
            ($this->status === self::STATUS_SCHEDULED && now()->greaterThan($this->scheduled_date));
    }

    /**
     * Días de retraso
     */
    public function getDaysLateAttribute(): ?int
    {
        if (!$this->is_overdue) {
            return null;
        }

        return now()->diffInDays($this->scheduled_date);
    }

    /**
     * Tiempo transcurrido entre programación y ejecución
     */
    public function getExecutionDelayAttribute(): ?string
    {
        if (!$this->executed_date) {
            return null;
        }

        $diff = $this->scheduled_date->diff($this->executed_date);
        
        if ($diff->days > 0) {
            return $diff->days . ' días';
        }
        
        if ($diff->h > 0) {
            return $diff->h . ' horas';
        }

        return 'A tiempo';
    }
}
