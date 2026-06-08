<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'module_id',
        'included',
        'max_users',
        'max_records',
    ];

    protected $casts = [
        'included' => 'boolean',
        'max_users' => 'integer',
        'max_records' => 'integer',
    ];

    /**
     * Relación con plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Relación con módulo
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Scope para plan específico
     */
    public function scopeForPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }

    /**
     * Scope para módulo específico
     */
    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }
}