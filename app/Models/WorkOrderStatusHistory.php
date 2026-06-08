<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderStatusHistory extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_order_status_history';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_order_id',
        'from_status',
        'to_status',
        'changed_by',
        'changed_at',
        'reason',
        'metadata',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Deshabilitar timestamps automáticos
     */
    public $timestamps = false;

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeForWorkOrder($query, $workOrderId)
    {
        return $query->where('work_order_id', $workOrderId);
    }

    public function scopeFromStatus($query, $status)
    {
        return $query->where('from_status', $status);
    }

    public function scopeToStatus($query, $status)
    {
        return $query->where('to_status', $status);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('changed_by', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('changed_at', 'desc');
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('changed_at', [$startDate, $endDate]);
    }
}
