<?php

namespace App\Http\Resources;

use App\Models\CompanySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
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
            'work_order_type' => $this->work_order_type,
            'priority' => $this->priority,
            'status' => $this->status,
            
            // Relaciones principales
            'company' => $this->when($this->relationLoaded('company'), function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),
            
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

            'work_request' => $this->when($this->relationLoaded('workRequest') && $this->workRequest, function () {
                return [
                    'id' => $this->workRequest->id,
                    'code' => $this->workRequest->code,
                    'title' => $this->workRequest->title,
                ];
            }),

            'project_id' => $this->project_id,
            'project' => $this->when($this->relationLoaded('project') && $this->project, function () {
                return [
                    'id'   => $this->project->id,
                    'code' => $this->project->code,
                    'name' => $this->project->name,
                ];
            }),

            // Programación
            'schedule' => [
                'scheduled_start' => $this->scheduled_start,
                'scheduled_end' => $this->scheduled_end,
                'estimated_duration_hours' => $this->estimated_duration_hours,
                'actual_start' => $this->actual_start,
                'actual_end' => $this->actual_end,
                'actual_duration_hours' => $this->actual_duration_hours,
                'duration_variance' => $this->duration_variance,
                'is_overdue' => $this->is_overdue,
            ],

            // Asignación
            'assignment' => [
                'assigned_to' => $this->when($this->relationLoaded('assignedTo') && $this->assignedTo, function () {
                    return [
                        'id' => $this->assignedTo->id,
                        'name' => $this->assignedTo->first_name . ' ' . $this->assignedTo->last_name,
                        'email' => $this->assignedTo->email,
                    ];
                }),
                'assigned_by' => $this->when($this->relationLoaded('assignedBy') && $this->assignedBy, function () {
                    return [
                        'id' => $this->assignedBy->id,
                        'name' => $this->assignedBy->first_name . ' ' . $this->assignedBy->last_name,
                    ];
                }),
                'assigned_at' => $this->assigned_at,
                'team_members' => $this->when($this->relationLoaded('assignments'), function () {
                    return $this->assignments->map(function ($assignment) {
                        return [
                            'id' => $assignment->id,
                            'user' => [
                                'id' => $assignment->user->id,
                                'name' => $assignment->user->first_name . ' ' . $assignment->user->last_name,
                            ],
                            'role' => $assignment->role,
                            'assigned_at' => $assignment->assigned_at,
                            'notes' => $assignment->notes,
                        ];
                    });
                }),
            ],

            // Costos
            'costs' => [
                'currency' => 'COP',
                'estimated' => [
                    'labor' => $this->estimated_labor_cost,
                    'material' => $this->estimated_material_cost,
                    'other' => $this->estimated_other_cost,
                    'total' => $this->total_estimated_cost,
                ],
                'actual' => [
                    'labor' => $this->actual_labor_cost,
                    'material' => $this->actual_material_cost,
                    'other' => $this->actual_other_cost,
                    'total' => $this->total_actual_cost,
                ],
                'variance' => $this->cost_variance,
            ],

            // Información adicional
            'details' => [
                'failure_type' => $this->failure_type,
                'downtime_hours' => $this->downtime_hours,
                'is_emergency' => $this->is_emergency,
                'requires_shutdown' => $this->requires_shutdown,
            ],

            // SLA
            'sla' => [
                'deadline' => $this->sla_deadline,
                'is_breached' => $this->sla_breached,
                'breach_reason' => $this->sla_breach_reason,
            ],

            // Completación
            'completion' => [
                'notes' => $this->completion_notes,
                'completed_by' => $this->when($this->relationLoaded('completedBy') && $this->completedBy, function () {
                    return [
                        'id' => $this->completedBy->id,
                        'name' => $this->completedBy->first_name . ' ' . $this->completedBy->last_name,
                    ];
                }),
                'completed_at' => $this->completed_at,
                'signature' => [
                    'name' => $this->signature_name,
                    'date' => $this->signature_date,
                    'has_data' => !empty($this->signature_data),
                ],
            ],

            // Notas de aprobación de la solicitud origen
            'approval_notes_request' => $this->approval_notes_request,

            // Validación
            'validation' => [
                'validated_by' => $this->when($this->relationLoaded('validatedBy') && $this->validatedBy, function () {
                    return [
                        'id' => $this->validatedBy->id,
                        'name' => $this->validatedBy->first_name . ' ' . $this->validatedBy->last_name,
                    ];
                }),
                'validated_at' => $this->validated_at,
                'notes' => $this->validation_notes,
            ],

            // Cancelación
            'cancellation' => [
                'cancelled_by' => $this->when($this->relationLoaded('cancelledBy') && $this->cancelledBy, function () {
                    return [
                        'id' => $this->cancelledBy->id,
                        'name' => $this->cancelledBy->first_name . ' ' . $this->cancelledBy->last_name,
                    ];
                }),
                'cancelled_at' => $this->cancelled_at,
                'reason' => $this->cancellation_reason,
            ],

            // Checklist
            'checklist' => [
                'items' => $this->when($this->relationLoaded('checklistItems'), function () {
                    return $this->checklistItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'item_text' => $item->item_text,
                            'display_order' => $item->display_order,
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
                'completion_percentage' => $this->completion_percentage,
            ],

            // Materiales y Herramientas
            'materials' => $this->when($this->relationLoaded('materials'), function () {
                return $this->materials->map(function ($material) {
                    return [
                        'id' => $material->id,
                        'material_id' => $material->material_id,
                        'material' => $material->material ? [
                            'code' => $material->material->code,
                            'name' => $material->material->name,
                            'description' => $material->material->description,
                            'is_tool' => $material->material->is_tool,
                            'category' => $material->material->category ? [
                                'id' => $material->material->category->id,
                                'name' => $material->material->category->name,
                            ] : null,
                        ] : null,
                        'warehouse_id' => $material->warehouse_id,
                        'warehouse' => $material->warehouse ? [
                            'code' => $material->warehouse->code,
                            'name' => $material->warehouse->name,
                        ] : null,
                        
                        // Estado del flujo
                        'material_status' => $material->material_status,
                        
                        // Cantidades
                        'quantity_planned' => $material->quantity_planned,
                        'quantity_requested' => $material->quantity_requested,
                        'quantity_approved' => $material->quantity_approved,
                        'quantity_delivered' => $material->quantity_delivered,
                        'quantity_consumed' => $material->quantity_consumed,
                        'quantity_returned' => $material->quantity_returned,
                        'quantity_variance' => $material->quantity_variance,
                        'unit' => $material->unit,
                        
                        // Costos
                        'unit_cost' => $material->unit_cost,
                        'total_cost' => $material->total_cost,
                        
                        // Fechas del flujo
                        'requested_at' => $material->requested_at,
                        'approved_at' => $material->approved_at,
                        'delivered_at' => $material->delivered_at,
                        'consumed_at' => $material->consumed_at,
                        'returned_at' => $material->returned_at,
                        'completed_at' => $material->completed_at,
                        
                        // Usuarios responsables
                        'requested_by' => $material->requestedBy ? [
                            'id' => $material->requestedBy->id,
                            'name' => $material->requestedBy->first_name . ' ' . $material->requestedBy->last_name,
                        ] : null,
                        'approved_by' => $material->approvedBy ? [
                            'id' => $material->approvedBy->id,
                            'name' => $material->approvedBy->first_name . ' ' . $material->approvedBy->last_name,
                        ] : null,
                        'delivered_by' => $material->deliveredBy ? [
                            'id' => $material->deliveredBy->id,
                            'name' => $material->deliveredBy->first_name . ' ' . $material->deliveredBy->last_name,
                        ] : null,
                        'consumed_by' => $material->consumedBy ? [
                            'id' => $material->consumedBy->id,
                            'name' => $material->consumedBy->first_name . ' ' . $material->consumedBy->last_name,
                        ] : null,
                        'returned_by' => $material->returnedBy ? [
                            'id' => $material->returnedBy->id,
                            'name' => $material->returnedBy->first_name . ' ' . $material->returnedBy->last_name,
                        ] : null,
                        'received_by' => $material->receivedBy ? [
                            'id' => $material->receivedBy->id,
                            'name' => $material->receivedBy->first_name . ' ' . $material->receivedBy->last_name,
                        ] : null,
                        
                        // Notas por etapa
                        'request_notes' => $material->request_notes,
                        'approval_notes' => $material->approval_notes,
                        'delivery_notes' => $material->delivery_notes,
                        'consumption_notes' => $material->consumption_notes,
                        'return_notes' => $material->return_notes,
                    ];
                });
            }),

            // Horas trabajadas
            'time_logs' => $this->when($this->relationLoaded('timeLogs'), function () {
                return $this->timeLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'user' => [
                            'id' => $log->user->id,
                            'name' => $log->user->first_name . ' ' . $log->user->last_name,
                        ],
                        'start_time' => $log->start_time,
                        'end_time' => $log->end_time,
                        'hours_worked' => $log->hours_worked,
                        'hourly_rate' => $log->hourly_rate,
                        'total_cost' => $log->total_cost,
                        'labor_type' => $log->labor_type,
                        'description' => $log->description,
                    ];
                });
            }),

            // Archivos adjuntos
            'attachments' => $this->when($this->relationLoaded('attachments'), function () {
                return $this->attachments->map(function ($attachment) {
                    return [
                        'id' => $attachment->id,
                        'file_name' => $attachment->file_name,
                        'file_path' => $attachment->file_path,
                        'file_url' => asset('storage/' . $attachment->file_path), // URL completa
                        'file_type' => $attachment->file_type,
                        'file_size' => $attachment->file_size,
                        'file_size_human' => $attachment->file_size_human,
                        'attachment_type' => $attachment->attachment_type,
                        'is_image' => in_array(strtolower(pathinfo($attachment->file_name, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']),
                        'uploaded_by' => $attachment->uploadedBy ? [
                            'id' => $attachment->uploadedBy->id,
                            'name' => $attachment->uploadedBy->first_name . ' ' . $attachment->uploadedBy->last_name,
                        ] : null,
                        'uploaded_at' => $attachment->uploaded_at,
                        'description' => $attachment->description,
                    ];
                });
            }),

            // Comentarios
            'comments' => $this->when($this->relationLoaded('comments'), function () {
                return $this->comments->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->first_name . ' ' . $comment->user->last_name,
                            'email' => $comment->user->email,
                        ],
                        'comment' => $comment->comment,
                        'is_internal' => $comment->is_internal,
                        'is_reply' => $comment->is_reply,
                        'has_replies' => $comment->has_replies,
                        'parent_id' => $comment->parent_id,
                        'replies' => $comment->relationLoaded('replies') ? $comment->replies->map(function ($reply) {
                            return [
                                'id' => $reply->id,
                                'user' => [
                                    'id' => $reply->user->id,
                                    'name' => $reply->user->first_name . ' ' . $reply->user->last_name,
                                ],
                                'comment' => $reply->comment,
                                'created_at' => $reply->created_at,
                            ];
                        }) : [],
                        'created_at' => $comment->created_at,
                        'updated_at' => $comment->updated_at,
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
                        'reason' => $history->reason,
                        'metadata' => $history->metadata,
                    ];
                });
            }),

            // Permisos de acciones (helper para el frontend)
            'permissions' => [
                'can_edit' => $this->can_be_edited,
                'can_start' => $this->can_be_started,
                'can_complete' => $this->can_be_completed,
                'can_validate' => $this->can_be_validated,
                'can_cancel' => $this->can_be_cancelled,
            ],

            // Configuración de la empresa relevante para la OT
            'company_settings' => [
                'require_materials_to_complete' => CompanySetting::get((int) $this->company_id, 'require_materials_to_complete', false),
            ],

            // Auditoría
            'audit' => [
                'created_by' => $this->when($this->relationLoaded('createdBy') && $this->createdBy, function () {
                    return [
                        'id' => $this->createdBy->id,
                        'name' => $this->createdBy->first_name . ' ' . $this->createdBy->last_name,
                    ];
                }),
                'updated_by' => $this->when($this->relationLoaded('updatedBy') && $this->updatedBy, function () {
                    return [
                        'id' => $this->updatedBy->id,
                        'name' => $this->updatedBy->first_name . ' ' . $this->updatedBy->last_name,
                    ];
                }),
                'created_at' => $this->created_at,
                'updated_at' => $this->updated_at,
            ],
        ];
    }
}
