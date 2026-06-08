<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectLog extends Model
{
    protected $fillable = [
        'project_id', 'phase_id', 'status_id',
        'user_id', 'logged_by', 'log_date', 'hours_worked',
        'activity_description', 'result_description',
        'progress_reported', 'progress_contribution', 'findings', 'deliverables', 'labor_cost',
        'reviewed_by', 'reviewed_at', 'validated_by', 'validated_at',
    ];

    protected $casts = [
        'log_date'               => 'date',
        'hours_worked'           => 'float',
        'progress_reported'      => 'float',
        'progress_contribution'  => 'float',
        'labor_cost'             => 'float',
        'reviewed_at'      => 'datetime',
        'validated_at'     => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectLogStatus::class, 'status_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class, 'log_id');
    }

    public function warehouseUsages(): HasMany
    {
        return $this->hasMany(ProjectWarehouseUsage::class, 'log_id');
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /**
     * Calcula el costo de MO y lo asigna antes de guardar.
     * Solo aplica si el miembro tiene hourly_rate definido.
     */
    public function calculateLaborCost(): void
    {
        $member = ProjectMember::where('project_id', $this->project_id)
            ->where('user_id', $this->user_id)
            ->where('is_active', true)
            ->first();

        if ($member && $member->hourly_rate) {
            $this->labor_cost = round($this->hours_worked * $member->hourly_rate, 2);
        }
    }

    public function scopeByProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByDate($query, string $date)
    {
        return $query->where('log_date', $date);
    }
}
