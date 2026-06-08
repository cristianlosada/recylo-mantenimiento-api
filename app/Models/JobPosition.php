<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPosition extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'code',
        'is_active',
        'can_lead_projects',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'can_lead_projects'  => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeLeadership($query)
    {
        return $query->where('can_lead_projects', true);
    }
}
