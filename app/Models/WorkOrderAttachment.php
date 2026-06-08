<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderAttachment extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'work_order_attachments';

    /**
     * Campos asignables en masa
     */
    protected $fillable = [
        'work_order_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'attachment_type',
        'uploaded_by',
        'uploaded_at',
        'description',
    ];

    /**
     * Casteo de tipos de datos
     */
    protected $casts = [
        'file_size' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Deshabilitar timestamps automáticos
     */
    public $timestamps = false;

    // ===================================
    // CONSTANTS
    // ===================================

    public const TYPE_PHOTO_BEFORE = 'photo_before';
    public const TYPE_PHOTO_DURING = 'photo_during';
    public const TYPE_PHOTO_AFTER = 'photo_after';
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_OTHER = 'other';

    // ===================================
    // RELATIONSHIPS
    // ===================================

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ===================================
    // SCOPES
    // ===================================

    public function scopeForWorkOrder($query, $workOrderId)
    {
        return $query->where('work_order_id', $workOrderId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('attachment_type', $type);
    }

    public function scopePhotos($query)
    {
        return $query->whereIn('attachment_type', [
            self::TYPE_PHOTO_BEFORE,
            self::TYPE_PHOTO_DURING,
            self::TYPE_PHOTO_AFTER
        ]);
    }

    public function scopeDocuments($query)
    {
        return $query->where('attachment_type', self::TYPE_DOCUMENT);
    }

    public function scopeSignatures($query)
    {
        return $query->where('attachment_type', self::TYPE_SIGNATURE);
    }

    // ===================================
    // ACCESSORS
    // ===================================

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
