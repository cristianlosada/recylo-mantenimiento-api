<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetMeterResource extends JsonResource
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
            'asset_id' => $this->asset_id,
            'meter_type' => $this->meter_type,
            'meter_type_name' => $this->type_name,
            'current_reading' => $this->current_reading,
            'formatted_reading' => $this->formatted_reading,
            'unit' => $this->unit,
            'description' => $this->description,
            'alert_threshold' => $this->alert_threshold,
            'max_reading' => $this->max_reading,
            'last_reading_date' => $this->last_reading_date,
            'is_active' => $this->is_active,
            'has_recent_readings' => $this->has_recent_readings,
            
            // Activo relacionado
            'asset' => $this->when($this->relationLoaded('asset'), function () {
                return [
                    'id' => $this->asset->id,
                    'code' => $this->asset->code,
                    'name' => $this->asset->name,
                    'category' => $this->when($this->asset->category, function () {
                        return [
                            'id' => $this->asset->category->id,
                            'name' => $this->asset->category->name,
                        ];
                    }),
                ];
            }),
            
            // Usuario que registró última lectura
            'last_reading_user' => $this->when($this->relationLoaded('lastReadingUser') && $this->lastReadingUser, function () {
                return [
                    'id' => $this->lastReadingUser->id,
                    'name' => $this->lastReadingUser->first_name . ' ' . $this->lastReadingUser->last_name,
                ];
            }),
            
            // Próximo mantenimiento (si existe)
            'next_maintenance' => $this->when($this->relationLoaded('maintenancePlans'), function () {
                $nextMaintenance = $this->getNextMaintenanceThreshold();
                if (!$nextMaintenance) {
                    return null;
                }
                
                return [
                    'plan_id' => $nextMaintenance['plan_id'],
                    'plan_name' => $nextMaintenance['plan_name'] ?? null,
                    'threshold' => $nextMaintenance['threshold'],
                    'remaining' => $nextMaintenance['remaining'],
                    'percentage' => $nextMaintenance['percentage'],
                ];
            }),
            
            // Lecturas recientes (si se solicitan)
            'recent_readings' => $this->when($this->relationLoaded('readings'), function () {
                return AssetMeterReadingResource::collection($this->readings->take(5));
            }),
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
