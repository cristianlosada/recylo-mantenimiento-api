<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Material extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'materials';

    protected $fillable = [
        'company_id',
        'material_category_id',
        'code',
        'name',
        'description',
        'barcode',
        'sku',
        'manufacturer_part_number',
        'unit_of_measure',
        'unit_cost',
        'minimum_stock',
        'maximum_stock',
        'reorder_point',
        'reorder_quantity',
        'default_supplier',
        'is_active',
        'is_critical',
        'image_path',
        'notes',
        'created_by',
        
        // Campos para herramientas (is_tool = true)
        'is_tool',
        'brand',
        'model',
        'serial_number',
        'tool_status',
        'requires_calibration',
        'last_calibration_date',
        'next_calibration_date',
        'calibration_frequency_days',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'minimum_stock' => 'decimal:3',
        'maximum_stock' => 'decimal:3',
        'reorder_point' => 'decimal:3',
        'reorder_quantity' => 'decimal:3',
        'is_active' => 'boolean',
        'is_critical' => 'boolean',
        'is_tool' => 'boolean',
        'requires_calibration' => 'boolean',
        'last_calibration_date' => 'date',
        'next_calibration_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relaciones
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'material_category_id');
    }

    public function stock(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    public function creator(): BelongsTo
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

    public function scopeLowStock($query)
    {
        return $query->whereHas('stock', function($q) {
            $q->whereRaw('quantity < minimum_stock');
        });
    }

    public function scopeTools($query)
    {
        return $query->where('is_tool', true);
    }

    public function scopeMaterials($query)
    {
        return $query->where('is_tool', false);
    }

    public function scopeAvailableTools($query)
    {
        return $query->where('is_tool', true)
                     ->where('tool_status', 'available');
    }

    public function scopeRequiresCalibration($query)
    {
        return $query->where('requires_calibration', true)
                     ->where('next_calibration_date', '<=', now()->addDays(30));
    }

    // Helpers
    public function isTool(): bool
    {
        return $this->is_tool === true;
    }

    public function isMaterial(): bool
    {
        return $this->is_tool === false;
    }

    public function isAvailable(): bool
    {
        return $this->is_tool && $this->tool_status === 'available';
    }

    public function needsCalibration(): bool
    {
        return $this->requires_calibration 
               && $this->next_calibration_date 
               && $this->next_calibration_date <= now();
    }
}
