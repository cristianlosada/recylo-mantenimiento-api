<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkRequestStatusHistory extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_request_status_history';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_request_id',
        'from_status',
        'to_status',
        'reason',
        'changed_by',
        'metadata',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'changed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public $timestamps = ['changed_at'];
    const CREATED_AT = 'changed_at';
    const UPDATED_AT = null;

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeRecent($query)
    {
        return $query->orderBy('changed_at', 'desc');
    }

    public function scopeForRequest($query, $requestId)
    {
        return $query->where('work_request_id', $requestId);
    }

    public function scopeToStatus($query, $status)
    {
        return $query->where('to_status', $status);
    }

    public function scopeFromStatus($query, $status)
    {
        return $query->where('from_status', $status);
    }

    // ===================================
    // ACCESSORS
    // ===================================

    public function getFromStatusLabelAttribute(): string
    {
        return $this->getStatusLabel($this->from_status);
    }

    public function getToStatusLabelAttribute(): string
    {
        return $this->getStatusLabel($this->to_status);
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(?string $status): string
    {
        return match($status) {
            'pending' => 'Pendiente',
            'under_review' => 'En Revisión',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
            'completed' => 'Completada',
            default => $status ?? 'Desconocido',
        };
    }

    /**
     * Record a status change.
     */
    public static function record(int $requestId, ?string $fromStatus, string $toStatus, ?int $userId, ?string $reason = null, ?array $metadata = null): self
    {
        return static::create([
            'work_request_id' => $requestId,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by' => $userId,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
