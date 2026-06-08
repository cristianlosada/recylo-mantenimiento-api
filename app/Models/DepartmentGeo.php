<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepartmentGeo extends Model
{
    use HasFactory;

    protected $table = 'departments_geo';

    protected $fillable = [
        'country_id',
        'code',
        'name',
        'iso_code',
        'dane_code',
        'capital_city',
    ];

    /**
     * Relación con país
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Relación con municipios
     */
    public function municipalities(): HasMany
    {
        return $this->hasMany(Municipality::class, 'department_geo_id');
    }

    /**
     * Relación con empresas
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'department_geo_id');
    }

    /**
     * Scope para departamentos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para buscar por código DANE
     */
    public function scopeByDaneCode($query, $daneCode)
    {
        return $query->where('dane_code', $daneCode);
    }

    /**
     * Scope para departamentos de Colombia
     */
    public function scopeColombian($query)
    {
        return $query->whereHas('country', function ($q) {
            $q->where('code', 'CO');
        });
    }
}