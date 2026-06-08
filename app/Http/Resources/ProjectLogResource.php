<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectLogResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'project_id' => $this->project_id,
            'user_id'    => $this->user_id,
            'phase_id'   => $this->phase_id,
            'phase'      => $this->when(
                $this->relationLoaded('phase') && $this->phase,
                fn() => ['id' => $this->phase->id, 'name' => $this->phase->name]
            ),
            'status' => $this->when(
                $this->relationLoaded('status') && $this->status,
                fn() => [
                    'id'    => $this->status->id,
                    'code'  => $this->status->code,
                    'name'  => $this->status->name,
                    'color' => $this->status->color,
                ]
            ),
            'user' => $this->when(
                $this->relationLoaded('user') && $this->user,
                fn() => [
                    'id'        => $this->user->id,
                    'full_name' => $this->user->full_name,
                    'name'      => $this->user->full_name,
                ]
            ),
            'logged_by' => $this->when(
                $this->relationLoaded('loggedBy') && $this->loggedBy,
                fn() => [
                    'id'   => $this->loggedBy->id,
                    'name' => $this->loggedBy->full_name,
                ]
            ),
            'log_date'             => $this->log_date?->toDateString(),
            'hours_worked'         => $this->hours_worked,
            'activity_description' => $this->activity_description,
            'result_description'   => $this->result_description,
            'progress_reported'     => $this->progress_reported,
            'progress_contribution' => $this->progress_contribution,
            'findings'             => $this->findings,
            'deliverables'         => $this->deliverables,
            'labor_cost'           => $this->labor_cost,
            'reviewed_by'          => $this->when(
                $this->relationLoaded('reviewedBy') && $this->reviewedBy,
                fn() => [
                    'id'   => $this->reviewedBy->id,
                    'name' => $this->reviewedBy->full_name,
                ]
            ),
            'reviewed_at'  => $this->reviewed_at,
            'validated_by' => $this->when(
                $this->relationLoaded('validatedBy') && $this->validatedBy,
                fn() => [
                    'id'   => $this->validatedBy->id,
                    'name' => $this->validatedBy->full_name,
                ]
            ),
            'validated_at' => $this->validated_at,
            'attachments'  => ProjectAttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
