<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetComponentConsumption extends Model
{
    use HasFactory;

    protected $table = 'asset_component_consumptions';

    const MOVEMENT_INSTALLATION = 'installation'; // +installed_qty, -stock
    const MOVEMENT_REPLACEMENT  = 'replacement';  // installed_qty sin cambio, -stock
    const MOVEMENT_REMOVAL      = 'removal';      // -installed_qty, +stock

    protected $fillable = [
        'company_id',
        'asset_id',
        'component_id',
        'work_order_id',
        'warehouse_id',
        'movement_type',
        'quantity_consumed',
        'quantity_delta',
        'returns_to_stock',
        'unit_cost',
        'total_cost',
        'stock_after',
        'notes',
        'performed_by',
        'consumed_at',
    ];

    protected $casts = [
        'quantity_consumed' => 'decimal:3',
        'quantity_delta'    => 'decimal:3',
        'returns_to_stock'  => 'boolean',
        'unit_cost'         => 'decimal:2',
        'total_cost'        => 'decimal:2',
        'stock_after'       => 'decimal:3',
        'consumed_at'       => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function scopeForAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeForComponent($query, $componentId)
    {
        return $query->where('component_id', $componentId);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
