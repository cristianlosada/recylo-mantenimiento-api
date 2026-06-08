<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectStatus extends Model
{
    protected $fillable = ['code', 'name', 'description', 'color', 'is_terminal', 'is_active'];

    protected $casts = [
        'is_terminal' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'status_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
