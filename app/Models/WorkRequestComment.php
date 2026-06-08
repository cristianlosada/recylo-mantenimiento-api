<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkRequestComment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_request_comments';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_request_id',
        'user_id',
        'parent_id',
        'comment',
        'is_internal',
    ];

    /**
     * Campos ocultos en serialización JSON
     */
    protected $hidden = [
        'deleted_at',
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

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorkRequestComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(WorkRequestComment::class, 'parent_id');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
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
