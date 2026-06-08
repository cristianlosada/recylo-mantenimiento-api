<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'code',
        'name',
        'validation_pattern',
        'max_length',
        'is_active',
    ];

    protected $casts = [
        'max_length' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relación con país
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Relación con documentos de usuario
     */
    public function userDocuments(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    /**
     * Relación con documentos de empresa
     */
    public function companyDocuments(): HasMany
    {
        return $this->hasMany(CompanyDocument::class);
    }

    /**
     * Scope para tipos activos
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
     * Validar formato de documento
     */
    public function validateDocument(string $document): bool
    {
        if (empty($this->validation_pattern)) {
            return true;
        }

        return preg_match($this->validation_pattern, $document) === 1;
    }
}