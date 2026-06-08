<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoleDelegation extends Model
{
    use HasFactory;

    protected $fillable = [
        'delegator_user_id',
        'delegatee_user_id',
        'role_id',
        'company_id',
        'reason',
        'delegated_at',
        'expires_at',
        'revoked_at',
    ];

    protected $casts = [
        'delegated_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Relación con usuario delegante
     */
    public function delegatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_user_id');
    }

    /**
     * Relación con usuario delegado
     */
    public function delegateUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegatee_user_id');
    }

    /**
     * Relación con rol
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Relación con empresa
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope para delegaciones activas
     */
    public function scopeActive($query)
    {
        return $query->whereNull('revoked_at');
    }

    /**
     * Scope para delegaciones vigentes
     */
    public function scopeCurrent($query)
    {
        return $query->where('delegated_at', '<=', now())
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>=', now());
                    })
                    ->whereNull('revoked_at');
    }

    /**
     * Scope para delegante específico
     */
    public function scopeForDelegator($query, $userId)
    {
        return $query->where('delegator_user_id', $userId);
    }

    /**
     * Scope para delegado específico
     */
    public function scopeForDelegate($query, $userId)
    {
        return $query->where('delegatee_user_id', $userId);
    }

    /**
     * Scope para empresa específica
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para delegaciones próximas a expirar
     */
    public function scopeExpiringInDays($query, $days = 7)
    {
        return $query->where('expires_at', '<=', now()->addDays($days))
                    ->where('expires_at', '>', now())
                    ->whereNull('revoked_at');
    }

    /**
     * Verificar si la delegación está vigente
     */
    public function isCurrent(): bool
    {
        return is_null($this->revoked_at) &&
               $this->delegated_at <= now() &&
               ($this->expires_at === null || $this->expires_at >= now());
    }

    /**
     * Verificar si está próxima a expirar
     */
    public function isNearExpiration(int $days = 7): bool
    {
        if (!$this->expires_at || $this->revoked_at) {
            return false;
        }

        return $this->expires_at->diffInDays(now()) <= $days && !$this->isExpired();
    }

    /**
     * Verificar si ya expiró
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Revocar delegación
     */
    public function revoke(): void
    {
        $this->revoked_at = now();
        $this->save();
    }

    /**
     * Extender delegación
     */
    public function extend(\Carbon\Carbon $newExpiresAt): void
    {
        $this->expires_at = $newExpiresAt;
        $this->save();
    }

    /**
     * Obtener duración de la delegación en días
     */
    public function getDurationInDaysAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }
        return $this->delegated_at->diffInDays($this->expires_at);
    }

    /**
     * Obtener días restantes
     */
    public function getDaysRemainingAttribute(): ?int
    {
        if (!$this->expires_at || $this->isExpired()) {
            return null;
        }

        return now()->diffInDays($this->expires_at);
    }
}