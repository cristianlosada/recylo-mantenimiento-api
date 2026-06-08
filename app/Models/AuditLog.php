<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'audit_action_id',
        'user_id',
        'company_id',
        'entity_type',
        'entity_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'additional_info'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'additional_info' => 'array',
    ];

    /**
     * Relación con acción de auditoría
     */
    public function auditAction(): BelongsTo
    {
        return $this->belongsTo(AuditAction::class);
    }

    /**
     * Relación con usuario que ejecutó la acción
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con empresa (contexto)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope para usuario específico
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope para empresa específica
     */
    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para entidad específica
     */
    public function scopeForEntity($query, $entityType, $entityId)
    {
        return $query->where('entity_type', $entityType)
                    ->where('entity_id', $entityId);
    }

    /**
     * Scope para rango de fechas
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Obtener descripción del cambio
     */
    public function getChangeDescriptionAttribute(): string
    {
        $action = $this->auditAction->description;
        $entity = class_basename($this->entity_type);
        
        return "{$action} en {$entity} #{$this->entity_id}";
    }
}