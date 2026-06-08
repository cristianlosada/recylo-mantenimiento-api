<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para mediciones de activos
 * 
 * @property int $id
 * @property int $asset_id
 * @property string $measurement_type
 * @property float $value
 * @property string $unit
 * @property float|null $min_threshold
 * @property float|null $max_threshold
 * @property string $status
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon $measured_at
 * @property int $measured_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * 
 * @property-read Asset $asset
 * @property-read User $measuredBy
 */
class AssetMeasurement extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'asset_measurements';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'asset_id',
        'measurement_type',
        'value',
        'unit',
        'min_threshold',
        'max_threshold',
        'status',
        'notes',
        'measured_at',
        'measured_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'value' => 'decimal:2',
        'min_threshold' => 'decimal:2',
        'max_threshold' => 'decimal:2',
        'measured_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Estados disponibles para la medición
     */
    const STATUS_NORMAL = 'normal';
    const STATUS_WARNING = 'warning';
    const STATUS_CRITICAL = 'critical';

    /**
     * Tipos de medición comunes
     */
    const TYPE_TEMPERATURE = 'temperature';
    const TYPE_PRESSURE = 'pressure';
    const TYPE_VIBRATION = 'vibration';
    const TYPE_HUMIDITY = 'humidity';
    const TYPE_VOLTAGE = 'voltage';
    const TYPE_CURRENT = 'current';
    const TYPE_RPM = 'rpm';
    const TYPE_FLOW = 'flow';
    const TYPE_LEVEL = 'level';
    const TYPE_OTHER = 'other';

    /**
     * Relación con el activo (muchos a uno)
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Relación con el usuario que realizó la medición (muchos a uno)
     */
    public function measuredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'measured_by');
    }

    /**
     * Calcular el estado basado en los umbrales
     * 
     * @return string
     */
    public function calculateStatus(): string
    {
        if ($this->min_threshold !== null && $this->value < $this->min_threshold) {
            return self::STATUS_CRITICAL;
        }

        if ($this->max_threshold !== null && $this->value > $this->max_threshold) {
            return self::STATUS_CRITICAL;
        }

        // Zona de advertencia: 10% antes del umbral
        if ($this->min_threshold !== null) {
            $warningMin = $this->min_threshold * 1.1;
            if ($this->value < $warningMin) {
                return self::STATUS_WARNING;
            }
        }

        if ($this->max_threshold !== null) {
            $warningMax = $this->max_threshold * 0.9;
            if ($this->value > $warningMax) {
                return self::STATUS_WARNING;
            }
        }

        return self::STATUS_NORMAL;
    }

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Calcular estado automáticamente antes de guardar
        static::saving(function ($measurement) {
            if ($measurement->min_threshold !== null || $measurement->max_threshold !== null) {
                $measurement->status = $measurement->calculateStatus();
            }
        });
    }
}
