<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionChange extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'company_plan_subscription_id',
        'change_type',
        'from_plan_id',
        'to_plan_id',
        'prorated_amount',
        'effective_date',
        'notes',
    ];

    protected $casts = [
        'prorated_amount' => 'decimal:2',
        'effective_date' => 'date',
    ];

    /**
     * Relación con empresa
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación con suscripción
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(CompanyPlanSubscription::class, 'company_plan_subscription_id');
    }

    /**
     * Relación con plan origen
     */
    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    /**
     * Relación con plan destino
     */
    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    /**
     * Scope por tipo de cambio
     */
    public function scopeByChangeType($query, $type)
    {
        return $query->where('change_type', $type);
    }

    /**
     * Scope para empresa específica
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
