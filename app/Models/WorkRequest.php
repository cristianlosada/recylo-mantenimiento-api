<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkRequest extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_requests';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'company_id',
        'asset_id',
        'code',
        'title',
        'description',
        'request_type',
        'priority',
        'status',
        'requester_id',
        'requester_name',
        'requester_email',
        'requester_phone',
        'is_public_request',
        'location_details',
        'estimated_cost',
        'estimated_hours',
        'actual_cost',
        'actual_hours',
        'response_due_at',
        'resolution_due_at',
        'first_response_at',
        'sla_breached',
        'sla_breach_reason',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'work_order_id',
        'equipment_status',
        'created_by',
        'updated_by',
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
        'estimated_cost' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'first_response_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'sla_breached' => 'boolean',
        'is_public_request' => 'boolean',
    ];

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkRequestAttachment::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(WorkRequestComment::class);
    }

    public function watchers(): HasMany
    {
        return $this->hasMany(WorkRequestWatcher::class);
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(WorkRequestChecklistItem::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkRequestTag::class,
            'work_request_tag_assignments',
            'work_request_id',
            'tag_id'
        )->withPivot('assigned_by', 'assigned_at')
          ->withTimestamps();
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(WorkRequestStatusHistory::class)->orderBy('changed_at', 'desc');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WorkRequestNotification::class);
    }

    public function relatedRequests(): HasMany
    {
        return $this->hasMany(WorkRequestRelated::class, 'work_request_id');
    }

    public function relatedToRequests(): HasMany
    {
        return $this->hasMany(WorkRequestRelated::class, 'related_work_request_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
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
        return $query->where('request_type', $type);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('status', 'under_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeSlaBreached($query)
    {
        return $query->where('sla_breached', true);
    }

    public function scopeOverdue($query)
    {
        return $query->where('resolution_due_at', '<', now())
                     ->whereIn('status', ['pending', 'under_review']);
    }

    public function scopeForAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeRequestedBy($query, $userId)
    {
        return $query->where('requester_id', $userId);
    }

    // ===================================
    // ACCESSORS
    // ===================================

    public function getIsOverdueAttribute(): bool
    {
        if (in_array($this->status, ['completed', 'cancelled', 'rejected'])) {
            return false;
        }

        return $this->resolution_due_at && $this->resolution_due_at->isPast();
    }

    public function getIsPendingResponseAttribute(): bool
    {
        return $this->first_response_at === null && $this->status === 'pending';
    }

    public function getCanBeEditedAttribute(): bool
    {
        // Permitir edición en pending siempre
        if ($this->status === 'pending') return true;

        // Permitir edición en approved SOLO si la OT vinculada no está en estado terminal
        if ($this->status === 'approved' && $this->work_order_id) {
            $wo = $this->workOrder;
            if (!$wo) return true; // sin OT cargada, permitir
            return !in_array($wo->status, ['completed', 'validated', 'cancelled']);
        }

        return false;
    }

    /**
     * Indica si editar la solicitud propagará cambios a la OT vinculada.
     * Retorna el status de la OT para que el frontend muestre la advertencia correcta.
     */
    public function getEditWarningAttribute(): ?string
    {
        if ($this->status !== 'approved' || !$this->work_order_id) return null;
        $wo = $this->workOrder;
        if (!$wo) return null;
        if (in_array($wo->status, ['pending', 'scheduled'])) return 'will_update_wo';
        if ($wo->status === 'in_progress') return 'wo_in_progress';
        return null;
    }

    public function getCanBeApprovedAttribute(): bool
    {
        return in_array($this->status, ['pending', 'under_review']);
    }

    public function getCanBeRejectedAttribute(): bool
    {
        return in_array($this->status, ['pending', 'under_review']);
    }

    public function getCostVarianceAttribute(): ?float
    {
        if (!$this->estimated_cost || !$this->actual_cost) {
            return null;
        }

        return $this->actual_cost - $this->estimated_cost;
    }

    public function getHoursVarianceAttribute(): ?float
    {
        if (!$this->estimated_hours || !$this->actual_hours) {
            return null;
        }

        return $this->actual_hours - $this->estimated_hours;
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Generate a unique code for the work request.
     * Uses Redis lock to prevent race conditions.
     * MUST be called within a DB transaction.
     */
    public static function generateCode(int $companyId): string
    {
        $prefix = 'SOL';
        $year = date('y');

        // Usar lock de Redis/Cache para prevenir race conditions
        $lockKey = "work_request_code_generation_{$companyId}_{$year}";
        
        // Intentar obtener el lock (espera hasta 10 segundos, lock válido por 5 segundos)
        $lock = \Cache::lock($lockKey, 5);
        
        try {
            // Bloquear hasta 10 segundos esperando el lock
            $lock->block(10);
            
            \Log::debug("Lock adquirido para generación de código", [
                'company_id' => $companyId,
                'lock_key' => $lockKey,
            ]);

            // Ahora que tenemos el lock, buscar el último código (INCLUIR SOFT DELETED)
            $lastRequest = static::withTrashed()
                ->where('company_id', $companyId)
                ->where('code', 'like', "{$prefix}-{$year}-%")
                ->orderBy('code', 'desc')
                ->first();

            if ($lastRequest) {
                // Extract the sequential number and increment by 1
                $lastNumber = (int) substr($lastRequest->code, -4);
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
                    \Log::info("Código generado exitosamente con lock", [
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
            \Log::error("Timeout esperando lock para generación de código", [
                'company_id' => $companyId,
                'lock_key' => $lockKey,
            ]);
            throw new \RuntimeException("No se pudo obtener lock para generar código. Intente nuevamente.");
        }
    }

    /**
     * Calculate SLA due dates based on priority.
     */
    public function calculateSlaDeadlines(): void
    {
        $now = now();

        // Define SLA hours based on priority
        $slaHours = match($this->priority) {
            'critical' => ['response' => 1, 'resolution' => 4],
            'high' => ['response' => 4, 'resolution' => 24],
            'medium' => ['response' => 8, 'resolution' => 72],
            'low' => ['response' => 24, 'resolution' => 168],
            default => ['response' => 24, 'resolution' => 168],
        };

        $this->response_due_at = $now->copy()->addHours($slaHours['response']);
        $this->resolution_due_at = $now->copy()->addHours($slaHours['resolution']);
    }

    /**
     * Check if SLA has been breached.
     */
    public function checkSlaStatus(): void
    {
        $now = now();

        // Estados en que la solicitud ya fue resuelta (aprobada, rechazada, completada o cancelada)
        // Una vez aprobada, el proceso de la solicitud terminó — el trabajo lo sigue la OT
        $terminalStatuses  = ['approved', 'completed', 'cancelled', 'rejected'];
        // Estados en que ya hubo una "respuesta" formal
        $respondedStatuses = ['approved', 'rejected', 'completed', 'cancelled'];

        // ── SLA de respuesta ──────────────────────────────────────────────
        // Breach si: deadline pasó + nunca hubo respuesta + aún en estado sin respuesta
        $alreadyResponded = $this->first_response_at || in_array($this->status, $respondedStatuses);
        $responseBreach = !$alreadyResponded
            && $this->response_due_at
            && $now->greaterThan($this->response_due_at);

        // ── SLA de resolución ─────────────────────────────────────────────
        $resolutionBreach = false;
        if ($this->resolution_due_at
            && $now->greaterThan($this->resolution_due_at)
            && !in_array($this->status, $terminalStatuses)
        ) {
            // Verificar si la OT vinculada resolvió a tiempo
            $resolvedViaWorkOrder = false;
            if ($this->work_order_id) {
                $wo = $this->workOrder;
                if ($wo && in_array($wo->status, ['completed', 'validated'])) {
                    $resolvedAt = $wo->validated_at ?? $wo->completed_at;
                    if ($resolvedAt && $resolvedAt->lessThanOrEqualTo($this->resolution_due_at)) {
                        $resolvedViaWorkOrder = true;
                    }
                }
            }
            $resolutionBreach = !$resolvedViaWorkOrder;
        }

        // ── Resultado final ───────────────────────────────────────────────
        $breached = $responseBreach || $resolutionBreach;
        $this->sla_breached = $breached;
        if ($breached) {
            $this->sla_breach_reason = $resolutionBreach
                ? 'Se ha incumplido el acuerdo de nivel de servicio (SLA) de resolución: no se ha resuelto dentro del plazo requerido.'
                : 'Se ha incumplido el SLA de respuesta: no se ha recibido respuesta dentro del plazo requerido.';
        } else {
            $this->sla_breach_reason = null;
        }
    }

    /**
     * Record first response time.
     */
    public function recordFirstResponse(): void
    {
        if (!$this->first_response_at) {
            $this->first_response_at = now();
            $this->save();
        }
    }

    /**
     * Apply checklist template based on asset category, request type, and priority.
     */
    public function applyChecklistTemplate(): void
    {
        if (!$this->asset) {
            return;
        }

        $template = WorkRequestChecklistTemplate::where('company_id', $this->company_id)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('asset_category_id')
                      ->orWhere('asset_category_id', $this->asset->category_id);
            })
            ->where(function ($query) {
                $query->whereNull('request_type')
                      ->orWhere('request_type', $this->request_type);
            })
            ->where(function ($query) {
                $query->whereNull('priority')
                      ->orWhere('priority', $this->priority);
            })
            ->orderBy('display_order')
            ->first();

        if ($template && !empty($template->checklist_items)) {
            foreach ($template->checklist_items as $item) {
                WorkRequestChecklistItem::create([
                    'work_request_id' => $this->id,
                    'template_id' => $template->id,
                    'item_text' => $item['text'] ?? $item['item_text'] ?? '',
                    'is_required' => $item['is_required'] ?? false,
                    'is_checked' => false,
                    'display_order' => $item['order'] ?? $item['display_order'] ?? 0,
                ]);
            }
        }
    }
}
