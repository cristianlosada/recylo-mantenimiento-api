<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePlanChecklistTemplateResource extends JsonResource
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
            'item_order' => $this->item_order,
            'item_text' => $this->item_text,
            'requires_photo' => $this->requires_photo,
            'is_mandatory' => $this->is_mandatory,
        ];
    }
}
