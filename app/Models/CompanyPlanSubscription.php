<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPlanSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'plan_id',
        'subscription_status_id',
        'start_date',
        'end_date',
        'amount',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Relación con empresa
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación con plan
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Relación con estado de suscripción
     */
    public function subscriptionStatus(): BelongsTo
    {
        return $this->belongsTo(SubscriptionStatus::class);
    }

    /**
     * Scope para suscripciones activas
     */
    public function scopeActive($query)
    {
        return $query->whereHas('subscriptionStatus', function ($q) {
            $q->where('code', 'active');
        });
    }

    /**
     * Scope para suscripciones vigentes
     */
    public function scopeCurrent($query)
    {
        return $query->where('start_date', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', now());
                    });
    }

    /**
     * Scope para empresa específica
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para suscripciones próximas a vencer
     */
    public function scopeExpiringInDays($query, $days = 30)
    {
        return $query->whereNotNull('end_date')
                    ->where('end_date', '<=', now()->addDays($days))
                    ->where('end_date', '>', now());
    }

    /**
     * Verificar si la suscripción está activa y vigente
     */
    public function isActiveAndCurrent(): bool
    {
        return $this->subscriptionStatus 
               && $this->subscriptionStatus->isActiveStatus() 
               && $this->start_date 
               && $this->start_date <= now() 
               && ($this->end_date === null || $this->end_date >= now());
    }

    /**
     * Verificar si está próxima a vencer
     */
    public function isNearExpiration(int $days = 30): bool
    {
        if (!$this->end_date) {
            return false;
        }

        return $this->end_date->diffInDays(now()) <= $days && !$this->isExpired();
    }

    /**
     * Verificar si ya expiró
     */
    public function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }

    /**
     * Calcular días restantes
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->end_date || $this->isExpired()) {
            return null;
        }

        return now()->diffInDays($this->end_date);
    }
}
