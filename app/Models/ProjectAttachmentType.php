<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAttachmentType extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function scopeActive($query) { return $query->where('is_active', true); }
}
