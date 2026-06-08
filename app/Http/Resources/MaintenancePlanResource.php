<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePlanResource extends JsonResource
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
            'code' => $this->code,
            'plan_name' => $this->plan_name,
            'description' => $this->description,
            'plan_type' => $this->plan_type,
            'priority' => $this->priority,
            'is_active' => $this->is_active,
            
            // Información de ejecución
            'execution' => [
                'next_execution_date' => $this->next_execution_date,
                'next_meter_threshold' => $this->next_meter_threshold,
                'last_execution_date' => $this->last_execution_date,
                'last_meter_reading' => $this->last_meter_reading,
                'execution_count' => $this->execution_count,
            ],
            
            // Configuración de frecuencia (time_based o hybrid)
            'frequency' => $this->when(
                in_array($this->plan_type, ['time_based', 'hybrid']),
                function () {
                    return [
                        'type' => $this->frequency_type,
                        'value' => $this->frequency_value,
                        'start_date' => $this->start_date,
                    ];
                }
            ),
            
            // Configuración de medidor (meter_based o hybrid)
            'meter_config' => $this->when(
                in_array($this->plan_type, ['meter_based', 'hybrid']),
                function () {
                    return [
                        'meter_type' => $this->meter_type,
                        'meter_threshold' => $this->meter_threshold,
                    ];
                }
            ),
            
            // Modo de disparo (solo hybrid)
            'trigger_mode' => $this->when($this->plan_type === 'hybrid', $this->trigger_mode),
            
            // Activo relacionado
            'asset' => $this->when($this->relationLoaded('asset'), function () {
                return [
                    'id' => $this->asset->id,
                    'code' => $this->asset->code,
                    'name' => $this->asset->name,
                    'category' => $this->asset->category ? [
                        'id' => $this->asset->category->id,
                        'name' => $this->asset->category->name,
                    ] : null,
                ];
            }),
            
            // Categoría de activo
            'asset_category' => $this->when($this->relationLoaded('assetCategory') && $this->assetCategory, function () {
                return [
                    'id' => $this->assetCategory->id,
                    'name' => $this->assetCategory->name,
                    'code' => $this->assetCategory->code,
                ];
            }),
            
            // Empresa y sitio
            'company' => $this->when($this->relationLoaded('company'), function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),
            
            'company_site' => $this->when($this->relationLoaded('site') && $this->site, function () {
                return [
                    'id' => $this->site->id,
                    'name' => $this->site->name,
                ];
            }),
            
            // Estimaciones
            'estimates' => [
                'duration_hours' => $this->estimated_duration_hours,
                'cost' => $this->estimated_cost,
            ],
            
            // Configuración adicional
            'requires_shutdown' => $this->requires_shutdown,
            'safety_notes' => $this->safety_notes,
            'instructions' => $this->instructions,
            
            // Creador
            'created_by' => $this->when($this->relationLoaded('creator') && $this->creator, function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->first_name . ' ' . $this->creator->last_name,
                ];
            }),
            
            // Templates de checklist
            'checklist_templates' => $this->when(
                $this->relationLoaded('checklistTemplates'),
                MaintenancePlanChecklistTemplateResource::collection($this->checklistTemplates)
            ),
            
            // Templates de materiales
            'material_templates' => $this->when(
                $this->relationLoaded('materialTemplates'),
                MaintenancePlanMaterialTemplateResource::collection($this->materialTemplates)
            ),
            
            // Ejecuciones recientes (si se solicitan)
            'recent_executions' => $this->when(
                $this->relationLoaded('executions'),
                MaintenancePlanExecutionResource::collection($this->executions->take(10))
            ),
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
