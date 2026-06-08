<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPhaseResource extends Model
{
    protected $fillable = [
        'phase_id', 'resource_type', 'name',
        'quantity', 'unit', 'unit_cost', 'estimated_cost', 'actual_cost',
        'material_id', 'notes', 'created_by',
    ];

    protected $casts = [
        'quantity'       => 'float',
        'unit_cost'      => 'float',
        'estimated_cost' => 'float',
        'actual_cost'    => 'float',
    ];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
