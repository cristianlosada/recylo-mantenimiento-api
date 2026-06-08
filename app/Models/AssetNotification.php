<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetNotification extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'asset_notifications';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'asset_id',
        'email',
        'notify_on_create',
        'notify_on_open',
        'notify_on_close',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'notify_on_create' => 'boolean',
        'notify_on_open' => 'boolean',
        'notify_on_close' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ===================================
    // RELATIONSHIPS
    // ===================================

    /**
     * Activo al que pertenece la notificación
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
