<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectPhaseResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'project_id'  => $this->project_id,
            'name'        => $this->name,
            'description' => $this->description,
            'order_index' => $this->order_index,
            'status'      => $this->when(
                $this->relationLoaded('status') && $this->status,
                fn() => [
                    'id'    => $this->status->id,
                    'code'  => $this->status->code,
                    'name'  => $this->status->name,
                    'color' => $this->status->color,
                ]
            ),
            'responsible' => $this->when(
                $this->relationLoaded('responsible') && $this->responsible,
                fn() => [
                    'id'        => $this->responsible->id,
                    'full_name' => $this->responsible->full_name,
                    'name'      => $this->responsible->full_name,
                ]
            ),
            'technicians' => $this->when(
                $this->relationLoaded('technicians'),
                fn() => $this->technicians->map(fn($u) => [
                    'id'        => $u->id,
                    'full_name' => $u->full_name,
                    'name'      => $u->full_name,
                ])
            ),
            'planned_start_date' => $this->planned_start_date?->toDateString(),
            'planned_end_date'   => $this->planned_end_date?->toDateString(),
            'actual_start_date'  => $this->actual_start_date?->toDateString(),
            'actual_end_date'    => $this->actual_end_date?->toDateString(),
            'weight_percent'     => $this->weight_percent,
            'progress_percent'   => $this->progress_percent,
            'created_at'         => $this->created_at,
            'updated_at'         => $this->updated_at,
        ];
    }
}
