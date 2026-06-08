<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectAttachmentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'project_id'    => $this->project_id,
            'log_id'        => $this->log_id,
            'phase_id'      => $this->phase_id,
            'type'          => $this->when($this->relationLoaded('attachmentType') && $this->attachmentType, [
                'id'   => $this->attachmentType->id,
                'code' => $this->attachmentType->code,
                'name' => $this->attachmentType->name,
            ]),
            'file_path'     => $this->file_path,
            'original_name' => $this->original_name,
            'url'           => asset('storage/' . $this->file_path),
            'uploaded_by'   => $this->when($this->relationLoaded('uploadedBy') && $this->uploadedBy, [
                'id'   => $this->uploadedBy->id,
                'name' => $this->uploadedBy->full_name ?? $this->uploadedBy->name,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
