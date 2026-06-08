<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Asset extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'assets';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'company_id',
        'company_site_id',
        'production_line_id',  // HU-A1
        'system_id',
        'parent_id',
        'category_id',
        'status_id',
        'priority_id',
        'brand',
        'model',
        'serial_number',
        'manufacturer_id',     // HU-A5
        'supplier_id',         // HU-A5
        'capacity',
        'capacity_unit',
        'manufacturing_year',
        'materials_used',
        'location_path',
        'location_details',
        'latitude',
        'longitude',
        'purchase_cost',
        'currency_id',
        'purchase_date',
        'installation_date',   // HU-A5
        'end_of_life_date',    // HU-A5
        'cost_center',
        'qr_code',
        'image_path',
        'is_active',
        'created_by',
        'updated_by'
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
        'materials_used' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'purchase_cost' => 'float',
        'capacity' => 'float',
        'manufacturing_year' => 'integer',
        'is_active' => 'boolean',
        'purchase_date' => 'date',
        'installation_date' => 'date',   // HU-A5
        'end_of_life_date' => 'date',    // HU-A5
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
     * Relación con sede (muchos a uno)
     */
    public function companySite(): BelongsTo
    {
        return $this->belongsTo(CompanySite::class);
    }

    /**
     * Relación con línea de producción / área (HU-A1)
     */
    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class);
    }

    /**
     * Relación con sistema funcional del activo
     */
    public function system(): BelongsTo
    {
        return $this->belongsTo(AssetSystem::class, 'system_id');
    }

    /**
     * Relación con fabricante del equipo (HU-A5)
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(AssetVendor::class, 'manufacturer_id');
    }

    /**
     * Relación con proveedor del equipo (HU-A5)
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(AssetVendor::class, 'supplier_id');
    }

    /**
     * Tipos de mantenimiento del activo — multiselección (HU-A4)
     */
    public function maintenanceTypes(): BelongsToMany
    {
        return $this->belongsToMany(MaintenanceType::class, 'asset_maintenance_types')
            ->withPivot('order_index')
            ->withTimestamps()
            ->orderBy('asset_maintenance_types.order_index');
    }

    /**
     * Relación con activo padre (muchos a uno)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_id');
    }

    /**
     * Relación con activos hijos (uno a muchos)
     */
    public function children(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_id');
    }

    /**
     * Relación con categoría (muchos a uno)
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class);
    }

    /**
     * Relación con estado (muchos a uno)
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class);
    }

    /**
     * Relación con prioridad (muchos a uno)
     */
    public function priority(): BelongsTo
    {
        return $this->belongsTo(AssetPriority::class);
    }

    /**
     * Relación con moneda (muchos a uno)
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Relación con especificaciones técnicas (uno a muchos)
     */
    public function specifications(): HasMany
    {
        return $this->hasMany(AssetSpecification::class);
    }

    /**
     * Relación con usuarios asignados (muchos a muchos)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'asset_users')
            ->withPivot('role', 'assigned_at', 'assigned_by')
            ->withTimestamps();
    }

    /**
     * Relación con notas del activo (uno a muchos)
     */
    public function notes(): HasMany
    {
        return $this->hasMany(AssetNote::class);
    }

    /**
     * Relación con notificaciones del activo (uno a muchos)
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(AssetNotification::class);
    }

    /**
     * Componentes instalados/especificados en el activo.
     */
    public function assetComponents(): HasMany
    {
        return $this->hasMany(AssetComponent::class);
    }

    /**
     * Historial de consumo de componentes en este activo.
     */
    public function componentConsumptions(): HasMany
    {
        return $this->hasMany(AssetComponentConsumption::class);
    }

    /**
     * Relación con repuestos del activo (uno a muchos)
     */
    public function spareParts(): HasMany
    {
        return $this->hasMany(AssetSparePart::class);
    }

    /**
     * Relación con archivos adjuntos del activo (uno a muchos)
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AssetAttachment::class);
    }

    /**
     * Relación con mediciones del activo (uno a muchos)
     */
    public function measurements(): HasMany
    {
        return $this->hasMany(AssetMeasurement::class);
    }

    /**
     * Relación con historial de actividad del activo (uno a muchos)
     */
    public function activityLog(): HasMany
    {
        return $this->hasMany(AssetActivityLog::class)->orderBy('performed_at', 'desc');
    }

    /**
     * Relación con medidores/contadores del activo (uno a muchos)
     */
    public function meters(): HasMany
    {
        return $this->hasMany(AssetMeter::class);
    }

    /**
     * Relación con planes de mantenimiento del activo (uno a muchos)
     */
    public function maintenancePlans(): HasMany
    {
        return $this->hasMany(MaintenancePlan::class);
    }

    /**
     * Obtener la lectura actual de un medidor específico
     * 
     * @param string $meterType Tipo: hours, kilometers, cycles, units_produced
     * @return float|null
     */

    /**
     * Plantillas de inspeccion asignadas a este activo (muchos a muchos)
     */
    public function inspectionTemplates(): BelongsToMany
    {
        return $this->belongsToMany(InspectionTemplate::class, 'inspection_template_assets', 'asset_id', 'template_id')
            ->withTimestamps();
    }
    public function getCurrentMeterReading(string $meterType): ?float
    {
        $meter = $this->meters()->where('meter_type', $meterType)->where('is_active', true)->first();
        return $meter ? $meter->current_reading : null;
    }

    /**
     * Relación con usuario creador (muchos a uno)
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relación con usuario que actualizó (muchos a uno)
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * ===============================================
     * SCOPES
     * ===============================================
     */

    /**
     * Scope para filtrar solo activos activos
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope para filtrar por compañía
     */
    public function scopeByCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    /**
     * Scope para filtrar por sede
     */
    public function scopeBySite($query, $siteId)
    {
        return $query->where('company_site_id', $siteId);
    }

    /**
     * Scope para filtrar por categoría
     */
    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope para filtrar por estado
     */
    public function scopeByStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope para obtener solo activos raíz (sin padre)
     */
    public function scopeRootAssets($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope para cargar jerarquía completa
     */
    public function scopeWithFullHierarchy($query)
    {
        return $query->with(['children' => function ($q) {
            $q->with('children');
        }]);
    }

    /**
     * ===============================================
     * ACCESSORS
     * ===============================================
     */

    /**
     * Accessor para obtener la ruta completa
     */
    public function getFullPathAttribute(): string
    {
        return $this->location_path ?? '';
    }

    /**
     * Accessor para verificar si tiene coordenadas GPS
     */
    public function getHasCoordinatesAttribute(): bool
    {
        return !is_null($this->latitude) && !is_null($this->longitude);
    }

    /**
     * Accessor para obtener materiales como array
     */
    public function getMaterialsUsedArrayAttribute(): array
    {
        return $this->materials_used ?? [];
    }

    /**
     * Accessor para obtener especificaciones como array asociativo
     */
    public function getSpecificationsArrayAttribute(): array
    {
        return $this->specifications->pluck('spec_value', 'spec_key')->toArray();
    }

    /**
     * Accessor para obtener el nivel jerárquico
     */
    public function getHierarchyLevelAttribute(): int
    {
        $level = 0;
        $parent = $this->parent;
        
        while ($parent) {
            $level++;
            $parent = $parent->parent;
        }
        
        return $level;
    }
}
