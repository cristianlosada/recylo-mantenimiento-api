<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inspection extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'template_id', 'asset_id', 'operator_id',
        'shift_id', 'inspection_date', 'status', 'has_findings',
        'work_request_id', 'notes', 'created_by', 'completed_at',
    ];

    protected $casts = [
        'inspection_date' => 'date',
        'has_findings'    => 'boolean',
        'completed_at'    => 'datetime',
    ];

    protected $hidden = ['deleted_at'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(InspectionTemplate::class, 'template_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(InspectionShift::class, 'shift_id');
    }

    public function workRequest(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class, 'work_request_id');
    }

    public function workRequests(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(WorkRequest::class, 'inspection_work_requests')
                    ->withPivot('section_id')
                    ->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(InspectionResponse::class, 'inspection_id');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(AssetMeterReading::class, 'inspection_id');
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }
}
