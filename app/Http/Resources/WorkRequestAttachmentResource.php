<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkRequestAttachmentResource extends JsonResource
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
            'file_name' => $this->file_name,
            'file_url' => $this->file_url,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->file_size_formatted,
            'is_image' => $this->is_image,
            'is_pdf' => $this->is_pdf,
            'is_document' => $this->is_document,
            'uploaded_by' => $this->when($this->relationLoaded('uploadedBy') && $this->uploadedBy, function () {
                return [
                    'id' => $this->uploadedBy->id,
                    'name' => $this->uploadedBy->first_name . ' ' . $this->uploadedBy->last_name,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
