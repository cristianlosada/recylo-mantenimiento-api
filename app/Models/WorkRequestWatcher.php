<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkRequestWatcher extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_request_watchers';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_request_id',
        'user_id',
        'added_by',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'watched_at' => 'datetime',
    ];

    public $timestamps = ['watched_at'];
    const CREATED_AT = 'watched_at';
    const UPDATED_AT = null;

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForRequest($query, $requestId)
    {
        return $query->where('work_request_id', $requestId);
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Check if a user is watching a specific work request.
     */
    public static function isWatching(int $requestId, int $userId): bool
    {
        return static::where('work_request_id', $requestId)
            ->where('user_id', $userId)
            ->exists();
    }
}
