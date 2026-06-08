<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'payload',
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'device_info',
        'location',
        'login_time',
        'last_activity',
        'logout_time',
        'is_active',
        'session_type',
        'expires_at'
    ];

    protected $casts = [
        'login_time' => 'datetime',
        'last_activity' => 'datetime',
        'logout_time' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'device_info' => 'json',
        'location' => 'json'
    ];

    /**
     * Tipos de sesión disponibles
     */
    const SESSION_TYPE_WEB = 'web';
    const SESSION_TYPE_API = 'api';
    const SESSION_TYPE_MOBILE = 'mobile';

    /**
     * Relación con usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope para sesiones activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para usuario específico
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para sesiones web
     */
    public function scopeWeb($query)
    {
        return $query->where('session_type', self::SESSION_TYPE_WEB);
    }

    /**
     * Scope para sesiones API
     */
    public function scopeApi($query)
    {
        return $query->where('session_type', self::SESSION_TYPE_API);
    }

    /**
     * Scope para sesiones móviles
     */
    public function scopeMobile($query)
    {
        return $query->where('session_type', self::SESSION_TYPE_MOBILE);
    }

    /**
     * Scope para sesiones recientes
     */
    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('login_time', '>=', now()->subHours($hours));
    }

    /**
     * Scope para sesiones desde IP específica
     */
    public function scopeFromIP($query, string $ip)
    {
        return $query->where('ip_address', $ip);
    }

    /**
     * Scope para sesiones expiradas
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now());
    }

    /**
     * Scope para sesiones inactivas
     */
    public function scopeInactive($query, int $minutes = 30)
    {
        return $query->where('last_activity', '<', now()->subMinutes($minutes))
                    ->where('is_active', true);
    }

    /**
     * Verificar si la sesión está activa
     */
    public function isActive(): bool
    {
        return $this->is_active && 
               $this->expires_at > now() &&
               !$this->logout_time;
    }

    /**
     * Verificar si la sesión está expirada
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Verificar si está inactiva por mucho tiempo
     */
    public function isInactive(int $minutes = 30): bool
    {
        return $this->last_activity && 
               $this->last_activity->diffInMinutes(now()) > $minutes;
    }

    /**
     * Actualizar última actividad
     */
    public function updateActivity(): void
    {
        $this->update(['last_activity' => now()]);
    }

    /**
     * Cerrar sesión
     */
    public function logout(): void
    {
        $this->is_active = false;
        $this->logout_time = now();
        $this->save();
    }

    /**
     * Extender sesión
     */
    public function extend(int $minutes = 120): void
    {
        $this->update([
            'expires_at' => now()->addMinutes($minutes),
            'last_activity' => now(),
        ]);
    }

    /**
     * Obtener duración de la sesión
     */
    public function getDurationAttribute(): ?string
    {
        if (!$this->logout_time) {
            return null;
        }

        return $this->login_time->diffForHumans($this->logout_time);
    }

    /**
     * Obtener tiempo restante
     */
    public function getTimeRemainingAttribute(): ?string
    {
        if (!$this->expires_at || $this->isExpired()) {
            return null;
        }

        return now()->diffForHumans($this->expires_at);
    }

    /**
     * Obtener información del dispositivo de manera legible
     */
    public function getDeviceTypeAttribute(): string
    {
        if (empty($this->device_info)) {
            return 'Desconocido';
        }

        $info = $this->device_info;

        if (str_contains(strtolower($this->user_agent), 'mobile')) {
            return 'Móvil';
        }

        if (str_contains(strtolower($this->user_agent), 'tablet')) {
            return 'Tablet';
        }

        return 'Escritorio';
    }

    /**
     * Obtener navegador principal
     */
    public function getBrowserAttribute(): string
    {
        $userAgent = strtolower($this->user_agent);

        if (str_contains($userAgent, 'chrome')) {
            return 'Chrome';
        } elseif (str_contains($userAgent, 'firefox')) {
            return 'Firefox';
        } elseif (str_contains($userAgent, 'safari')) {
            return 'Safari';
        } elseif (str_contains($userAgent, 'edge')) {
            return 'Edge';
        }

        return 'Desconocido';
    }

    /**
     * Obtener ubicación legible
     */
    public function getLocationStringAttribute(): ?string
    {
        if (empty($this->location)) {
            return null;
        }

        $location = $this->location;
        
        if (isset($location['city']) && isset($location['country'])) {
            return $location['city'] . ', ' . $location['country'];
        }

        if (isset($location['country'])) {
            return $location['country'];
        }

        return null;
    }
}