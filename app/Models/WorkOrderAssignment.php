<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderAssignment extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_order_assignments';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_order_id',
        'user_id',
        'role',
        'assigned_by',
        'assigned_at',
        'notes',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    /**
     * Deshabilitar timestamps automáticos
     */
    public $timestamps = false;

    // ===================================
    // CONSTANTS
    // ===================================

    public const ROLE_TECHNICIAN = 'technician';
    public const ROLE_SUPERVISOR = 'supervisor';
    public const ROLE_HELPER = 'helper';
    public const ROLE_SPECIALIST = 'specialist';

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

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
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

    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    public function scopeTechnicians($query)
    {
        return $query->where('role', self::ROLE_TECHNICIAN);
    }

    public function scopeSupervisors($query)
    {
        return $query->where('role', self::ROLE_SUPERVISOR);
    }
}
