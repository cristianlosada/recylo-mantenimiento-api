<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentWarehouseStock extends Model
{
    protected $table = 'component_warehouse_stock';

    public $timestamps = false; // solo tiene updated_at con default

    protected $fillable = [
        'warehouse_id',
        'component_id',
        'quantity',
        'average_unit_cost',
        'location',
    ];

    protected $casts = [
        'quantity'          => 'decimal:3',
        'average_unit_cost' => 'decimal:2',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Actualiza el stock y recalcula el costo promedio ponderado.
     * quantity positivo = entrada, negativo = salida.
     */
    public function applyMovement(float $quantity, ?float $unitCost = null): void
    {
        if ($quantity > 0 && $unitCost !== null) {
            // Recalcular costo promedio ponderado en entradas
            $currentValue = $this->quantity * ($this->average_unit_cost ?? 0);
            $newValue     = $quantity * $unitCost;
            $newQty       = $this->quantity + $quantity;
            $this->average_unit_cost = $newQty > 0
                ? round(($currentValue + $newValue) / $newQty, 2)
                : $unitCost;
        }

        $this->quantity = max(0, $this->quantity + $quantity);
        $this->save();
    }
}
