<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Municipality extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_geo_id',
        'dane_code',
        'name',
        'municipality_type',
        'population_category',
        'is_capital',
        'altitude_meters',
    ];

    protected $casts = [
        'is_capital' => 'boolean',
        'altitude_meters' => 'integer',
    ];

    /**
     * Relación con departamento
     */
    public function departmentGeo(): BelongsTo
    {
        return $this->belongsTo(DepartmentGeo::class, 'department_geo_id');
    }

    /**
     * Alias para compatibilidad (mantener department también)
     */
    public function department(): BelongsTo
    {
        return $this->departmentGeo();
    }

    /**
     * Relación con empresas
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Scope para municipios activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para capitales
     */
    public function scopeCapitals($query)
    {
        return $query->where('is_capital', true);
    }

    /**
     * Scope para buscar por código DANE
     */
    public function scopeByDaneCode($query, $daneCode)
    {
        return $query->where('dane_code', $daneCode);
    }

    /**
     * Accessor para obtener el nombre completo con departamento
     */
    public function getFullNameAttribute(): string
    {
        return $this->name . ', ' . $this->departmentGeo->name;
    }
}