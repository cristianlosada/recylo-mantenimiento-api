<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePlanExecutionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_date' => $this->scheduled_date,
            'scheduled_meter_reading' => $this->scheduled_meter_reading,
            'executed_date' => $this->executed_date,
            'meter_reading_at_execution' => $this->meter_reading_at_execution,
            'status' => $this->status,
            'status_name' => $this->status_name,
            'skip_reason' => $this->skip_reason,
            
            // Información de retraso
            'is_overdue' => $this->is_overdue,
            'days_late' => $this->days_late,
            'execution_delay' => $this->execution_delay,
            
            // Plan de mantenimiento
            'maintenance_plan' => $this->when($this->relationLoaded('maintenancePlan'), function () {
                return [
                    'id' => $this->maintenancePlan->id,
                    'code' => $this->maintenancePlan->code,
                    'plan_name' => $this->maintenancePlan->plan_name,
                    'plan_type' => $this->maintenancePlan->plan_type,
                ];
            }),
            
            // Orden de trabajo generada
            'work_order' => $this->when($this->relationLoaded('workOrder') && $this->workOrder, function () {
                return [
                    'id' => $this->workOrder->id,
                    'code' => $this->workOrder->code,
                    'title' => $this->workOrder->title,
                    'status' => $this->workOrder->status,
                    'priority' => $this->workOrder->priority,
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
