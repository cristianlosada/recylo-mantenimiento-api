<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderTimeLog extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_order_time_logs';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_order_id',
        'user_id',
        'start_time',
        'end_time',
        'hours_worked',
        'hourly_rate',
        'total_cost',
        'labor_type',
        'work_description',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'hours_worked' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    // ===================================
    // CONSTANTS
    // ===================================

    public const LABOR_TYPE_REGULAR = 'regular';
    public const LABOR_TYPE_OVERTIME = 'overtime';
    public const LABOR_TYPE_WEEKEND = 'weekend';
    public const LABOR_TYPE_HOLIDAY = 'holiday';

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeForWorkOrder($query, $workOrderId)
    {
        return $query->where('work_order_id', $workOrderId);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByLaborType($query, $laborType)
    {
        return $query->where('labor_type', $laborType);
    }

    public function scopeRegular($query)
    {
        return $query->where('labor_type', self::LABOR_TYPE_REGULAR);
    }

    public function scopeOvertime($query)
    {
        return $query->where('labor_type', self::LABOR_TYPE_OVERTIME);
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Calculate hours worked based on start and end time
     */
    public function calculateHoursWorked(): void
    {
        if ($this->start_time && $this->end_time) {
            $this->hours_worked = $this->start_time->diffInHours($this->end_time, true);
        }
    }

    /**
     * Calculate total cost based on hours worked and hourly rate
     */
    public function calculateTotalCost(): void
    {
        $this->total_cost = $this->hours_worked * $this->hourly_rate;
    }

    /**
     * Automatically calculate both hours and cost
     */
    public function calculateAll(): void
    {
        $this->calculateHoursWorked();
        $this->calculateTotalCost();
    }
}
