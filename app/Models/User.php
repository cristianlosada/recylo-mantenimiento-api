<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\Auditable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes, Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'second_last_name',
        'email',
        'password',
        'document_type_id',
        'document_number',
        'birth_date',
        'gender',
        'nationality_country_id',
        'status',
        'email_verified_at',
        'last_login_at',
        'password_changed_at',
        'mfa_enabled',
        'mfa_secret',
        'failed_login_attempts',
        'locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Relación con tipo de documento
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Relación con empresas del usuario
     */
    public function userCompanies(): HasMany
    {
        return $this->hasMany(UserCompany::class);
    }

    /**
     * Relación con empresas a través de la tabla pivote
     */
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'user_companies')
                    ->withPivot([
                        'status', 
                        'employee_code', 
                        'hire_date', 
                        'termination_date', 
                        'termination_reason',
                        'salary_amount',
                        'salary_currency',
                        'hourly_rate',
                        'is_primary',
                        'site_id',
                        'production_line_id',
                        'department',
                        'job_position',
                        'job_position_id',
                        'employment_type',
                        'direct_supervisor_id'
                    ])
                    ->withTimestamps();
    }

    /**
     * Relación con contactos del usuario
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(UserContact::class);
    }

    /**
     * Relación con roles del usuario
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    /**
     * Relación con roles a través de la tabla pivote
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot(['company_id', 'assigned_at', 'expires_at'])
                    ->withTimestamps();
    }

    /**
     * Relación con delegaciones de roles creadas
     */
    public function createdDelegations(): HasMany
    {
        return $this->hasMany(RoleDelegation::class, 'delegator_user_id');
    }

    /**
     * Relación con delegaciones de roles recibidas
     */
    public function receivedDelegations(): HasMany
    {
        return $this->hasMany(RoleDelegation::class, 'delegatee_user_id');
    }

    /**
     * Relación con delegaciones activas (vigentes)
     */
    public function activeDelegations(): HasMany
    {
        return $this->hasMany(RoleDelegation::class, 'delegatee_user_id')
                    ->where('delegated_at', '<=', now())
                    ->where(function ($query) {
                        $query->whereNull('expires_at')
                              ->orWhere('expires_at', '>=', now());
                    })
                    ->whereNull('revoked_at');
    }

    /**
     * Relación con logs de auditoría
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Relación con sesiones
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    /**
     * Scope para usuarios activos
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope para buscar por documento
     */
    public function scopeByDocument($query, $documentNumber)
    {
        return $query->where('document_number', $documentNumber);
    }

    /**
     * Scope para buscar por email
     */
    public function scopeByEmail($query, $email)
    {
        return $query->where('email', $email);
    }

    /**
     * Accessor para nombre completo
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Accessor para iniciales
     */
    public function getInitialsAttribute(): string
    {
        $firstInitial = $this->first_name ? substr($this->first_name, 0, 1) : '';
        $lastInitial = $this->last_name ? substr($this->last_name, 0, 1) : '';
        return strtoupper($firstInitial . $lastInitial);
    }

    /**
     * Accessor para teléfono principal
     */
    public function getPhoneAttribute(): ?string
    {
        $primaryContact = $this->contacts()
            ->where('contact_type_id', function($query) {
                $query->select('id')
                    ->from('contact_types')
                    ->where('code', 'phone')
                    ->limit(1);
            })
            ->where('is_primary', true)
            ->first();

        return $primaryContact?->value;
    }

    /**
     * Accessor para obtener información del tipo de documento
     */
    public function getDocumentTypeInfoAttribute(): ?array
    {
        if (!$this->documentType) {
            return null;
        }

        return [
            'id' => $this->documentType->id,
            'name' => $this->documentType->name,
            'code' => $this->documentType->code
        ];
    }

    /**
     * Verificar si el usuario tiene un rol específico en una empresa
     */
    public function hasRole(string $roleCode, ?int $companyId = null): bool
    {
        return $this->roles()
                    ->where('code', $roleCode)
                    ->when($companyId, function ($query) use ($companyId) {
                        $query->wherePivot('company_id', $companyId);
                    })
                    ->exists();
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission(string $permissionCode, ?int $companyId = null): bool
    {
        return $this->roles()
                    ->whereHas('permissions', function ($query) use ($permissionCode) {
                        $query->where('code', $permissionCode);
                    })
                    ->when($companyId, function ($query) use ($companyId) {
                        $query->wherePivot('company_id', $companyId);
                    })
                    ->exists();
    }

    /**
     * Consultar los modulos asociados al usuario
     */
    public function modules(?int $companyId = null)
    {
        // Obtener los IDs de roles del usuario (filtrados por empresa si se especifica)
        $roleIds = $this->userRoles()
            ->when($companyId, function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->pluck('role_id')
            ->toArray();

        if (empty($roleIds)) {
            return collect([]);
        }

        // Obtener módulos que tienen permisos asociados a esos roles
        $modules = Module::whereHas('permissions', function ($query) use ($roleIds) {
            $query->whereHas('roles', function ($q) use ($roleIds) {
                $q->whereIn('roles.id', $roleIds);
            });
        })->distinct()->get();
        
        return $modules;
    }
}
