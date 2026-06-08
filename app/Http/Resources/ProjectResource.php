<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'code'               => $this->code,
            'name'               => $this->name,
            'description'        => $this->description,
            'objective'          => $this->objective,
            'justification'      => $this->justification,

            // Catálogos
            'type'   => $this->when(
                $this->relationLoaded('type') && $this->type,
                fn() => [
                    'id'          => $this->type->id,
                    'code'        => $this->type->code,
                    'name'        => $this->type->name,
                    'code_prefix' => $this->type->code_prefix,
                    'icon'        => $this->type->icon,
                ]
            ),
            'status' => $this->when(
                $this->relationLoaded('status') && $this->status,
                fn() => [
                    'id'          => $this->status->id,
                    'code'        => $this->status->code,
                    'name'        => $this->status->name,
                    'color'       => $this->status->color,
                    'is_terminal' => $this->status->is_terminal,
                ]
            ),

            // Responsable y área
            'leader' => $this->when(
                $this->relationLoaded('leader') && $this->leader,
                fn() => [
                    'id'        => $this->leader->id,
                    'full_name' => $this->leader->full_name,
                    'name'      => $this->leader->full_name,
                ]
            ),
            'area' => $this->when(
                $this->relationLoaded('area') && $this->area,
                fn() => [
                    'id'   => $this->area->id,
                    'name' => $this->area->name,
                ]
            ),

            // Fechas
            'planned_start_date' => $this->planned_start_date?->toDateString(),
            'planned_end_date'   => $this->planned_end_date?->toDateString(),
            'actual_start_date'  => $this->actual_start_date?->toDateString(),
            'actual_end_date'    => $this->actual_end_date?->toDateString(),

            // Economía y avance
            'budget'           => $this->budget,
            'actual_cost'      => $this->actual_cost,
            'progress_percent' => $this->progress_percent,

            // Cierre
            'closure_notes'   => $this->closure_notes,
            'lessons_learned' => $this->lessons_learned,

            // Trazabilidad
            'approved_by' => $this->when(
                $this->relationLoaded('approvedBy') && $this->approvedBy,
                fn() => [
                    'id'   => $this->approvedBy->id,
                    'name' => $this->approvedBy->full_name,
                ]
            ),
            'approved_at'  => $this->approved_at,
            'closed_by'    => $this->when(
                $this->relationLoaded('closedBy') && $this->closedBy,
                fn() => [
                    'id'   => $this->closedBy->id,
                    'name' => $this->closedBy->full_name,
                ]
            ),
            'closed_at'    => $this->closed_at,
            'cancelled_by' => $this->when(
                $this->relationLoaded('cancelledBy') && $this->cancelledBy,
                fn() => [
                    'id'   => $this->cancelledBy->id,
                    'name' => $this->cancelledBy->full_name,
                ]
            ),
            'cancelled_at' => $this->cancelled_at,
            'created_by'   => $this->when(
                $this->relationLoaded('createdBy') && $this->createdBy,
                fn() => [
                    'id'   => $this->createdBy->id,
                    'name' => $this->createdBy->full_name,
                ]
            ),

            // Relaciones cargadas opcionales
            'phases'   => ProjectPhaseResource::collection($this->whenLoaded('phases')),
            'members'  => ProjectMemberResource::collection($this->whenLoaded('activeMembers')),
            'logs'     => ProjectLogResource::collection($this->whenLoaded('logs')),

            // OTs vinculadas
            'work_orders'       => $this->whenLoaded('workOrders', fn() =>
                $this->workOrders->map(fn($wo) => [
                    'id'              => $wo->id,
                    'code'            => $wo->code,
                    'title'           => $wo->title,
                    'work_order_type' => $wo->work_order_type,
                    'priority'        => $wo->priority,
                    'status'          => $wo->status,
                    'scheduled_start' => $wo->scheduled_start,
                    'scheduled_end'   => $wo->scheduled_end,
                    'assigned_to'     => $wo->assignedTo ? [
                        'id'   => $wo->assignedTo->id,
                        'name' => $wo->assignedTo->full_name,
                    ] : null,
                ])
            ),

            // Computed
            'is_overdue'         => $this->planned_end_date && !$this->status?->is_terminal
                                    && $this->planned_end_date->lt(now()->startOfDay()),
            'members_count'      => $this->when(isset($this->members_count), $this->members_count),
            'logs_count'         => $this->when(isset($this->logs_count), $this->logs_count),
            'work_orders_count'  => $this->when(isset($this->work_orders_count), $this->work_orders_count),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
