<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'key',
        'value',
        'description',
        'type'
    ];

    /**
     * Relación con empresa
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope para empresa específica
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para buscar por clave
     */
    public function scopeByKey($query, $key)
    {
        return $query->where('key', $key);
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Obtener valor tipado según el tipo de configuración
     */
    public function getTypedValueAttribute()
    {
        switch ($this->type) {
            case 'boolean':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            case 'number':
                return is_numeric($this->value) ? (float) $this->value : 0;
            case 'json':
                return json_decode($this->value, true);
            default:
                return $this->value;
        }
    }

    /**
     * Obtener configuración de empresa con cache
     */
    public static function get(int $companyId, string $key, $default = null)
    {
        $cacheKey = "company_setting_{$companyId}_{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($companyId, $key, $default) {
            $setting = static::forCompany($companyId)->byKey($key)->first();
            return $setting ? $setting->typed_value : $default;
        });
    }

    /**
     * Establecer configuración de empresa
     */
    public static function set(int $companyId, string $key, $value, string $description = null, string $type = 'string'): CompanySetting
    {
        // Convertir valor según el tipo
        $formattedValue = $value;
        if ($type === 'json' && (is_array($value) || is_object($value))) {
            $formattedValue = json_encode($value);
        } elseif ($type === 'boolean') {
            $formattedValue = $value ? 'true' : 'false';
        }

        $setting = static::updateOrCreate(
            [
                'company_id' => $companyId,
                'key' => $key
            ],
            [
                'value' => $formattedValue,
                'description' => $description,
                'type' => $type
            ]
        );

        // Limpiar cache
        Cache::forget("company_setting_{$companyId}_{$key}");

        return $setting;
    }

    /**
     * Verificar si una configuración existe para la empresa
     */
    public static function has(int $companyId, string $key): bool
    {
        return static::forCompany($companyId)->byKey($key)->exists();
    }

    /**
     * Eliminar configuración de empresa
     */
    public static function forget(int $companyId, string $key): bool
    {
        $deleted = static::forCompany($companyId)->byKey($key)->delete();
        Cache::forget("company_setting_{$companyId}_{$key}");
        return $deleted > 0;
    }

    /**
     * Obtener todas las configuraciones de una empresa
     */
    public static function getAllForCompany(int $companyId): array
    {
        return static::forCompany($companyId)
                    ->get()
                    ->pluck('typed_value', 'key')
                    ->toArray();
    }

    /**
     * Limpiar cache al guardar
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($setting) {
            $cacheKey = "company_setting_{$setting->company_id}_{$setting->key}";
            Cache::forget($cacheKey);
        });

        static::deleted(function ($setting) {
            $cacheKey = "company_setting_{$setting->company_id}_{$setting->key}";
            Cache::forget($cacheKey);
        });
    }
}