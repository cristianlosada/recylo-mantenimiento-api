<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectType extends Model
{
    protected $fillable = ['code', 'name', 'code_prefix', 'description', 'icon', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
