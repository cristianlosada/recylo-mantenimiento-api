<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Project extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'company_id', 'code', 'name', 'project_type_id', 'status_id',
        'description', 'objective', 'justification',
        'leader_id', 'area_id',
        'planned_start_date', 'planned_end_date',
        'actual_start_date', 'actual_end_date',
        'budget', 'actual_cost', 'progress_percent',
        'closure_notes', 'lessons_learned',
        'approved_by', 'approved_at',
        'closed_by', 'closed_at',
        'cancelled_by', 'cancelled_at',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date'   => 'date',
        'actual_start_date'  => 'date',
        'actual_end_date'    => 'date',
        'approved_at'        => 'datetime',
        'closed_at'          => 'datetime',
        'cancelled_at'       => 'datetime',
        'budget'             => 'float',
        'actual_cost'        => 'float',
        'progress_percent'   => 'float',
    ];

    protected $hidden = ['deleted_at'];

    // -------------------------------------------------------------------------
    // RELACIONES
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(ProjectType::class, 'project_type_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectStatus::class, 'status_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'area_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function phases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class)->orderBy('order_index');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class)->where('is_active', true);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectLog::class)->orderBy('log_date', 'desc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    public function warehouseUsages(): HasMany
    {
        return $this->hasMany(ProjectWarehouseUsage::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class)->orderBy('created_at', 'desc');
    }

    // -------------------------------------------------------------------------
    // SCOPES
    // -------------------------------------------------------------------------

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeActive($query)
    {
        return $query->whereHas('status', fn($q) => $q->where('is_terminal', false));
    }

    public function scopeInProgress($query)
    {
        return $query->whereHas('status', fn($q) => $q->where('code', 'in_progress'));
    }

    public function scopeOverdue($query)
    {
        return $query->whereHas('status', fn($q) => $q->where('is_terminal', false))
                     ->where('planned_end_date', '<', now()->toDateString());
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Recalcula el avance del proyecto como promedio ponderado de sus fases.
     * Solo aplica si company_setting projects.auto_calculate_progress = true.
     */
    public function recalculateProgress(): void
    {
        $phases = $this->phases()->get();

        if ($phases->isEmpty()) {
            return;
        }

        $totalWeight = $phases->sum('weight_percent');

        if ($totalWeight > 0) {
            $progress = $phases->sum(fn($p) => ($p->weight_percent / $totalWeight) * $p->progress_percent);
        } else {
            // Sin pesos definidos → promedio simple entre fases
            $progress = $phases->avg('progress_percent') ?? 0;
        }

        $this->update(['progress_percent' => round($progress, 2)]);
    }

    /**
     * Recalcula el costo real: mano de obra (bitácora) + materiales/recursos por fase.
     */
    public function recalculateCost(): void
    {
        $laborCost = $this->logs()->sum('labor_cost');

        $resourceCost = \App\Models\ProjectPhaseResource::whereHas('phase', fn($q) => $q->where('project_id', $this->id))
            ->whereNotNull('actual_cost')
            ->sum('actual_cost');

        $warehouseCost = \App\Models\ProjectWarehouseUsage::where('project_id', $this->id)
            ->join('inventory_transactions', 'inventory_transactions.id', '=', 'project_warehouse_usages.inventory_transaction_id')
            ->sum('inventory_transactions.total_cost');

        $this->update(['actual_cost' => $laborCost + $resourceCost + $warehouseCost]);
    }

    /**
     * Genera el código del proyecto según el tipo: PREFIX-YYYY-NNN
     */
    public static function generateCode(int $companyId, string $typeCode): string
    {
        $type   = ProjectType::where('code', $typeCode)->first();
        $prefix = $type?->code_prefix ?? 'PROY';
        $year   = now()->year;

        $last = static::where('company_id', $companyId)
            ->where('code', 'like', "{$prefix}-{$year}-%")
            ->withTrashed()
            ->count();

        $sequential = str_pad($last + 1, 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$sequential}";
    }
}
