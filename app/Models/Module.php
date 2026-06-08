<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'icon',
        'order',
        'is_core',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_core' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Relación con permisos del módulo
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Relación con planes que incluyen este módulo
     */
    public function planModules(): HasMany
    {
        return $this->hasMany(PlanModule::class);
    }

    /**
     * Relación con planes a través de la tabla pivote
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_modules')
                    ->withTimestamps();
    }

    /**
     * Relación con empresas que tienen habilitado este módulo
     */
    public function companyEnabledModules(): HasMany
    {
        return $this->hasMany(CompanyEnabledModule::class);
    }

    /**
     * Relación con empresas a través de módulos habilitados
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_enabled_modules')
                    ->withPivot(['enabled', 'config'])
                    ->withTimestamps();
    }

    /**
     * Scope para módulos activos
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
     * Verificar si está habilitado para una empresa
     */
    public function isEnabledForCompany(int $companyId): bool
    {
        return $this->companyEnabledModules()
                    ->where('company_id', $companyId)
                    ->where('enabled', true)
                    ->exists();
    }
}