<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\Auditable;

class Role extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'code',
        'name',
        'description',
        'company_id',
        'is_system',
        'is_active'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con la empresa (company)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación con permisos del rol
     */
    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /**
     * Relación con permisos a través de la tabla pivote
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
                    ->withTimestamps();
    }

    /**
     * Relación con usuarios del rol
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Relación con usuarios a través de la tabla pivote
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
                    ->withPivot(['company_id', 'assigned_at', 'expires_at'])
                    ->withTimestamps();
    }

    /**
     * Relación con delegaciones de este rol (delegaciones temporales entre usuarios)
     */
    public function delegations(): HasMany
    {
        return $this->hasMany(RoleDelegation::class);
    }

    /**
     * Scope para roles activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para roles del sistema
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope para roles de empresa
     */
    public function scopeCompany($query, $companyId = null)
    {
        if ($companyId) {
            return $query->where('company_id', $companyId);
        }
        return $query->whereNotNull('company_id');
    }

    /**
     * Scope para buscar por código
     */
    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }

    /**
     * Verificar si el rol tiene un permiso específico
     */
    public function hasPermission(string $permissionCode): bool
    {
        return $this->permissions()
                    ->where('code', $permissionCode)
                    ->exists();
    }

    /**
     * Asignar un permiso al rol
     */
    public function givePermission(Permission $permission): void
    {
        if (!$this->hasPermission($permission->code)) {
            $this->permissions()->attach($permission);
        }
    }

    /**
     * Revocar un permiso del rol
     */
    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission);
    }

    /**
     * Verificar si es un rol de administración
     */
    public function isAdminRole(): bool
    {
        return in_array($this->code, ['SUPER_ADMIN', 'ADMIN']);
    }
}