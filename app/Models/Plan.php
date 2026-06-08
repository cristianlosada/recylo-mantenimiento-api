<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'price',
        'currency',
        'billing_cycle_days',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'billing_cycle_days' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con módulos del plan
     */
    public function planModules(): HasMany
    {
        return $this->hasMany(PlanModule::class);
    }

    /**
     * Relación con módulos a través de la tabla pivote
     */
    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_modules')
                    ->withTimestamps();
    }

    /**
     * Relación con suscripciones del plan
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanyPlanSubscription::class);
    }

    /**
     * Scope para planes activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para buscar por código
     */
    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Scope para ordenar por precio
     */
    public function scopeOrderByPrice($query, $direction = 'asc')
    {
        return $query->orderBy('price', $direction);
    }

    /**
     * Verificar si el plan incluye un módulo
     */
    public function hasModule(string $moduleCode): bool
    {
        return $this->modules()
                    ->where('code', $moduleCode)
                    ->exists();
    }

    /**
     * Calcular precio mensual estimado
     */
    public function getMonthlyPriceAttribute(): float
    {
        if (!$this->billing_cycle_days || $this->billing_cycle_days <= 0) {
            return (float) $this->price;
        }

        return round(($this->price / $this->billing_cycle_days) * 30, 2);
    }
}