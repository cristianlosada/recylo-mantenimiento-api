<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionStatus extends Model
{
    use HasFactory;

    // Constantes para IDs de estados
    const STATUS_ACTIVE = 1;
    const STATUS_SUSPENDED = 3;
    const STATUS_CANCELLED = 4;
    const STATUS_EXPIRED = 5;
    const STATUS_PENDING = 2;

    // Constantes para códigos de estados
    const CODE_ACTIVE = 'active';
    const CODE_SUSPENDED = 'suspended';
    const CODE_CANCELLED = 'cancelled';
    const CODE_EXPIRED = 'expired';
    const CODE_PENDING = 'pending';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con suscripciones
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanyPlanSubscription::class);
    }

    /**
     * Scope para estados activos
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
     * Verificar si es un estado activo de suscripción
     */
    public function isActiveStatus(): bool
    {
        return $this->code === 'ACTIVE';
    }

    /**
     * Verificar si es un estado suspendido
     */
    public function isSuspended(): bool
    {
        return $this->code === 'SUSPENDED';
    }

    /**
     * Verificar si es un estado cancelado
     */
    public function isCancelled(): bool
    {
        return $this->code === 'CANCELLED';
    }

    /**
     * Verificar si es un estado que permite uso del sistema
     */
    public function allowsSystemAccess(): bool
    {
        return in_array($this->code, ['ACTIVE', 'PENDING']);
    }
}