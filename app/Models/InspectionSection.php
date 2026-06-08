<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Asset;

class InspectionSection extends Model
{
    protected $fillable = [
        'template_id', 'asset_id', 'name', 'order_index', 'response_options', 'has_observation', 'is_active',
    ];

    protected $casts = [
        'response_options' => 'array',
        'has_observation'  => 'boolean',
        'is_active'        => 'boolean',
        'order_index'      => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InspectionItem::class, 'section_id')->orderBy('order_index');
    }
}
