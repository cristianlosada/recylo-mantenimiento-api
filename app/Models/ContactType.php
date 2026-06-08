<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'validation_pattern',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relación con contactos de usuario
     */
    public function userContacts(): HasMany
    {
        return $this->hasMany(UserContact::class);
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
     * Validar formato de contacto
     */
    public function validateContact(string $contact): bool
    {
        if (empty($this->validation_pattern)) {
            return true;
        }

        return preg_match($this->validation_pattern, $contact) === 1;
    }
}