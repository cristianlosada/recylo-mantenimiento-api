<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePlanMaterialTemplateResource extends JsonResource
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
            'estimated_quantity' => $this->estimated_quantity,
            
            // Material relacionado
            'material' => $this->when($this->relationLoaded('material'), function () {
                return [
                    'id' => $this->material->id,
                    'code' => $this->material->code,
                    'name' => $this->material->name,
                    'unit' => $this->material->unit,
                    'current_stock' => $this->material->current_stock,
                    'unit_price' => $this->material->unit_price,
                ];
            }),
            
            // Accessors calculados
            'estimated_cost' => $this->estimated_cost,
            'is_available' => $this->is_available,
        ];
    }
}
