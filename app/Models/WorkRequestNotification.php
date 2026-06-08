<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkRequestNotification extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_request_notifications';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_request_id',
        'user_id',
        'notification_type',
        'title',
        'message',
        'channel',
        'status',
        'sent_at',
        'read_at',
        'error_message',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'sent_at' => 'datetime',
        'read_at' => 'datetime',
    ];

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

    // ===================================
    // SCOPES
    // ===================================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeRead($query)
    {
        return $query->whereNotNull('read_at');
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByChannel($query, $channel)
    {
        return $query->where('channel', $channel);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('notification_type', $type);
    }

    // ===================================
    // ACCESSORS
    // ===================================

    public function getIsReadAttribute(): bool
    {
        return $this->read_at !== null;
    }

    public function getIsSentAttribute(): bool
    {
        return $this->status === 'sent';
    }

    public function getIsFailedAttribute(): bool
    {
        return $this->status === 'failed';
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Mark notification as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /**
     * Mark notification as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(): void
    {
        if (!$this->read_at) {
            $this->update([
                'status' => 'read',
                'read_at' => now(),
            ]);
        }
    }

    /**
     * Create a notification record.
     */
    public static function createNotification(
        int $requestId,
        int $userId,
        string $type,
        string $title,
        string $message,
        string $channel = 'in_app'
    ): self {
        return static::create([
            'work_request_id' => $requestId,
            'user_id' => $userId,
            'notification_type' => $type,
            'title' => $title,
            'message' => $message,
            'channel' => $channel,
            'status' => 'pending',
        ]);
    }
}
