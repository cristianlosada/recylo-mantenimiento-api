<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetSpecification extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'asset_specifications';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'asset_id',
        'spec_key',
        'spec_value',
        'spec_unit',
        'spec_type',
        'display_order'
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'display_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Orden por defecto
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('order', function ($builder) {
            $builder->orderBy('display_order', 'asc');
        });
    }

    /**
     * ===============================================
     * RELACIONES ELOQUENT
     * ===============================================
     */

    /**
     * Relación con activo (muchos a uno)
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
