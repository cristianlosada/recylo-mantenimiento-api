<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserContact extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_type_id',
        'value',
        'is_primary',
        'is_verified',
        'verified_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * Relación con usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con tipo de contacto
     */
    public function contactType(): BelongsTo
    {
        return $this->belongsTo(ContactType::class);
    }

    /**
     * Scope para contactos primarios
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope para tipo específico de contacto
     */
    public function scopeByType($query, $contactTypeCode)
    {
        return $query->whereHas('contactType', function ($q) use ($contactTypeCode) {
            $q->where('code', $contactTypeCode);
        });
    }

    /**
     * Scope para usuario específico
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Validar formato del contacto según su tipo
     */
    public function isValid(): bool
    {
        return $this->contactType->validateContact($this->value);
    }
}