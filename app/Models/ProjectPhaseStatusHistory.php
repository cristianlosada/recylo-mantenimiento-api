<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPhaseStatusHistory extends Model
{
    protected $fillable = [
        'phase_id', 'type', 'from_status_id', 'to_status_id', 'changed_by', 'notes', 'changes', 'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
        'changes'    => 'array',
    ];

    public function phase(): BelongsTo    { return $this->belongsTo(ProjectPhase::class, 'phase_id'); }
    public function fromStatus(): BelongsTo { return $this->belongsTo(ProjectPhaseStatus::class, 'from_status_id'); }
    public function toStatus(): BelongsTo   { return $this->belongsTo(ProjectPhaseStatus::class, 'to_status_id'); }
    public function changedBy(): BelongsTo  { return $this->belongsTo(User::class, 'changed_by'); }
}
