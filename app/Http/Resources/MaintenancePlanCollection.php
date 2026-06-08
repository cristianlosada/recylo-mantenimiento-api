<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MaintenancePlanCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'plan_name' => $plan->name,
                    'plan_type' => $plan->plan_type,
                    'priority' => $plan->priority,
                    'is_active' => $plan->is_active,
                    
                    'asset' => [
                        'id' => $plan->asset->id,
                        'code' => $plan->asset->code,
                        'name' => $plan->asset->name,
                    ],
                    
                    'execution' => [
                        'next_execution_date' => $plan->next_execution_date,
                        'next_meter_threshold' => $plan->next_meter_threshold,
                        'execution_count' => $plan->execution_count,
                    ],
                    
                    'frequency' => $plan->frequency_type ? [
                        'type' => $plan->frequency_type,
                        'value' => $plan->frequency_value,
                    ] : null,
                    
                    'meter_config' => $plan->meter_type ? [
                        'meter_type' => $plan->meter_type,
                        'meter_threshold' => $plan->meter_threshold,
                    ] : null,
                    
                    'created_at' => $plan->created_at,
                    'updated_at' => $plan->updated_at,
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
                'by_type' => [
                    'time_based' => $this->collection->where('plan_type', 'time_based')->count(),
                    'meter_based' => $this->collection->where('plan_type', 'meter_based')->count(),
                    'hybrid' => $this->collection->where('plan_type', 'hybrid')->count(),
                ],
            ],
        ];
    }
}
