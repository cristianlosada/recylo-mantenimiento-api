<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarehouseStock extends Model
{
    use HasFactory;

    protected $table = 'warehouse_stock';

    public $timestamps = false; // Solo tiene updated_at

    protected $fillable = [
        'warehouse_id',
        'material_id',
        'quantity',
        'average_unit_cost',
        'location',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'average_unit_cost' => 'decimal:2',
        'updated_at' => 'datetime',
    ];

    // Relaciones
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    // Accessors
    public function getTotalValueAttribute()
    {
        return $this->quantity * ($this->average_unit_cost ?? $this->material->unit_cost ?? 0);
    }

    // Scopes
    public function scopeLowStock($query)
    {
        return $query->whereHas('material', function($q) {
            $q->whereRaw('warehouse_stock.quantity < materials.minimum_stock');
        });
    }
}
