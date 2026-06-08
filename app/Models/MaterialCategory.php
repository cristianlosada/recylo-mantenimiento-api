<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class MaterialCategory extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $table = 'material_categories';

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'description',
        'parent_category_id',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relaciones
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parentCategory(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'parent_category_id');
    }

    public function childCategories(): HasMany
    {
        return $this->hasMany(MaterialCategory::class, 'parent_category_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Verifica si esta categoría o su categoría padre es de herramientas
     * 
     * @return bool
     */
    public function isToolCategory(): bool
    {
        // Verificar si la categoría actual es "Herramientas" (id=2 o code contiene HERR)
        if ($this->code === 'CAT-HERR' || str_contains($this->code, 'HERR')) {
            return true;
        }

        // Si tiene padre, verificar recursivamente
        if ($this->parent_category_id) {
            $parent = $this->parentCategory;
            if ($parent && $parent->code === 'CAT-HERR') {
                return true;
            }
        }

        return false;
    }
}
