<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Project;

class WorkOrder extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_orders';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'company_id',
        'work_request_id',
        'maintenance_plan_id',
        'asset_id',
        'code',
        'title',
        'description',
        'work_order_type',
        'priority',
        'status',
        'scheduled_start',
        'scheduled_end',
        'estimated_duration_hours',
        'actual_start',
        'actual_end',
        'actual_duration_hours',
        'assigned_to',
        'assigned_by',
        'assigned_at',
        'estimated_labor_cost',
        'estimated_material_cost',
        'estimated_other_cost',
        'actual_labor_cost',
        'actual_material_cost',
        'actual_other_cost',
        'completion_notes',
        'signature_data',
        'signature_name',
        'signature_date',
        'completed_by',
        'completed_at',
        'validated_by',
        'validated_at',
        'validation_notes',
        'approval_notes_request',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'failure_type',
        'downtime_hours',
        'is_emergency',
        'requires_shutdown',
        'sla_deadline',
        'sla_breached',
        'sla_breach_reason',
        'created_by',
        'updated_by',
        'deleted_by',
        'project_id',
    ];

    /**
     * Campos ocultos en serialización JSON
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'validated_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'signature_date' => 'datetime',
        'sla_deadline' => 'datetime',
        'estimated_duration_hours' => 'decimal:2',
        'actual_duration_hours' => 'decimal:2',
        'estimated_labor_cost' => 'decimal:2',
        'estimated_material_cost' => 'decimal:2',
        'estimated_other_cost' => 'decimal:2',
        'actual_labor_cost' => 'decimal:2',
        'actual_material_cost' => 'decimal:2',
        'actual_other_cost' => 'decimal:2',
        'downtime_hours' => 'decimal:2',
        'is_emergency' => 'boolean',
        'requires_shutdown' => 'boolean',
        'sla_breached' => 'boolean',
    ];

    // ===================================
    // MUTATORS (Setters)
    // ===================================

    /**
     * Asegurar que los costos estimados nunca sean null
     */
    public function setEstimatedLaborCostAttribute($value)
    {
        $this->attributes['estimated_labor_cost'] = $value ?? 0;
    }

    public function setEstimatedMaterialCostAttribute($value)
    {
        $this->attributes['estimated_material_cost'] = $value ?? 0;
    }

    public function setEstimatedOtherCostAttribute($value)
    {
        $this->attributes['estimated_other_cost'] = $value ?? 0;
    }

    public function setEstimatedDurationHoursAttribute($value)
    {
        $this->attributes['estimated_duration_hours'] = $value ?? 0;
    }

    // ===================================
    // CONSTANTS
    // ===================================

    public const STATUS_PENDING = 'pending';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_CANCELLED = 'cancelled';

    public const WORK_ORDER_TYPE_CORRECTIVE = 'corrective';
    public const WORK_ORDER_TYPE_PREVENTIVE = 'preventive';
    public const WORK_ORDER_TYPE_PREDICTIVE = 'predictive';
    public const WORK_ORDER_TYPE_INSPECTION = 'inspection';
    public const WORK_ORDER_TYPE_EMERGENCY = 'emergency';
    public const WORK_ORDER_TYPE_PROJECT = 'project';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkOrderAssignment::class)->orderBy('assigned_at', 'desc');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class)->orderBy('created_at', 'asc');
    }

    public function timeLogs(): HasMany
    {
        return $this->hasMany(WorkOrderTimeLog::class)->orderBy('start_time', 'desc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkOrderAttachment::class)->orderBy('uploaded_at', 'desc');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistItem::class)->orderBy('display_order');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkOrderComment::class)
            ->whereNull('parent_id') // Solo comentarios principales
            ->orderBy('created_at', 'asc');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(WorkOrderStatusHistory::class)->orderBy('changed_at', 'desc');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('work_order_type', $type);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', self::STATUS_SCHEDULED);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeValidated($query)
    {
        return $query->where('status', self::STATUS_VALIDATED);
    }

    public function scopeOverdue($query)
    {
        return $query->where('scheduled_end', '<', now())
                     ->whereNotIn('status', [self::STATUS_COMPLETED, self::STATUS_VALIDATED, self::STATUS_CANCELLED]);
    }

    public function scopeSlaBreached($query)
    {
        return $query->where('sla_breached', true);
    }

    public function scopeForAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeEmergency($query)
    {
        return $query->where('is_emergency', true);
    }

    public function scopeScheduledBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('scheduled_start', [$startDate, $endDate]);
    }

    // ===================================
    // ACCESSORS
    // ===================================

    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_VALIDATED, self::STATUS_CANCELLED])) {
            return false;
        }

        return $this->scheduled_end && $this->scheduled_end->isPast();
    }

    public function getCanBeEditedAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SCHEDULED]);
    }

    public function getCanBeStartedAttribute(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_SCHEDULED]);
    }

    public function getCanBeCompletedAttribute(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function getCanBeValidatedAttribute(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_VALIDATED, self::STATUS_CANCELLED]);
    }

    public function getTotalEstimatedCostAttribute(): float
    {
        return ($this->estimated_labor_cost ?? 0) + 
               ($this->estimated_material_cost ?? 0) + 
               ($this->estimated_other_cost ?? 0);
    }

    public function getTotalActualCostAttribute(): float
    {
        return ($this->actual_labor_cost ?? 0) + 
               ($this->actual_material_cost ?? 0) + 
               ($this->actual_other_cost ?? 0);
    }

    public function getCostVarianceAttribute(): float
    {
        return $this->total_actual_cost - $this->total_estimated_cost;
    }

    public function getDurationVarianceAttribute(): ?float
    {
        if (!$this->estimated_duration_hours || !$this->actual_duration_hours) {
            return null;
        }

        return $this->actual_duration_hours - $this->estimated_duration_hours;
    }

    public function getCompletionPercentageAttribute(): float
    {
        $total = $this->checklistItems()->count();
        
        if ($total === 0) {
            return 0;
        }

        $completed = $this->checklistItems()->where('is_checked', true)->count();
        
        return round(($completed / $total) * 100, 2);
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Generate a unique code for the work order.
     * Uses Redis lock to prevent race conditions.
     * MUST be called within a DB transaction.
     */
    public static function generateCode(int $companyId): string
    {
        $prefix = 'OT';
        $year = date('y');

        // Usar lock de Redis/Cache para prevenir race conditions
        $lockKey = "work_order_code_generation_{$companyId}_{$year}";
        
        // Intentar obtener el lock (espera hasta 10 segundos, lock válido por 5 segundos)
        $lock = \Cache::lock($lockKey, 5);
        
        try {
            // Bloquear hasta 10 segundos esperando el lock
            $lock->block(10);
            
            \Log::debug("Lock adquirido para generación de código OT", [
                'company_id' => $companyId,
                'lock_key' => $lockKey,
            ]);

            // Ahora que tenemos el lock, buscar el último código (INCLUIR SOFT DELETED)
            $lastOrder = static::withTrashed()
                ->where('company_id', $companyId)
                ->where('code', 'like', "{$prefix}-{$year}-%")
                ->orderBy('code', 'desc')
                ->first();

            if ($lastOrder) {
                // Extract the sequential number and increment by 1
                $lastNumber = (int) substr($lastOrder->code, -4);
                $startNumber = $lastNumber + 1;
            } else {
                $startNumber = 1;
            }

            // Buscar el siguiente código disponible (por si hay gaps)
            $maxAttempts = 100;
            $currentNumber = $startNumber;
            
            for ($i = 0; $i < $maxAttempts; $i++) {
                $code = sprintf('%s-%s-%04d', $prefix, $year, $currentNumber);
                
                // Verificar si el código ya existe (INCLUIR SOFT DELETED)
                $exists = static::withTrashed()
                    ->where('company_id', $companyId)
                    ->where('code', $code)
                    ->exists();
                
                if (!$exists) {
                    \Log::info("Código OT generado exitosamente con lock", [
                        'company_id' => $companyId,
                        'code' => $code,
                        'attempt' => $i + 1,
                        'started_from' => $startNumber,
                    ]);
                    
                    // Liberar lock antes de retornar
                    $lock->release();
                    return $code;
                }
                
                \Log::debug("Código {$code} ya existe, probando siguiente", [
                    'company_id' => $companyId,
                    'attempt' => $i + 1,
                ]);
                $currentNumber++;
            }

            // Si llegamos aquí, algo está muy mal
            $lock->release();
            throw new \RuntimeException("No se pudo generar un código único después de {$maxAttempts} intentos");
            
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            \Log::error("Timeout esperando lock para generación de código OT", [
                'company_id' => $companyId,
                'lock_key' => $lockKey,
            ]);
            throw new \RuntimeException("No se pudo obtener lock para generar código. Intente nuevamente.");
        }
    }

    /**
     * Calculate actual costs from time logs and materials
     */
    public function calculateActualCosts(): void
    {
        // Calculate labor cost from time logs
        $laborCost = $this->timeLogs()
            ->sum('total_cost');
        
        // Calculate material cost
        $materialCost = $this->materials()
            ->sum('total_cost');

        $this->actual_labor_cost = $laborCost;
        $this->actual_material_cost = $materialCost;
        
        // actual_other_cost debe ser establecido manualmente si es necesario
    }

    /**
     * Calculate actual duration from actual start and end times
     */
    public function calculateActualDuration(): void
    {
        if ($this->actual_start && $this->actual_end) {
            $this->actual_duration_hours = $this->actual_start->diffInHours($this->actual_end, true);
        }
    }

    /**
     * Check if a status transition is valid
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            self::STATUS_PENDING => [self::STATUS_SCHEDULED, self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
            self::STATUS_SCHEDULED => [self::STATUS_IN_PROGRESS, self::STATUS_ON_HOLD, self::STATUS_CANCELLED],
            self::STATUS_IN_PROGRESS => [self::STATUS_ON_HOLD, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_ON_HOLD => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [self::STATUS_VALIDATED, self::STATUS_IN_PROGRESS], // Puede volver a in_progress si necesita correcciones
            self::STATUS_VALIDATED => [], // Estado final
            self::STATUS_CANCELLED => [], // Estado final
        ];

        return in_array($newStatus, $transitions[$this->status] ?? []);
    }

    /**
     * Calculate SLA deadline based on priority and work order type
     */
    public function calculateSlaDeadline(): void
    {
        if ($this->is_emergency) {
            $this->sla_deadline = now()->addHours(2);
            return;
        }

        $slaHours = match($this->priority) {
            self::PRIORITY_CRITICAL => 8,
            self::PRIORITY_HIGH => 24,
            self::PRIORITY_MEDIUM => 72,
            self::PRIORITY_LOW => 168,
            default => 168,
        };

        // Ajustar según el tipo de orden
        if ($this->work_order_type === self::WORK_ORDER_TYPE_PREVENTIVE) {
            $slaHours *= 1.5; // 50% más de tiempo para preventivos
        }

        $this->sla_deadline = $this->scheduled_start 
            ? $this->scheduled_start->copy()->addHours($slaHours)
            : now()->addHours($slaHours);
    }

    /**
     * Check if SLA has been breached
     */
    public function checkSlaStatus(): void
    {
        $now = now();

        if ($this->sla_deadline && $now->greaterThan($this->sla_deadline) && 
            !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_VALIDATED])) {
            $this->sla_breached = true;
            $this->sla_breach_reason = 'SLA deadline exceeded - Work order not completed within required timeframe';
        }
    }

    /**
     * Get translated status label in Spanish
     */
    public static function getStatusLabel(string $status): string
    {
        return match($status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_SCHEDULED => 'Programada',
            self::STATUS_IN_PROGRESS => 'En Progreso',
            self::STATUS_ON_HOLD => 'En Espera',
            self::STATUS_COMPLETED => 'Completada',
            self::STATUS_VALIDATED => 'Validada',
            self::STATUS_CANCELLED => 'Cancelada',
            default => $status,
        };
    }

    /**
     * Record status change in history
     */
    public function recordStatusChange(?string $fromStatus, string $toStatus, int $changedBy, ?string $reason = null, ?array $metadata = null): void
    {
        WorkOrderStatusHistory::create([
            'work_order_id' => $this->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $changedBy,
            'changed_at' => now(),
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
