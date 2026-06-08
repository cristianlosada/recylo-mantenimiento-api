<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class InspectionResponsePhoto extends Model
{
    protected $fillable = ['response_id', 'file_path', 'original_name', 'size', 'mime_type'];

    protected $casts = [
        'size' => 'integer',
    ];

    protected $appends = ['url'];

    public function response(): BelongsTo
    {
        return $this->belongsTo(InspectionResponse::class, 'response_id');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->file_path);
    }
}
