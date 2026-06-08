<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssetVendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'asset_vendors';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'type',
        'contact_name',
        'contact_email',
        'contact_phone',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $hidden = ['deleted_at'];

    // -------------------------------------------------------
    // RELACIONES
    // -------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Activos donde este vendor es el fabricante */
    public function manufacturedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'manufacturer_id');
    }

    /** Activos donde este vendor es el proveedor */
    public function suppliedAssets(): HasMany
    {
        return $this->hasMany(Asset::class, 'supplier_id');
    }

    // -------------------------------------------------------
    // SCOPES
    // -------------------------------------------------------

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeManufacturers($query)
    {
        return $query->whereIn('type', ['manufacturer', 'both']);
    }

    public function scopeSuppliers($query)
    {
        return $query->whereIn('type', ['supplier', 'both']);
    }
}
