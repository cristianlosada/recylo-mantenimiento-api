<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectAttachment extends Model
{
    protected $fillable = [
        'project_id', 'log_id', 'phase_id',
        'attachment_type_id', 'file_path', 'original_name', 'uploaded_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function log(): BelongsTo
    {
        return $this->belongsTo(ProjectLog::class, 'log_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    public function attachmentType(): BelongsTo
    {
        return $this->belongsTo(ProjectAttachmentType::class, 'attachment_type_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
