<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MaintenancePlanExecutionCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(function ($execution) {
                return [
                    'id' => $execution->id,
                    'scheduled_date' => $execution->scheduled_date,
                    'executed_date' => $execution->executed_date,
                    'status' => $execution->status,
                    'status_name' => $execution->status_name,
                    
                    'maintenance_plan' => [
                        'id' => $execution->maintenancePlan->id,
                        'code' => $execution->maintenancePlan->code,
                        'plan_name' => $execution->maintenancePlan->plan_name,
                    ],
                    
                    'work_order' => $execution->workOrder ? [
                        'id' => $execution->workOrder->id,
                        'code' => $execution->workOrder->code,
                        'status' => $execution->workOrder->status,
                    ] : null,
                    
                    'is_overdue' => $execution->is_overdue,
                    'days_late' => $execution->days_late,
                    
                    'created_at' => $execution->created_at,
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
                'by_status' => [
                    'scheduled' => $this->collection->where('status', 'scheduled')->count(),
                    'completed' => $this->collection->where('status', 'completed')->count(),
                    'skipped' => $this->collection->where('status', 'skipped')->count(),
                    'overdue' => $this->collection->where('status', 'overdue')->count(),
                ],
            ],
        ];
    }
}
