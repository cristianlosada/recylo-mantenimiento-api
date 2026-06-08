<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkRequestChecklistTemplateResource extends JsonResource
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
            'company_id' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'checklist_items' => $this->checklist_items ?? [],
            'asset_category_id' => $this->asset_category_id,
            'asset_category' => $this->whenLoaded('assetCategory', function () {
                return [
                    'id' => $this->assetCategory->id,
                    'name' => $this->assetCategory->name,
                    'code' => $this->assetCategory->code,
                ];
            }),
            'request_type' => $this->request_type,
            'request_type_label' => $this->getRequestTypeLabel(),
            'priority' => $this->priority,
            'priority_label' => $this->getPriorityLabel(),
            'is_active' => $this->is_active,
            'is_mandatory' => $this->is_mandatory,
            'display_order' => $this->display_order,
            'created_by' => $this->created_by,
            'created_by_user' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->first_name . ' ' . $this->createdBy->last_name,
                    'email' => $this->createdBy->email,
                ];
            }),
            'usage_count' => $this->when(
                $this->relationLoaded('checklistItems'),
                fn() => $this->checklistItems->count()
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }

    /**
     * Get the label for request type
     */
    private function getRequestTypeLabel(): ?string
    {
        $labels = [
            'corrective' => 'Correctiva',
            'preventive' => 'Preventiva',
            'improvement' => 'Mejora',
            'inspection' => 'Inspección',
        ];

        return $this->request_type ? ($labels[$this->request_type] ?? $this->request_type) : null;
    }

    /**
     * Get the label for priority
     */
    private function getPriorityLabel(): ?string
    {
        $labels = [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ];

        return $this->priority ? ($labels[$this->priority] ?? $this->priority) : null;
    }
}
