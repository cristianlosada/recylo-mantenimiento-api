<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\ProductionLine;

class InspectionTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'company_id', 'category_id', 'name', 'description',
        'is_active', 'requires_horometer',
        'template_type', 'production_line_id',
    ];

    protected $casts = [
        'is_active'            => 'boolean',
        'requires_horometer'   => 'boolean',
        'template_type'        => 'string',
        'production_line_id'   => 'integer',
    ];

    protected $hidden = ['deleted_at'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'category_id');
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(InspectionSection::class, 'template_id')->orderBy('order_index');
    }

    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'inspection_template_assets', 'template_id', 'asset_id')
            ->withTimestamps();
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'template_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}
