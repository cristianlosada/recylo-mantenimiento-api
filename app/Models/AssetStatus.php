<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetStatus extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'asset_statuses';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'color',
        'requires_note',
        'is_operational',
        'is_active'
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'requires_note' => 'boolean',
        'is_operational' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * ===============================================
     * RELACIONES ELOQUENT
     * ===============================================
     */

    /**
     * Relación con activos (uno a muchos)
     */
    public function assets()
    {
        return $this->hasMany(Asset::class, 'status_id');
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope para filtrar solo estados activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar estados operacionales
     */
    public function scopeOperational($query)
    {
        return $query->where('is_operational', true);
    }
}
