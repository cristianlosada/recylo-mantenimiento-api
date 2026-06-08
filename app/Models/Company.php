<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Company extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected $fillable = [
        'legal_name',
        'trade_name',
        'tax_id',
        'country_id',
        'municipality_id',
        'department_geo_id',
        'company_size_id',
        'economic_activity',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'founded_at',
        'employee_count',
        'status',
    ];

    protected $casts = [
        'founded_at' => 'date',
        'employee_count' => 'integer',
    ];

    /**
     * Relación con país
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Relación con municipio
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * Relación con departamento
     */
    public function departmentGeo(): BelongsTo
    {
        return $this->belongsTo(DepartmentGeo::class);
    }

    /**
     * Relación con tamaño de empresa
     */
    public function companySize(): BelongsTo
    {
        return $this->belongsTo(CompanySize::class);
    }

    /**
     * Relación con sedes de la empresa
     */
    public function sites(): HasMany
    {
        return $this->hasMany(CompanySite::class);
    }

    /**
     * Relación con configuraciones de la empresa
     */
    public function settings(): HasMany
    {
        return $this->hasMany(CompanySetting::class);
    }

    /**
     * Relación con documentos de la empresa
     */
    public function documents(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /**
     * Relación con usuarios de la empresa
     */
    public function userCompanies(): HasMany
    {
        return $this->hasMany(UserCompany::class);
    }

    /**
     * Relación con usuarios a través de la tabla pivote
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_companies')
                    ->withPivot([
                        'status', 
                        'employee_code', 
                        'hire_date', 
                        'termination_date', 
                        'termination_reason',
                        'salary_amount',
                        'salary_currency',
                        'is_primary',
                        'site_id',
                        'department',
                        'job_position',
                        'employment_type',
                        'direct_supervisor_id'
                    ])
                    ->withTimestamps();
    }

    /**
     * Relación con suscripciones
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanyPlanSubscription::class);
    }

    /**
     * Relación con módulos habilitados
     */
    public function enabledModules(): HasMany
    {
        return $this->hasMany(CompanyEnabledModule::class);
    }

    /**
     * Scope para empresas activas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para buscar por tax_id
     */
    public function scopeByTaxId($query, $taxId)
    {
        return $query->where('tax_id', $taxId);
    }

    /**
     * Accessor para nombre de visualización
     */
    public function getNameAttribute(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }

    /**
     * Accessor para nombre completo
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }
}