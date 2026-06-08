<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetPriority extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'asset_priorities';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'code',
        'name',
        'level',
        'color',
        'description',
        'is_active'
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'level' => 'integer',
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
        return $this->hasMany(Asset::class, 'priority_id');
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope para filtrar solo prioridades activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para ordenar por nivel
     */
    public function scopeOrderedByLevel($query)
    {
        return $query->orderBy('level', 'asc');
    }
}
