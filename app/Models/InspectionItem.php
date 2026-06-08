<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionItem extends Model
{
    protected $fillable = [
        'section_id', 'name', 'item_type', 'response_options', 'order_index', 'is_required', 'is_active', 'non_conformant_value',
    ];

    protected $casts = [
        'is_required'      => 'boolean',
        'is_active'        => 'boolean',
        'order_index'      => 'integer',
        'item_type'        => 'string',
        'response_options' => 'array',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(InspectionSection::class, 'section_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(InspectionResponse::class, 'item_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
