<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Component extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'components';

    protected $fillable = [
        'company_id',
        'component_type_id',
        'code',
        'name',
        'description',
        'reference',
        'brand',
        'unit_of_measure',
        'unit_cost',
        'minimum_stock',
        'maximum_stock',
        'reorder_point',
        'is_active',
        'is_critical',
        'image_path',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'unit_cost'      => 'decimal:2',
        'minimum_stock'  => 'decimal:3',
        'maximum_stock'  => 'decimal:3',
        'reorder_point'  => 'decimal:3',
        'is_active'      => 'boolean',
        'is_critical'    => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ComponentType::class, 'component_type_id');
    }

    public function stock(): HasMany
    {
        return $this->hasMany(ComponentWarehouseStock::class);
    }

    public function assetComponents(): HasMany
    {
        return $this->hasMany(AssetComponent::class);
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(AssetComponentConsumption::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeCritical($query)
    {
        return $query->where('is_critical', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeLowStock($query)
    {
        return $query->whereHas('stock', function ($q) {
            $q->where('quantity', '>', 0)
              ->whereColumn('quantity', '<', 'components.minimum_stock');
        });
    }

    public function scopeOutOfStock($query)
    {
        return $query->whereDoesntHave('stock', function ($q) {
            $q->where('quantity', '>', 0);
        });
    }

    /**
     * Stock total consolidado en todos los almacenes.
     */
    public function getTotalStockAttribute(): float
    {
        return (float) $this->stock->sum('quantity');
    }

    /**
     * ¿El stock total está por debajo del mínimo?
     */
    public function getIsLowStockAttribute(): bool
    {
        return $this->minimum_stock > 0 && $this->total_stock < $this->minimum_stock;
    }

    /**
     * ¿El stock total supera el máximo?
     */
    public function getIsOverstockAttribute(): bool
    {
        return $this->maximum_stock && $this->total_stock > $this->maximum_stock;
    }
}
