<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkRequestChecklistItem extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_request_checklist_items';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_request_id',
        'template_id',
        'item_text',
        'is_required',
        'is_checked',
        'checked_by',
        'checked_at',
        'notes',
        'display_order',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'is_required' => 'boolean',
        'is_checked' => 'boolean',
        'checked_at' => 'datetime',
        'display_order' => 'integer',
    ];

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkRequestChecklistTemplate::class, 'template_id');
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeChecked($query)
    {
        return $query->where('is_checked', true);
    }

    public function scopeUnchecked($query)
    {
        return $query->where('is_checked', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Mark item as checked.
     */
    public function check(int $userId, ?string $notes = null): void
    {
        $this->update([
            'is_checked' => true,
            'checked_by' => $userId,
            'checked_at' => now(),
            'notes' => $notes,
        ]);
    }

    /**
     * Mark item as unchecked.
     */
    public function uncheck(): void
    {
        $this->update([
            'is_checked' => false,
            'checked_by' => null,
            'checked_at' => null,
            'notes' => null,
        ]);
    }
}
