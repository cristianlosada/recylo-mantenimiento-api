<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'description',
        'type',
        'is_public'
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Scope para configuraciones públicas
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope para configuraciones privadas
     */
    public function scopePrivate($query)
    {
        return $query->where('is_public', false);
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
     * Obtener configuración del sistema con cache
     */
    public static function get(string $key, $default = null)
    {
        return Cache::remember("system_setting_{$key}", 3600, function () use ($key, $default) {
            $setting = static::byKey($key)->first();
            return $setting ? $setting->typed_value : $default;
        });
    }

    /**
     * Establecer configuración del sistema
     */
    public static function set(string $key, $value, string $description = null, string $type = 'string'): SystemSetting
    {
        // Convertir valor según el tipo
        $formattedValue = $value;
        if ($type === 'json' && (is_array($value) || is_object($value))) {
            $formattedValue = json_encode($value);
        } elseif ($type === 'boolean') {
            $formattedValue = $value ? 'true' : 'false';
        }

        $setting = static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $formattedValue,
                'description' => $description,
                'type' => $type,
                'is_public' => false
            ]
        );

        // Limpiar cache
        Cache::forget("system_setting_{$key}");

        return $setting;
    }

    /**
     * Verificar si una configuración existe
     */
    public static function has(string $key): bool
    {
        return static::byKey($key)->exists();
    }

    /**
     * Eliminar configuración
     */
    public static function forget(string $key): bool
    {
        $deleted = static::byKey($key)->delete();
        Cache::forget("system_setting_{$key}");
        return $deleted > 0;
    }

    /**
     * Obtener todas las configuraciones públicas
     */
    public static function getPublicSettings(): array
    {
        return static::public()
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
            Cache::forget("system_setting_{$setting->key}");
        });

        static::deleted(function ($setting) {
            Cache::forget("system_setting_{$setting->key}");
        });
    }
}