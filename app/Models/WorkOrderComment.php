<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrderComment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_order_comments';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_order_id',
        'user_id',
        'parent_id',
        'comment',
        'is_internal',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'is_internal' => 'boolean',
    ];

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorkOrderComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(WorkOrderComment::class, 'parent_id');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeForWorkOrder($query, $workOrderId)
    {
        return $query->where('work_order_id', $workOrderId);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ===================================
    // ACCESSORS
    // ===================================

    public function getIsReplyAttribute(): bool
    {
        return $this->parent_id !== null;
    }

    public function getHasRepliesAttribute(): bool
    {
        return $this->replies()->exists();
    }
}
