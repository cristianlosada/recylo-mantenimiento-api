<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'module',
        'severity',
        'log_details',
        'is_active'
    ];

    protected $casts = [
        'log_details' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con logs de auditoría
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Scope para acciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar por módulo
     */
    public function scopeByModule($query, $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope para filtrar por severidad
     */
    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope para acciones críticas
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'CRITICAL');
    }

    /**
     * Registrar un log de auditoría
     */
    public function log(array $data): AuditLog
    {
        return $this->auditLogs()->create($data);
    }
}