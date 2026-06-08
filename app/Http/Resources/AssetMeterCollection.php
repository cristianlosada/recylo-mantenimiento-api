<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AssetMeterCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($meter) {
                return [
                    'id' => $meter->id,
                    'meter_type' => $meter->meter_type,
                    'meter_type_name' => $meter->type_name,
                    'current_reading' => $meter->current_reading,
                    'formatted_reading' => $meter->formatted_reading,
                    'unit' => $meter->unit,
                    'is_active' => $meter->is_active,
                    
                    'asset' => [
                        'id' => $meter->asset->id,
                        'code' => $meter->asset->code,
                        'name' => $meter->asset->name,
                    ],
                    
                    'last_reading' => [
                        'date' => $meter->last_reading_date,
                    ],
                    
                    'has_recent_readings' => $meter->has_recent_readings,
                    
                    'created_at' => $meter->created_at,
                    'updated_at' => $meter->updated_at,
                ];
            }),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'total' => $this->collection->count(),
                'active' => $this->collection->where('is_active', true)->count(),
                'inactive' => $this->collection->where('is_active', false)->count(),
            ],
        ];
    }
}
