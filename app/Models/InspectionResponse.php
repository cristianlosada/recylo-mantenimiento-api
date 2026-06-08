<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionResponse extends Model
{
    protected $fillable = [
        'inspection_id', 'item_id', 'response_value', 'is_non_conformant', 'observation', 'change_made',
    ];

    protected $casts = [
        'is_non_conformant' => 'boolean',
        'change_made'       => 'boolean',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InspectionItem::class, 'item_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(InspectionResponsePhoto::class, 'response_id');
    }
}
