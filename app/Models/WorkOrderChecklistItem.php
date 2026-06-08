<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderChecklistItem extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_order_checklist_items';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_order_id',
        'item_text',
        'is_checked',
        'is_required',
        'display_order',
        'checked_by',
        'checked_at',
        'notes',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'is_checked' => 'boolean',
        'is_required' => 'boolean',
        'display_order' => 'integer',
        'checked_at' => 'datetime',
    ];

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeForWorkOrder($query, $workOrderId)
    {
        return $query->where('work_order_id', $workOrderId);
    }

    public function scopeChecked($query)
    {
        return $query->where('is_checked', true);
    }

    public function scopeUnchecked($query)
    {
        return $query->where('is_checked', false);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('display_order');
    }

    // ===================================
    // METHODS
    // ===================================

    /**
     * Mark item as checked
     */
    public function check(int $userId): void
    {
        $this->is_checked = true;
        $this->checked_by = $userId;
        $this->checked_at = now();
        $this->save();
    }

    /**
     * Mark item as unchecked
     */
    public function uncheck(): void
    {
        $this->is_checked = false;
        $this->checked_by = null;
        $this->checked_at = null;
        $this->save();
    }
}
