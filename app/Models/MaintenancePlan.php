<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class MaintenancePlan extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'maintenance_plans';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'company_id',
        'code',
        'asset_id',
        'asset_category_id',
        'site_id',
        'name',
        'description',
        'plan_type',
        'frequency_type',
        'frequency_value',
        'meter_type',
        'meter_threshold',
        'trigger_mode',
        'priority',
        'estimated_duration_minutes',
        'estimated_cost',
        'default_assigned_to',
        'is_active',
        'last_execution_date',
        'last_meter_reading',
        'next_execution_date',
        'next_meter_threshold',
        'created_by',
        'updated_by',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'frequency_value' => 'integer',
        'meter_threshold' => 'decimal:2',
        'estimated_duration_minutes' => 'integer',
        'estimated_cost' => 'decimal:2',
        'is_active' => 'boolean',
        'last_execution_date' => 'datetime',
        'last_meter_reading' => 'decimal:2',
        'next_execution_date' => 'datetime',
        'next_meter_threshold' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // =====================================================
    // CONSTANTES: Tipos de Plan
    // =====================================================

    const TYPE_TIME_BASED = 'time_based';
    const TYPE_METER_BASED = 'meter_based';
    const TYPE_HYBRID = 'hybrid';

    const TYPES = [
        self::TYPE_TIME_BASED => 'Basado en Tiempo',
        self::TYPE_METER_BASED => 'Basado en Medición',
        self::TYPE_HYBRID => 'Híbrido',
    ];

    // =====================================================
    // CONSTANTES: Frecuencias Temporales
    // =====================================================

    const FREQ_DAILY = 'daily';
    const FREQ_WEEKLY = 'weekly';
    const FREQ_MONTHLY = 'monthly';
    const FREQ_QUARTERLY = 'quarterly';
    const FREQ_SEMIANNUAL = 'semiannual';
    const FREQ_ANNUAL = 'annual';

    const FREQUENCIES = [
        self::FREQ_DAILY => 'Diario',
        self::FREQ_WEEKLY => 'Semanal',
        self::FREQ_MONTHLY => 'Mensual',
        self::FREQ_QUARTERLY => 'Trimestral',
        self::FREQ_SEMIANNUAL => 'Semestral',
        self::FREQ_ANNUAL => 'Anual',
    ];

    // =====================================================
    // CONSTANTES: Tipos de Medidor
    // =====================================================

    const METER_HOURS = 'hours';
    const METER_KILOMETERS = 'kilometers';
    const METER_CYCLES = 'cycles';
    const METER_UNITS = 'units_produced';

    // =====================================================
    // CONSTANTES: Modos de Disparo (Híbrido)
    // =====================================================

    const TRIGGER_FIRST = 'first';  // El que ocurra primero
    const TRIGGER_BOTH = 'both';    // Ambos deben cumplirse

    const TRIGGER_MODES = [
        self::TRIGGER_FIRST => 'El que ocurra primero',
        self::TRIGGER_BOTH => 'Ambos deben cumplirse',
    ];

    // =====================================================
    // CONSTANTES: Prioridades
    // =====================================================

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    const PRIORITIES = [
        self::PRIORITY_LOW => 'Baja',
        self::PRIORITY_MEDIUM => 'Media',
        self::PRIORITY_HIGH => 'Alta',
        self::PRIORITY_URGENT => 'Urgente',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Empresa a la que pertenece el plan
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Activo al que aplica el plan
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Categoría del activo
     */
    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /**
     * Sitio del activo
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(CompanySite::class, 'site_id');
    }

    /**
     * Técnico asignado por defecto
     */
    public function defaultAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_assigned_to');
    }

    /**
     * Usuario que creó el plan
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario que modificó por última vez
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Checklist predefinido del plan
     */
    public function checklistTemplates(): HasMany
    {
        return $this->hasMany(MaintenancePlanChecklistTemplate::class)->orderBy('item_order');
    }

    /**
     * Materiales predefinidos del plan
     */
    public function materialTemplates(): HasMany
    {
        return $this->hasMany(MaintenancePlanMaterialTemplate::class);
    }

    /**
     * Historial de ejecuciones del plan
     */
    public function executions(): HasMany
    {
        return $this->hasMany(MaintenancePlanExecution::class)->latest('scheduled_date');
    }

    /**
     * Work Orders generadas desde este plan
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    // =====================================================
    // SCOPES
    // =====================================================

    /**
     * Scope: Solo planes activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Planes de un tipo específico
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('plan_type', $type);
    }

    /**
     * Scope: Planes que vencen hoy
     */
    public function scopeDueToday($query)
    {
        return $query->active()
            ->where(function ($q) {
                // Planes basados en tiempo que vencen hoy
                $q->where('plan_type', self::TYPE_TIME_BASED)
                    ->whereDate('next_execution_date', '<=', now());
            })
            ->orWhere(function ($q) {
                // Planes híbridos con fecha vencida
                $q->where('plan_type', self::TYPE_HYBRID)
                    ->whereDate('next_execution_date', '<=', now());
            });
    }

    /**
     * Scope: Planes atrasados
     */
    public function scopeOverdue($query)
    {
        return $query->active()
            ->where('next_execution_date', '<', now()->subDay());
    }

    /**
     * Scope: Planes próximos a vencer (próximos X días)
     */
    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->active()
            ->whereBetween('next_execution_date', [now(), now()->addDays($days)]);
    }

    /**
     * Scope: Planes de una empresa específica
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope: Planes de un sitio específico
     */
    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    // =====================================================
    // MÉTODOS PRINCIPALES: Lógica de Negocio
    // =====================================================

    /**
     * Verificar si el plan debe ejecutarse (está vencido)
     * 
     * @return bool
     */
    public function isDue(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->plan_type === self::TYPE_TIME_BASED) {
            return $this->isTimeDue();
        }

        if ($this->plan_type === self::TYPE_METER_BASED) {
            return $this->isMeterDue();
        }

        if ($this->plan_type === self::TYPE_HYBRID) {
            return $this->isHybridDue();
        }

        return false;
    }

    /**
     * Verificar si está vencido por tiempo
     */
    protected function isTimeDue(): bool
    {
        return $this->next_execution_date && now()->greaterThanOrEqualTo($this->next_execution_date);
    }

    /**
     * Verificar si está vencido por medición
     */
    protected function isMeterDue(): bool
    {
        $assetMeter = $this->asset->meters()
            ->where('meter_type', $this->meter_type)
            ->where('is_active', true)
            ->first();

        if (!$assetMeter) {
            return false;
        }

        return $assetMeter->current_reading >= $this->next_meter_threshold;
    }

    /**
     * Verificar si está vencido (modo híbrido)
     */
    protected function isHybridDue(): bool
    {
        $timeDue = $this->isTimeDue();
        $meterDue = $this->isMeterDue();

        if ($this->trigger_mode === self::TRIGGER_FIRST) {
            // El que ocurra primero
            return $timeDue || $meterDue;
        }

        if ($this->trigger_mode === self::TRIGGER_BOTH) {
            // Ambos deben cumplirse
            return $timeDue && $meterDue;
        }

        return false;
    }

    /**
     * Generar automáticamente una Work Order desde este plan
     * 
     * @return WorkOrder
     */
    public function generateWorkOrder(): WorkOrder
    {
        // 1. Crear la Work Order
        $workOrder = WorkOrder::create([
            'company_id' => $this->company_id,
            'maintenance_plan_id' => $this->id,
            'asset_id' => $this->asset_id,
            'code' => $this->generateWorkOrderCode(),
            'title' => "🔧 [PREVENTIVO] " . $this->name,
            'description' => $this->description,
            'work_order_type' => 'preventive',
            'priority' => $this->priority,
            'status' => 'scheduled',
            'scheduled_start' => now(),
            'estimated_duration_hours' => $this->estimated_duration_minutes ? ($this->estimated_duration_minutes / 60) : null,
            'assigned_to' => $this->default_assigned_to,
            'assigned_by' => 1, // Sistema
            'assigned_at' => now(),
        ]);

        // 2. Copiar checklist desde templates
        foreach ($this->checklistTemplates as $template) {
            $workOrder->checklist()->create([
                'item_order' => $template->item_order,
                'item_text' => $template->item_text,
                'requires_photo' => $template->requires_photo,
                'is_mandatory' => $template->is_mandatory,
                'is_completed' => false,
            ]);
        }

        // 3. Asignar materiales en estado 'planned'
        foreach ($this->materialTemplates as $template) {
            $workOrder->materials()->create([
                'material_id' => $template->material_id,
                'estimated_quantity' => $template->estimated_quantity,
                'material_status' => 'planned',
                'notes' => $template->notes,
            ]);
        }

        return $workOrder;
    }

    /**
     * Generar código único para la Work Order
     */
    protected function generateWorkOrderCode(): string
    {
        $prefix = 'OT-PM-' . now()->format('Ym');
        $lastOrder = WorkOrder::where('code', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        if (!$lastOrder) {
            return $prefix . '-00001';
        }

        $lastNumber = intval(substr($lastOrder->code, -5));
        return $prefix . '-' . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Actualizar próxima ejecución después de generar una Work Order
     */
    public function updateNextExecution(): void
    {
        if ($this->plan_type === self::TYPE_TIME_BASED || $this->plan_type === self::TYPE_HYBRID) {
            $this->next_execution_date = $this->calculateNextDateExecution();
        }

        if ($this->plan_type === self::TYPE_METER_BASED || $this->plan_type === self::TYPE_HYBRID) {
            $this->next_meter_threshold = $this->calculateNextMeterThreshold();
        }

        $this->last_execution_date = now();

        if ($this->plan_type === self::TYPE_METER_BASED || $this->plan_type === self::TYPE_HYBRID) {
            $assetMeter = $this->asset->meters()->where('meter_type', $this->meter_type)->first();
            $this->last_meter_reading = $assetMeter ? $assetMeter->current_reading : 0;
        }

        $this->save();
    }

    /**
     * Calcular próxima fecha de ejecución (planes time_based)
     */
    protected function calculateNextDateExecution(): Carbon
    {
        $base = $this->last_execution_date ?? now();

        return match ($this->frequency_type) {
            self::FREQ_DAILY => $base->copy()->addDays($this->frequency_value),
            self::FREQ_WEEKLY => $base->copy()->addWeeks($this->frequency_value),
            self::FREQ_MONTHLY => $base->copy()->addMonths($this->frequency_value),
            self::FREQ_QUARTERLY => $base->copy()->addMonths($this->frequency_value * 3),
            self::FREQ_SEMIANNUAL => $base->copy()->addMonths($this->frequency_value * 6),
            self::FREQ_ANNUAL => $base->copy()->addYears($this->frequency_value),
            default => $base->copy()->addMonth(),
        };
    }

    /**
     * Calcular próximo umbral de medición (planes meter_based)
     */
    protected function calculateNextMeterThreshold(): float
    {
        return $this->last_meter_reading + $this->meter_threshold;
    }

    /**
     * Generar código único para el plan
     */
    public static function generateCode(int $companyId): string
    {
        $prefix = 'MP-' . now()->format('Ym');
        
        $lastPlan = self::where('company_id', $companyId)
            ->where('code', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        if (!$lastPlan) {
            return $prefix . '-00001';
        }

        $lastNumber = intval(substr($lastPlan->code, -5));
        return $prefix . '-' . str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
    }

    // =====================================================
    // ACCESSORS
    // =====================================================

    /**
     * Obtener nombre del tipo de plan
     */
    public function getPlanTypeNameAttribute(): string
    {
        return self::TYPES[$this->plan_type] ?? $this->plan_type;
    }

    /**
     * Obtener nombre de la frecuencia
     */
    public function getFrequencyNameAttribute(): ?string
    {
        return $this->frequency_type ? self::FREQUENCIES[$this->frequency_type] : null;
    }

    /**
     * Obtener nombre del modo de disparo
     */
    public function getTriggerModeNameAttribute(): ?string
    {
        return $this->trigger_mode ? self::TRIGGER_MODES[$this->trigger_mode] : null;
    }

    /**
     * Obtener nombre de la prioridad
     */
    public function getPriorityNameAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    /**
     * Verificar si está atrasado
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->is_active || !$this->next_execution_date) {
            return false;
        }

        return now()->greaterThan($this->next_execution_date->addDay());
    }

    /**
     * Días hasta próxima ejecución
     */
    public function getDaysUntilNextExecutionAttribute(): ?int
    {
        if (!$this->next_execution_date) {
            return null;
        }

        return now()->diffInDays($this->next_execution_date, false);
    }

    /**
     * Descripción completa de la frecuencia
     */
    public function getFrequencyDescriptionAttribute(): ?string
    {
        if ($this->plan_type === self::TYPE_TIME_BASED) {
            return "Cada {$this->frequency_value} " . strtolower($this->frequency_name);
        }

        if ($this->plan_type === self::TYPE_METER_BASED) {
            $meterName = AssetMeter::TYPES[$this->meter_type] ?? $this->meter_type;
            return "Cada {$this->meter_threshold} " . strtolower($meterName);
        }

        if ($this->plan_type === self::TYPE_HYBRID) {
            $time = "Cada {$this->frequency_value} " . strtolower($this->frequency_name);
            $meter = "cada {$this->meter_threshold} " . strtolower(AssetMeter::TYPES[$this->meter_type] ?? $this->meter_type);
            $mode = $this->trigger_mode === self::TRIGGER_FIRST ? 'o' : 'y';
            return "{$time} {$mode} {$meter}";
        }

        return null;
    }
}
