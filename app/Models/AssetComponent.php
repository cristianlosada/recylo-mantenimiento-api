<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetComponent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'asset_components';

    const STATUS_NORMAL       = 'normal';
    const STATUS_LOW_STOCK    = 'low_stock';
    const STATUS_OUT_OF_STOCK = 'out_of_stock';

    protected $fillable = [
        'asset_id',
        'component_id',
        'specified_quantity',
        'installed_quantity',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'specified_quantity' => 'decimal:3',
        'installed_quantity' => 'decimal:3',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    /**
     * Recalcula y guarda el status según cantidades.
     */
    public function recalculateStatus(): void
    {
        $installed  = (float) $this->installed_quantity;
        $specified  = (float) $this->specified_quantity;

        if ($installed <= 0) {
            $this->status = self::STATUS_OUT_OF_STOCK;
        } elseif ($installed < $specified) {
            $this->status = self::STATUS_LOW_STOCK;
        } else {
            $this->status = self::STATUS_NORMAL;
        }
    }
}
