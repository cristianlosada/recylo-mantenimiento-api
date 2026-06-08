<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkRequestResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'request_type' => $this->request_type,
            'priority' => $this->priority,
            'status' => $this->status,
            'equipment_status' => $this->equipment_status,
            
            // Relaciones
            'company' => $this->when($this->relationLoaded('company'), function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),
            
            'asset' => $this->when($this->relationLoaded('asset') && $this->asset, function () {
                return [
                    'id' => $this->asset->id,
                    'code' => $this->asset->code,
                    'name' => $this->asset->name,
                    'category' => $this->asset->category ? [
                        'id' => $this->asset->category->id,
                        'name' => $this->asset->category->name,
                        'code' => $this->asset->category->code,
                    ] : null,
                    'production_line' => $this->asset->relationLoaded('productionLine') && $this->asset->productionLine ? [
                        'id' => $this->asset->productionLine->id,
                        'name' => $this->asset->productionLine->name,
                    ] : null,
                ];
            }),
            
            'requester' => $this->when($this->relationLoaded('requester') && $this->requester, function () {
                return [
                    'id' => $this->requester->id,
                    'name' => trim($this->requester->first_name . ' ' . $this->requester->last_name),
                    'email' => $this->requester->email,
                ];
            }),

            // Información de solicitante público (si aplica)
            'public_requester' => $this->when($this->is_public_request, function () {
                return [
                    'name' => $this->requester_name,
                    'email' => $this->requester_email,
                    'phone' => $this->requester_phone,
                ];
            }),

            'location_details' => $this->location_details,
            
            // QR Code
            'qr_code' => [
                'url' => $this->qr_code_url,
                'generated_at' => $this->qr_code_generated_at,
            ],

            // Work Order vinculada
            'work_order' => $this->when($this->relationLoaded('workOrder') && $this->workOrder, function () {
                return [
                    'id' => $this->workOrder->id,
                    'code' => $this->workOrder->code,
                    'title' => $this->workOrder->title,
                    'status' => $this->workOrder->status,
                    'priority' => $this->workOrder->priority,
                    'work_order_type' => $this->workOrder->work_order_type,
                    'assigned_to' => $this->workOrder->assignedTo ? [
                        'id' => $this->workOrder->assignedTo->id,
                        'name' => $this->workOrder->assignedTo->first_name . ' ' . $this->workOrder->assignedTo->last_name,
                        'email' => $this->workOrder->assignedTo->email,
                    ] : null,
                    'assigned_by' => $this->workOrder->assignedBy ? [
                        'id' => $this->workOrder->assignedBy->id,
                        'name' => $this->workOrder->assignedBy->first_name . ' ' . $this->workOrder->assignedBy->last_name,
                    ] : null,
                    'asset' => $this->workOrder->asset ? [
                        'id' => $this->workOrder->asset->id,
                        'code' => $this->workOrder->asset->code,
                        'name' => $this->workOrder->asset->name,
                    ] : null,
                    'scheduled_start' => $this->workOrder->scheduled_start,
                    'scheduled_end' => $this->workOrder->scheduled_end,
                    'actual_start' => $this->workOrder->actual_start,
                    'actual_end' => $this->workOrder->actual_end,
                    'created_at' => $this->workOrder->created_at,
                ];
            }),
            
            'work_order_id' => $this->work_order_id,
            
            // Costos y estimaciones
            'costs' => [
                'estimated_cost' => $this->estimated_cost,
                'estimated_hours' => $this->estimated_hours,
                'actual_cost' => $this->actual_cost,
                'actual_hours' => $this->actual_hours,
                'cost_variance' => $this->cost_variance,
                'hours_variance' => $this->hours_variance,
            ],
            
            // SLA
            'sla' => [
                'response_due_at' => $this->response_due_at,
                'resolution_due_at' => $this->resolution_due_at,
                'first_response_at' => $this->first_response_at,
                'is_breached' => $this->sla_breached,
                'breach_reason' => $this->sla_breach_reason,
                'is_overdue' => $this->is_overdue,
                'is_pending_response' => $this->is_pending_response,
            ],
            
            // Aprobación/Rechazo
            'approval' => [
                'approved_by' => $this->when($this->relationLoaded('approvedBy'), function () {
                    return $this->approvedBy ? [
                        'id' => $this->approvedBy->id,
                        'name' => $this->approvedBy->first_name . ' ' . $this->approvedBy->last_name,
                    ] : null;
                }),
                'approved_at' => $this->approved_at,
                'rejected_by' => $this->when($this->relationLoaded('rejectedBy'), function () {
                    return $this->rejectedBy ? [
                        'id' => $this->rejectedBy->id,
                        'name' => $this->rejectedBy->first_name . ' ' . $this->rejectedBy->last_name,
                    ] : null;
                }),
                'rejected_at' => $this->rejected_at,
                'rejection_reason' => $this->rejection_reason,
            ],
            
            // Relaciones anidadas (solo si están cargadas)
            'attachments' => WorkRequestAttachmentResource::collection($this->whenLoaded('attachments')),
            'comments' => WorkRequestCommentResource::collection($this->whenLoaded('comments')),
            'tags' => WorkRequestTagResource::collection($this->whenLoaded('tags')),
            'watchers' => $this->when($this->relationLoaded('watchers'), function () {
                return $this->watchers->map(function ($watcher) {
                    return [
                        'id' => $watcher->id,
                        'user' => [
                            'id' => $watcher->user->id,
                            'name' => $watcher->user->first_name . ' ' . $watcher->user->last_name,
                        ],
                        'watched_at' => $watcher->watched_at,
                    ];
                });
            }),
            
            'checklist_items' => $this->when($this->relationLoaded('checklistItems'), function () {
                return $this->checklistItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_text' => $item->item_text,
                        'is_required' => $item->is_required,
                        'is_checked' => $item->is_checked,
                        'checked_by' => $item->checkedBy ? [
                            'id' => $item->checkedBy->id,
                            'name' => $item->checkedBy->first_name . ' ' . $item->checkedBy->last_name,
                        ] : null,
                        'checked_at' => $item->checked_at,
                        'notes' => $item->notes,
                    ];
                });
            }),

            // Solicitudes relacionadas
            'related_requests' => $this->when($this->relationLoaded('relatedRequests'), function () {
                return $this->relatedRequests->filter(function ($related) {
                    return $related && $related->pivot;
                })->map(function ($related) {
                    return [
                        'id' => $related->id,
                        'code' => $related->code,
                        'title' => $related->title,
                        'status' => $related->status,
                        'relationship_type' => $related->pivot->relationship_type,
                    ];
                });
            }),

            // Historial de estados
            'status_history' => $this->when($this->relationLoaded('statusHistory'), function () {
                return $this->statusHistory->map(function ($history) {
                    return [
                        'id' => $history->id,
                        'from_status' => $history->from_status,
                        'to_status' => $history->to_status,
                        'changed_by' => $history->changedBy ? [
                            'id' => $history->changedBy->id,
                            'name' => $history->changedBy->first_name . ' ' . $history->changedBy->last_name,
                        ] : null,
                        'changed_at' => $history->changed_at,
                        'change_reason' => $history->change_reason,
                    ];
                });
            }),
            
            // Estados computados
            'permissions' => [
                'can_be_edited'   => $this->can_be_edited,
                'can_be_approved' => $this->can_be_approved,
                'can_be_rejected' => $this->can_be_rejected,
                'edit_warning'    => $this->edit_warning,
            ],
            
            // Auditoría
            'audit' => [
                'created_by' => $this->when($this->relationLoaded('createdBy'), function () {
                    return $this->createdBy ? [
                        'id' => $this->createdBy->id,
                        'name' => $this->createdBy->first_name . ' ' . $this->createdBy->last_name,
                    ] : null;
                }),
                'updated_by' => $this->when($this->relationLoaded('updatedBy'), function () {
                    return $this->updatedBy ? [
                        'id' => $this->updatedBy->id,
                        'name' => $this->updatedBy->first_name . ' ' . $this->updatedBy->last_name,
                    ] : null;
                }),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
        ];
    }
}
