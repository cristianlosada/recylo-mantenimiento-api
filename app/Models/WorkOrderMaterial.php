<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderMaterial extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_order_materials';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_order_id',
        'material_id',
        'warehouse_id',
        'material_status',
        
        // Cantidades
        'quantity_planned',
        'quantity_requested',
        'quantity_approved',
        'quantity_delivered',
        'quantity_consumed',
        'quantity_returned',
        'unit',
        
        // Costos
        'unit_cost',
        'total_cost',
        
        // Fechas
        'requested_at',
        'approved_at',
        'delivered_at',
        'consumed_at',
        'returned_at',
        'completed_at',
        
        // Usuarios
        'requested_by',
        'approved_by',
        'delivered_by',
        'consumed_by',
        'returned_by',
        'received_by',
        
        // Notas
        'request_notes',
        'approval_notes',
        'delivery_notes',
        'consumption_notes',
        'return_notes',
        'reception_notes',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'quantity_planned' => 'decimal:3',
        'quantity_requested' => 'decimal:3',
        'quantity_approved' => 'decimal:3',
        'quantity_delivered' => 'decimal:3',
        'quantity_consumed' => 'decimal:3',
        'quantity_returned' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'delivered_at' => 'datetime',
        'consumed_at' => 'datetime',
        'returned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function consumedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consumed_by');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeForWorkOrder($query, $workOrderId)
    {
        return $query->where('work_order_id', $workOrderId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('material_status', $status);
    }

    public function scopeRequested($query)
    {
        return $query->where('material_status', 'requested');
    }

    public function scopeDelivered($query)
    {
        return $query->where('material_status', 'delivered');
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Calculate total cost based on quantity consumed and unit cost
     */
    public function calculateTotalCost(): void
    {
        $this->total_cost = $this->quantity_consumed * $this->unit_cost;
    }

    /**
     * Get variance between planned and consumed quantity
     */
    public function getQuantityVarianceAttribute(): ?float
    {
        if ($this->quantity_planned === null || $this->quantity_consumed === null) {
            return null;
        }

        return $this->quantity_consumed - $this->quantity_planned;
    }

    /**
     * Check if material is a tool (non-consumable)
     */
    public function isTool(): bool
    {
        return $this->material && $this->material->is_tool;
    }

    /**
     * Constants for material status
     */
    const STATUS_PLANNED = 'planned';
    const STATUS_REQUESTED = 'requested';
    const STATUS_APPROVED = 'approved';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_IN_USE = 'in_use';
    const STATUS_CONSUMED = 'consumed';
    const STATUS_RETURNED = 'returned';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
}
