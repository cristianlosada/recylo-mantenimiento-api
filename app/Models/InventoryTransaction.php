<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\Auditable;

class InventoryTransaction extends Model
{
    use HasFactory, Auditable;

    protected $table = 'inventory_transactions';

    protected $fillable = [
        'company_id',
        'transaction_code',
        'transaction_type',
        'warehouse_id',
        'material_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'balance_after',
        'reason',
        'work_order_id',
        'purchase_order_number',
        'reference_document',
        'from_warehouse_id',
        'to_warehouse_id',
        'transaction_date',
        'performed_by',
        'approved_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'balance_after' => 'decimal:3',
        'transaction_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    // Scopes
    public function scopeByType($query, $type)
    {
        return $query->where('transaction_type', $type);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('transaction_date', [$startDate, $endDate]);
    }

    public function scopeWorkOrderTransactions($query)
    {
        return $query->whereIn('transaction_type', [
            'work_order_out',
            'work_order_return',
            'tool_assignment',
            'tool_return'
        ]);
    }

    public function scopeToolTransactions($query)
    {
        return $query->whereIn('transaction_type', ['tool_assignment', 'tool_return']);
    }
}
