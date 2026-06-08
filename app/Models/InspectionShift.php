<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InspectionShift extends Model
{
    protected $fillable = ['name', 'start_time', 'end_time', 'is_active'];

    protected $casts = [
        'is_active'  => 'boolean',
        'start_time' => 'string',
        'end_time'   => 'string',
    ];

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'shift_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
