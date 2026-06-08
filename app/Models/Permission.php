<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'module_id',
        'action',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con el módulo al que pertenece
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id');
    }

    /**
     * Relación con permisos de roles
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Relación con roles a través de la tabla pivote
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
                    ->withTimestamps();
    }

    /**
     * Scope para permisos activos
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
     * Scope para filtrar por módulo
     */
    public function scopeByModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope para filtrar por acción
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Agrupar permisos por módulo
     */
    public static function groupByModule()
    {
        return static::active()
                    ->with('module')
                    ->get()
                    ->groupBy(fn($p) => $p->module?->code ?? 'UNKNOWN')
                    ->map(function ($permissions) {
                        return $permissions->groupBy('action');
                    });
    }

    /**
     * Verificar si es un permiso crítico
     */
    public function isCritical(): bool
    {
        $criticalActions = ['DELETE', 'ADMIN'];
        return in_array($this->action, $criticalActions);
    }
}