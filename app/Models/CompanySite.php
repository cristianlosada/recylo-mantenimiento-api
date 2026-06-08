<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class CompanySite extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'company_sites';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'company_id',
        'site_type_id',
        'name',
        'municipality_id',
        'address_line_1',
        'address_line_2',
        'postal_code',
        'latitude',
        'longitude',
        'is_headquarters',
        'is_active'
    ];

    /**
     * Campos ocultos en serialización JSON
     */
    protected $hidden = [
        'deleted_at'
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_headquarters' => 'boolean',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * ===============================================
     * RELACIONES ELOQUENT
     * ===============================================
     */

    /**
     * Relación con compañía (muchos a uno)
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación con tipo de sede (muchos a uno)
     */
    public function siteType(): BelongsTo
    {
        return $this->belongsTo(SiteType::class);
    }

    /**
     * Relación con municipio (muchos a uno)
     */
    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    /**
     * ===============================================
     * QUERY SCOPES
     * ===============================================
     */

    /**
     * Scope para filtrar sedes activas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar sede principal
     */
    public function scopeHeadquarters($query)
    {
        return $query->where('is_headquarters', true);
    }

    /**
     * Scope para filtrar por empresa específica
     */
    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * ===============================================
     * ACCESSORS & MUTATORS
     * ===============================================
     */

    /**
     * Accessor: Dirección completa formateada
     */
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->address_line_1,
            $this->address_line_2,
            $this->municipality?->name,
            $this->postal_code
        ]);

        return implode(', ', $parts);
    }

    /**
     * Accessor: Nombre completo con tipo de sede
     */
    public function getDisplayNameAttribute(): string
    {
        $typeName = $this->siteType?->name ?? 'Sede';
        return "{$this->name} ({$typeName})";
    }

    /**
     * Accessor: Indica si tiene coordenadas GPS
     */
    public function getHasCoordinatesAttribute(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }
}
