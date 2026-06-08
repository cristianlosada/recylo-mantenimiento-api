<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkRequestRelated extends Model
{
    use HasFactory;

    protected $table = 'work_request_related';

    protected $fillable = [
        'work_request_id',
        'related_work_request_id',
        'relationship_type',
        'notes',
        'created_by',
    ];

    public $timestamps = ['created_at'];
    const UPDATED_AT = null;

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class, 'work_request_id');
    }

    public function relatedRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class, 'related_work_request_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeByType($query, $type)
    {
        return $query->where('relationship_type', $type);
    }

    public function scopeDuplicates($query)
    {
        return $query->where('relationship_type', 'duplicate');
    }

    public function scopeBlocks($query)
    {
        return $query->where('relationship_type', 'blocks');
    }

    public function scopeRelated($query)
    {
        return $query->where('relationship_type', 'related');
    }

    // ===================================
    // ACCESSORS
    // ===================================

    public function getRelationshipLabelAttribute(): string
    {
        return match($this->relationship_type) {
            'duplicate' => 'Duplicado de',
            'related' => 'Relacionado con',
            'blocks' => 'Bloquea a',
            'caused_by' => 'Causado por',
            'parent' => 'Padre de',
            'child' => 'Hijo de',
            default => $this->relationship_type,
        };
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Link two work requests with a relationship.
     */
    public static function link(
        int $requestId,
        int $relatedRequestId,
        string $relationshipType,
        int $userId,
        ?string $notes = null
    ): self {
        return static::create([
            'work_request_id' => $requestId,
            'related_work_request_id' => $relatedRequestId,
            'relationship_type' => $relationshipType,
            'created_by' => $userId,
            'notes' => $notes,
        ]);
    }

    /**
     * Check if two requests are already related.
     */
    public static function areRelated(int $requestId, int $relatedRequestId): bool
    {
        return static::where(function ($query) use ($requestId, $relatedRequestId) {
            $query->where('work_request_id', $requestId)
                  ->where('related_work_request_id', $relatedRequestId);
        })->orWhere(function ($query) use ($requestId, $relatedRequestId) {
            $query->where('work_request_id', $relatedRequestId)
                  ->where('related_work_request_id', $requestId);
        })->exists();
    }
}
