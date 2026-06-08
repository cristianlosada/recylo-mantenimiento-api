<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenancePlanChecklistTemplate extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'maintenance_plan_checklist_templates';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'maintenance_plan_id',
        'item_order',
        'item_text',
        'requires_photo',
        'is_mandatory',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'item_order' => 'integer',
        'requires_photo' => 'boolean',
        'is_mandatory' => 'boolean',
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

    // =====================================================
    // SCOPES
    // =====================================================

    /**
     * Scope: Ordenar por orden de ejecución
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('item_order', 'asc');
    }

    /**
     * Scope: Solo ítems obligatorios
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    /**
     * Scope: Ítems que requieren foto
     */
    public function scopeRequiringPhoto($query)
    {
        return $query->where('requires_photo', true);
    }
}
