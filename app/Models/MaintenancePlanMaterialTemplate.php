<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanMaterialTemplate extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'maintenance_plan_material_templates';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'maintenance_plan_id',
        'material_id',
        'estimated_quantity',
        'notes',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'estimated_quantity' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // =====================================================
    // RELACIONES
    // =====================================================

    /**
     * Plan de mantenimiento al que pertenece
     */
    public function maintenancePlan(): BelongsTo
    {
        return $this->belongsTo(MaintenancePlan::class);
    }

    /**
     * Material o herramienta
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    // =====================================================
    // ACCESSORS
    // =====================================================

    /**
     * Obtener el costo estimado del material
     */
    public function getEstimatedCostAttribute(): ?float
    {
        if (!$this->material) {
            return null;
        }

        return $this->estimated_quantity * $this->material->unit_price;
    }

    /**
     * Verificar si el material está disponible en inventario
     */
    public function getIsAvailableAttribute(): bool
    {
        if (!$this->material) {
            return false;
        }

        // Si es herramienta, verificar que esté disponible
        if ($this->material->is_tool) {
            return $this->material->available_quantity > 0;
        }

        // Si es consumible, verificar que haya suficiente stock
        return $this->material->available_quantity >= $this->estimated_quantity;
    }
}
