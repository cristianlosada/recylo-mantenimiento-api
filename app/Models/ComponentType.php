<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComponentType extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'component_types';

    protected $fillable = [
        'company_id',
        'code_prefix',
        'name',
        'description',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function components(): HasMany
    {
        return $this->hasMany(Component::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Genera el siguiente código disponible para este tipo.
     * Ej: ROD → ROD-001, ROD-002...
     */
    public function generateNextComponentCode(): string
    {
        $prefix = $this->code_prefix;
        $companyId = $this->company_id;

        $last = Component::withTrashed()
            ->where('company_id', $companyId)
            ->where('component_type_id', $this->id)
            ->orderByRaw('CAST(SUBSTRING(code, ' . (strlen($prefix) + 2) . ') AS UNSIGNED) DESC')
            ->first();

        $nextNumber = $last
            ? ((int) substr($last->code, strlen($prefix) + 1)) + 1
            : 1;

        // Asegurar unicidad
        $maxAttempts = 100;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = sprintf('%s-%03d', $prefix, $nextNumber);
            if (!Component::withTrashed()->where('company_id', $companyId)->where('code', $code)->exists()) {
                return $code;
            }
            $nextNumber++;
        }

        throw new \RuntimeException("No se pudo generar un código único para el tipo {$prefix}");
    }
}
