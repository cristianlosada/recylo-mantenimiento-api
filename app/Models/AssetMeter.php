<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetMeter extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'asset_meters';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'asset_id',
        'meter_type',
        'current_reading',
        'unit',
        'last_reading_date',
        'last_reading_by',
        'is_active',
        'notes',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'current_reading' => 'decimal:2',
        'last_reading_date' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // =====================================================
    // CONSTANTES: Tipos de Medidores
    // =====================================================

    const TYPE_HOURS = 'hours';
    const TYPE_KILOMETERS = 'kilometers';
    const TYPE_CYCLES = 'cycles';
    const TYPE_UNITS_PRODUCED = 'units_produced';

    const TYPES = [
        self::TYPE_HOURS => 'Horas',
        self::TYPE_KILOMETERS => 'Kilómetros',
        self::TYPE_CYCLES => 'Ciclos',
        self::TYPE_UNITS_PRODUCED => 'Unidades Producidas',
    ];

    const UNITS = [
        self::TYPE_HOURS => 'h',
        self::TYPE_KILOMETERS => 'km',
        self::TYPE_CYCLES => 'ciclos',
        self::TYPE_UNITS_PRODUCED => 'unidades',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Activo al que pertenece el medidor
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Usuario que registró la última lectura
     */
    public function lastReadingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_reading_by');
    }

    /**
     * Historial de lecturas de este medidor
     */
    public function readings(): HasMany
    {
        return $this->hasMany(AssetMeterReading::class);
    }

    /**
     * Planes de mantenimiento que usan este tipo de medidor
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class, 'meter_type', 'meter_type')
            ->where('asset_id', $this->asset_id);
    }

    // =====================================================
    // SCOPES
    // =====================================================

    /**
     * Scope: Solo medidores activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Medidores de un tipo específico
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('meter_type', $type);
    }

    /**
     * Scope: Medidores con lecturas recientes (últimos X días)
     */
    public function scopeWithRecentReadings($query, int $days = 90)
    {
        return $query->where('last_reading_date', '>=', now()->subDays($days));
    }

    // =====================================================
    // MÉTODOS DE INSTANCIA
    // =====================================================

    /**
     * Registrar una nueva lectura en el medidor
     * 
     * @param float $value Valor de la lectura
     * @param array $data Datos adicionales (fecha, origen, user_id, etc.)
     * @return AssetMeterReading
     */
    public function recordReading(float $value, array $data = []): AssetMeterReading
    {
        // Calcular diferencia con lectura anterior
        $previousValue = $this->current_reading;
        $difference = $value - $previousValue;

        // Crear el registro de lectura
        $reading = $this->readings()->create([
            'reading_value' => $value,
            'previous_value' => $previousValue,
            'difference' => $difference,
            'reading_date' => $data['reading_date'] ?? now(),
            'reading_source' => $data['reading_source'] ?? AssetMeterReading::SOURCE_MANUAL,
            'work_order_id' => $data['work_order_id'] ?? null,
            'maintenance_plan_id' => $data['maintenance_plan_id'] ?? null,
            'inspection_id' => $data['inspection_id'] ?? null,
            'recorded_by' => $data['recorded_by'] ?? auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]);

        // Actualizar la lectura actual del medidor
        $this->update([
            'current_reading' => $value,
            'last_reading_date' => $reading->reading_date,
            'last_reading_by' => $reading->recorded_by,
        ]);

        return $reading;
    }

    /**
     * Obtener la última lectura registrada
     */
    public function getLastReading(): ?AssetMeterReading
    {
        return $this->readings()->latest('reading_date')->first();
    }

    /**
     * Obtener lecturas en un rango de fechas
     */
    public function getReadingsBetween($startDate, $endDate)
    {
        return $this->readings()
            ->whereBetween('reading_date', [$startDate, $endDate])
            ->orderBy('reading_date', 'asc')
            ->get();
    }

    /**
     * Verificar si el medidor está próximo a algún umbral de mantenimiento
     * 
     * @return array|null ['plan_id', 'threshold', 'remaining']
     */
    public function getNextMaintenanceThreshold(): ?array
    {
        $plan = $this->maintenancePlans()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->where('plan_type', 'meter_based')
                    ->orWhere('plan_type', 'hybrid');
            })
            ->whereNotNull('next_meter_threshold')
            ->orderBy('next_meter_threshold', 'asc')
            ->first();

        if (!$plan) {
            return null;
        }

        return [
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'threshold' => $plan->next_meter_threshold,
            'remaining' => $plan->next_meter_threshold - $this->current_reading,
            'percentage' => ($this->current_reading / $plan->next_meter_threshold) * 100,
        ];
    }

    // =====================================================
    // ACCESSORS
    // =====================================================

    /**
     * Obtener el nombre del tipo de medidor
     */
    public function getTypeNameAttribute(): string
    {
        return self::TYPES[$this->meter_type] ?? $this->meter_type;
    }

    /**
     * Obtener la unidad del medidor
     */
    public function getUnitLabelAttribute(): string
    {
        return self::UNITS[$this->meter_type] ?? $this->unit;
    }

    /**
     * Obtener lectura formateada con unidad
     */
    public function getFormattedReadingAttribute(): string
    {
        return number_format($this->current_reading, 2) . ' ' . $this->unit_label;
    }

    /**
     * Verificar si tiene lecturas recientes (últimos 90 días)
     */
    public function getHasRecentReadingsAttribute(): bool
    {
        return $this->last_reading_date && 
               $this->last_reading_date->greaterThan(now()->subDays(90));
    }
}
