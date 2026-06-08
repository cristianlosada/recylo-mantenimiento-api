<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class AssetMeterReadingCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($reading) {
                return [
                    'id' => $reading->id,
                    'reading_value' => $reading->reading_value,
                    'previous_value' => $reading->previous_value,
                    'difference' => $reading->difference,
                    'reading_date' => $reading->reading_date,
                    'reading_source' => $reading->reading_source,
                    
                    'meter' => [
                        'id' => $reading->assetMeter->id,
                        'meter_type' => $reading->assetMeter->meter_type,
                        'unit' => $reading->assetMeter->unit,
                    ],
                    
                    'recorded_by' => $reading->recordedBy ? [
                        'id' => $reading->recordedBy->id,
                        'name' => $reading->recordedBy->first_name . ' ' . $reading->recordedBy->last_name,
                    ] : null,
                    
                    'created_at' => $reading->created_at,
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
                'manual_readings' => $this->collection->where('reading_source', 'manual')->count(),
                'automatic_readings' => $this->collection->where('reading_source', 'automatic')->count(),
            ],
        ];
    }
}
