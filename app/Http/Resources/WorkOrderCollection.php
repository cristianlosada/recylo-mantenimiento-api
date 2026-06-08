<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class WorkOrderCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'meta' => [
                'total'        => $this->total(),
                'per_page'     => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page'    => $this->lastPage(),
            ],
            'data' => $this->collection->map(function ($workOrder) {
                return [
                    'id' => $workOrder->id,
                    'code' => $workOrder->code,
                    'title' => $workOrder->title,
                    'work_order_type' => $workOrder->work_order_type,
                    'priority' => $workOrder->priority,
                    'status' => $workOrder->status,
                    'is_emergency' => $workOrder->is_emergency,
                    'is_overdue' => $workOrder->is_overdue,
                    
                    'asset' => $workOrder->asset ? [
                        'id' => $workOrder->asset->id,
                        'code' => $workOrder->asset->code,
                        'name' => $workOrder->asset->name,
                    ] : null,
                    
                    'assigned_to' => $workOrder->assignedTo ? [
                        'id' => $workOrder->assignedTo->id,
                        'name' => $workOrder->assignedTo->first_name . ' ' . $workOrder->assignedTo->last_name,
                    ] : null,
                    
                    'schedule' => [
                        'scheduled_start' => $workOrder->scheduled_start,
                        'scheduled_end' => $workOrder->scheduled_end,
                        'actual_start' => $workOrder->actual_start,
                        'actual_end' => $workOrder->actual_end,
                    ],
                    
                    'costs' => [
                        'estimated_total' => $workOrder->total_estimated_cost,
                        'actual_total' => $workOrder->total_actual_cost,
                        'variance' => $workOrder->cost_variance,
                    ],
                    
                    'sla' => [
                        'deadline' => $workOrder->sla_deadline,
                        'is_breached' => $workOrder->sla_breached,
                    ],
                    
                    'completion_percentage' => $workOrder->completion_percentage,
                    
                    'created_at' => $workOrder->created_at,
                    'updated_at' => $workOrder->updated_at,
                ];
            }),
        ];
    }
}
