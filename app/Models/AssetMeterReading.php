<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMeterReading extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'asset_meter_readings';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'asset_meter_id',
        'reading_value',
        'previous_value',
        'difference',
        'reading_date',
        'reading_source',
        'work_order_id',
        'maintenance_plan_id',
        'inspection_id',
        'recorded_by',
        'notes',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'reading_value' => 'decimal:2',
        'previous_value' => 'decimal:2',
        'difference' => 'decimal:2',
        'reading_date' => 'datetime',
        'created_at' => 'datetime',
    ];

    // =====================================================
    // CONSTANTES: Orígenes de Lectura
    // =====================================================

    const SOURCE_MANUAL = 'manual';
    const SOURCE_WORK_ORDER = 'work_order';
    const SOURCE_MAINTENANCE_PLAN = 'maintenance_plan';
    const SOURCE_IMPORT = 'import';
    const SOURCE_INSPECTION = 'inspection';

    const SOURCES = [
        self::SOURCE_MANUAL => 'Manual',
        self::SOURCE_WORK_ORDER => 'Desde Work Order',
        self::SOURCE_MAINTENANCE_PLAN => 'Desde Plan de Mantenimiento',
        self::SOURCE_IMPORT => 'Importación',
        self::SOURCE_INSPECTION => 'Desde Inspección',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Medidor al que pertenece la lectura
     */
    public function assetMeter(): BelongsTo
    {
        return $this->belongsTo(AssetMeter::class);
    }

    /**
     * Work Order relacionada (opcional)
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * Plan de mantenimiento relacionado (opcional)
     */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /**
     * Inspección relacionada (opcional)
     */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    /**
     * Usuario que registró la lectura
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // =====================================================
    // SCOPES
    // =====================================================

    /**
     * Scope: Lecturas manuales
     */
    public function scopeManual($query)
    {
        return $query->where('reading_source', self::SOURCE_MANUAL);
    }

    /**
     * Scope: Lecturas desde Work Orders
     */
    public function scopeFromWorkOrders($query)
    {
        return $query->where('reading_source', self::SOURCE_WORK_ORDER);
    }

    /**
     * Scope: Lecturas en un rango de fechas
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('reading_date', [$startDate, $endDate]);
    }

    /**
     * Scope: Lecturas de los últimos X días
     */
    public function scopeLastDays($query, int $days = 30)
    {
        return $query->where('reading_date', '>=', now()->subDays($days));
    }

    /**
     * Scope: Lecturas con incremento positivo
     */
    public function scopeWithPositiveDifference($query)
    {
        return $query->where('difference', '>', 0);
    }

    // =====================================================
    // ACCESSORS
    // =====================================================

    /**
     * Obtener el nombre del origen de la lectura
     */
    public function getSourceNameAttribute(): string
    {
        return self::SOURCES[$this->reading_source] ?? $this->reading_source;
    }

    /**
     * Obtener lectura formateada con unidad
     */
    public function getFormattedReadingAttribute(): string
    {
        $unit = $this->assetMeter->unit ?? '';
        return number_format($this->reading_value, 2) . ' ' . $unit;
    }

    /**
     * Obtener diferencia formateada con unidad
     */
    public function getFormattedDifferenceAttribute(): string
    {
        $unit = $this->assetMeter->unit ?? '';
        $sign = $this->difference >= 0 ? '+' : '';
        return $sign . number_format($this->difference, 2) . ' ' . $unit;
    }

    /**
     * Verificar si es una lectura anormal (incremento muy alto o negativo)
     */
    public function getIsAbnormalAttribute(): bool
    {
        // Incremento negativo es anormal
        if ($this->difference < 0) {
            return true;
        }

        // Incremento mayor al 50% de la lectura anterior es sospechoso
        if ($this->previous_value > 0 && 
            $this->difference > ($this->previous_value * 0.5)) {
            return true;
        }

        return false;
    }

    // =====================================================
    // MÉTODOS ESTÁTICOS
    // =====================================================

    /**
     * Obtener estadísticas de lecturas en un periodo
     */
    public static function getStatistics(int $assetMeterId, $startDate, $endDate): array
    {
        $readings = self::where('asset_meter_id', $assetMeterId)
            ->whereBetween('reading_date', [$startDate, $endDate])
            ->orderBy('reading_date', 'asc')
            ->get();

        if ($readings->isEmpty()) {
            return [
                'total_readings' => 0,
                'total_increment' => 0,
                'average_increment' => 0,
                'max_increment' => 0,
                'min_increment' => 0,
            ];
        }

        return [
            'total_readings' => $readings->count(),
            'total_increment' => $readings->sum('difference'),
            'average_increment' => $readings->avg('difference'),
            'max_increment' => $readings->max('difference'),
            'min_increment' => $readings->min('difference'),
            'first_reading' => $readings->first()->reading_value,
            'last_reading' => $readings->last()->reading_value,
        ];
    }
}
