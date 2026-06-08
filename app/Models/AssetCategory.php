<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'asset_categories';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'color',
        'is_active'
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
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
        return $this->hasMany(Asset::class, 'category_id');
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope para filtrar solo categorías activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
