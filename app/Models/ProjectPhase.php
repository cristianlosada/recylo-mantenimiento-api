<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectPhase extends Model
{
    protected $fillable = [
        'project_id', 'status_id', 'name', 'description',
        'order_index', 'planned_start_date', 'planned_end_date',
        'actual_start_date', 'actual_end_date',
        'weight_percent', 'progress_percent', 'responsible_id',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date'   => 'date',
        'actual_start_date'  => 'date',
        'actual_end_date'    => 'date',
        'weight_percent'     => 'float',
        'progress_percent'   => 'float',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ProjectPhaseStatus::class, 'status_id');
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function technicians(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_phase_technicians', 'phase_id', 'user_id')
            ->withPivot('assigned_by', 'assigned_at')
            ->withTimestamps();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ProjectLog::class, 'phase_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ProjectPhaseStatusHistory::class, 'phase_id')->orderByDesc('changed_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class, 'phase_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(ProjectPhaseResource::class, 'phase_id');
    }

    public function warehouseUsages(): HasMany
    {
        return $this->hasMany(ProjectWarehouseUsage::class, 'phase_id');
    }

    public function estimatedCost(): float
    {
        return (float) $this->resources()->sum('estimated_cost');
    }

    public function actualResourceCost(): float
    {
        return (float) $this->resources()->sum('actual_cost');
    }

    /**
     * Recalcula el avance de la fase como suma acumulada de contributions,
     * capada al 100%. Actualiza progress_contribution en cada log y
     * dispara auto-transiciones de estado.
     *
     * @return array{progress: float, capped: bool, cap_at: float|null}
     */
    public function recalculateProgressFromLogs(): array
    {
        $logs = $this->logs()
            ->whereNotNull('progress_reported')
            ->orderBy('log_date')
            ->orderBy('id')
            ->get(['id', 'progress_reported']);

        $accumulated = 0.0;
        $cappedAt    = null;

        foreach ($logs as $log) {
            $remaining    = 100.0 - $accumulated;
            $contribution = min((float) $log->progress_reported, $remaining);
            $log->updateQuietly(['progress_contribution' => $contribution]);
            $accumulated  = min(100.0, $accumulated + $contribution);
            if ((float) $log->progress_reported > $remaining && $cappedAt === null) {
                $cappedAt = $remaining;
            }
        }

        $newProgress = round($accumulated, 2);
        $this->autoTransitionByProgress($newProgress);
        $this->update(['progress_percent' => $newProgress]);

        return [
            'progress' => $newProgress,
            'capped'   => $cappedAt !== null,
            'cap_at'   => $cappedAt,
        ];
    }

    /**
     * Ejecuta transiciones automáticas de estado basadas en el avance calculado.
     * Respeta 'on_hold': no lo revierte automáticamente a in_progress excepto
     * cuando el avance llega al 100%.
     */
    private function autoTransitionByProgress(float $newProgress): void
    {
        $this->loadMissing('status');
        $currentCode = $this->status?->code ?? 'pending';

        $targetCode = match(true) {
            $newProgress >= 100 && $currentCode !== 'completed'              => 'completed',
            $newProgress < 100  && $currentCode === 'completed'              => 'in_progress',
            $newProgress > 0    && $newProgress < 100 && $currentCode === 'pending' => 'in_progress',
            default => null,
        };

        if (!$targetCode) return;

        $newStatus = ProjectPhaseStatus::where('code', $targetCode)->first();
        if (!$newStatus) return;

        $fromStatusId = $this->status_id;
        $updateData   = ['status_id' => $newStatus->id];

        if ($targetCode === 'in_progress' && !$this->actual_start_date) {
            $updateData['actual_start_date'] = now()->toDateString();
        }
        if ($targetCode === 'completed' && !$this->actual_end_date) {
            $updateData['actual_end_date'] = now()->toDateString();
        }

        $this->update($updateData);
        $this->refresh();

        ProjectPhaseStatusHistory::create([
            'phase_id'       => $this->id,
            'type'           => 'status_changed',
            'from_status_id' => $fromStatusId,
            'to_status_id'   => $newStatus->id,
            'changed_by'     => auth()->id() ?? $this->responsible_id,
            'notes'          => 'Cambio automático por avance de bitácoras',
            'changed_at'     => now(),
        ]);
    }
}
