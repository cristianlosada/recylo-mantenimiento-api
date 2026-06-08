<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\JobPosition;

class UserCompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'company_id',
        'employee_code',
        'site_id',
        'production_line_id',
        'department',
        'job_position',
        'job_position_id',
        'employment_type',
        'direct_supervisor_id',
        'hire_date',
        'termination_date',
        'termination_reason',
        'salary_amount',
        'salary_currency',
        'hourly_rate',
        'is_primary',
        'status',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'termination_date' => 'date',
        'is_primary' => 'boolean',
        'salary_amount' => 'decimal:2',
        'hourly_rate'   => 'decimal:2',
    ];

    /**
     * Relación con usuario
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con empresa
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Relación con sitio de la empresa
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(CompanySite::class, 'site_id');
    }

    /**
     * Relación con línea de producción (centro de costo)
     */
    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }

    /**
     * Relación con supervisor directo
     */
    public function directSupervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direct_supervisor_id');
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    /**
     * Scope para relaciones activas
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
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
     * Verificar si la relación está activa
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && is_null($this->termination_date);
    }

    /**
     * Verificar si es la empresa principal del usuario
     */
    public function isPrimary(): bool
    {
        return $this->is_primary === true;
    }

    /**
     * Obtener el salario formateado
     */
    public function getFormattedSalaryAttribute(): ?string
    {
        if (!$this->salary_amount) {
            return null;
        }

        $currency = $this->salary_currency ?? 'COP';
        return number_format($this->salary_amount, 2) . ' ' . $currency;
    }

    /**
     * Scope para empleados activos
     */
    public function scopeActiveEmployees($query)
    {
        return $query->where('status', 'active')
                    ->whereNull('termination_date');
    }

    /**
     * Scope para empresa principal
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }
}