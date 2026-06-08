<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkRequestTag extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_request_tags';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'color',
        'description',
        'is_active',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function workRequests(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkRequest::class,
            'work_request_tag_assignments',
            'tag_id',
            'work_request_id'
        )->withPivot('assigned_by', 'assigned_at')
          ->withTimestamps();
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

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }

    // ===================================
    // ACCESSORS
    // ===================================

    public function getUsageCountAttribute(): int
    {
        return $this->workRequests()->count();
    }
}
