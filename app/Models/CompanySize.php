<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanySize extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'min_employees',
        'max_employees',
        'is_active',
    ];

    protected $casts = [
        'min_employees' => 'integer',
        'max_employees' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con empresas
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Scope para tamaños activos
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
     * Determinar el tamaño de empresa según número de empleados
     */
    public static function getByEmployeeCount(int $employeeCount): ?CompanySize
    {
        return static::where('is_active', true)
                    ->where('min_employees', '<=', $employeeCount)
                    ->where(function ($query) use ($employeeCount) {
                        $query->where('max_employees', '>=', $employeeCount)
                              ->orWhereNull('max_employees');
                    })
                    ->first();
    }
}