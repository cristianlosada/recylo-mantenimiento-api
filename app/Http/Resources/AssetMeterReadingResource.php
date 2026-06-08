<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetMeterReadingResource extends JsonResource
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
            'reading_value' => $this->reading_value,
            'previous_value' => $this->previous_value,
            'difference' => $this->difference,
            'reading_date' => $this->reading_date,
            'reading_source' => $this->reading_source,
            'notes' => $this->notes,
            
            // Medidor relacionado
            'meter' => $this->when($this->relationLoaded('assetMeter'), function () {
                return [
                    'id' => $this->assetMeter->id,
                    'meter_type' => $this->assetMeter->meter_type,
                    'meter_type_name' => $this->assetMeter->type_name,
                    'unit' => $this->assetMeter->unit,
                ];
            }),
            
            // Usuario que registró
            'recorded_by' => $this->when($this->relationLoaded('recordedBy') && $this->recordedBy, function () {
                return [
                    'id' => $this->recordedBy->id,
                    'name' => $this->recordedBy->first_name . ' ' . $this->recordedBy->last_name,
                    'email' => $this->recordedBy->email,
                ];
            }),
            
            // Orden de trabajo relacionada (si aplica)
            'work_order' => $this->when($this->relationLoaded('workOrder') && $this->workOrder, function () {
                return [
                    'id' => $this->workOrder->id,
                    'code' => $this->workOrder->code,
                    'title' => $this->workOrder->title,
                    'status' => $this->workOrder->status,
                ];
            }),
            
            // Plan de mantenimiento relacionado (si aplica)
            'maintenance_plan' => $this->when($this->relationLoaded('maintenancePlan') && $this->maintenancePlan, function () {
                return [
                    'id' => $this->maintenancePlan->id,
                    'code' => $this->maintenancePlan->code,
                    'plan_name' => $this->maintenancePlan->plan_name,
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at,
        ];
    }
}
