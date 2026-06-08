<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkRequestChecklistTemplate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_request_checklist_templates';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'checklist_items',
        'asset_category_id',
        'request_type',
        'priority',
        'is_active',
        'is_mandatory',
        'display_order',
        'created_by',
    ];

    /**
     * Campos ocultos en serialización JSON
     */
    protected $hidden = [
        'deleted_at',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'checklist_items' => 'array',
        'is_active' => 'boolean',
        'is_mandatory' => 'boolean',
        'display_order' => 'integer',
    ];

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assetCategory(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(WorkRequestChecklistItem::class, 'template_id');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForCategory($query, $categoryId)
    {
        return $query->where(function ($q) use ($categoryId) {
            $q->whereNull('asset_category_id')
              ->orWhere('asset_category_id', $categoryId);
        });
    }

    public function scopeForType($query, $type)
    {
        return $query->where(function ($q) use ($type) {
            $q->whereNull('request_type')
              ->orWhere('request_type', $type);
        });
    }

    public function scopeForPriority($query, $priority)
    {
        return $query->where(function ($q) use ($priority) {
            $q->whereNull('priority')
              ->orWhere('priority', $priority);
        });
    }

    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Find applicable template for a work request.
     */
    public static function findApplicable(int $companyId, ?int $categoryId, string $requestType, string $priority): ?self
    {
        return static::where('company_id', $companyId)
            ->active()
            ->forCategory($categoryId)
            ->forType($requestType)
            ->forPriority($priority)
            ->ordered()
            ->first();
    }
}
