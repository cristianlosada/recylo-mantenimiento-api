<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkOrderRequest;
use App\Http\Requests\UpdateWorkOrderRequest;
use App\Http\Requests\AssignWorkOrderRequest;
use App\Http\Requests\CompleteWorkOrderRequest;
use App\Http\Requests\ValidateWorkOrderRequest;
use App\Http\Requests\CancelWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Http\Resources\WorkOrderCollection;
use App\Http\Responses\ApiResponse;
use App\Models\CompanySetting;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderChecklistItem;
use App\Models\WorkOrderTimeLog;
use App\Models\WorkRequest;
use App\Models\Company;
use App\Models\UserCompany;
use App\Models\User;
use App\Exports\WorkOrderExport;
use App\Notifications\AssetWorkOrderNotification;
use App\Services\AssetActivityService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Services\AssetNotificationService;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class WorkOrderController extends Controller
{
    protected $activityService;
    protected $notificationService;

    public function __construct(
        AssetActivityService $activityService,
        AssetNotificationService $notificationService
    ) {
        $this->activityService = $activityService;
        $this->notificationService = $notificationService;
    }

    /**
     * Lista OTs pendientes sin asignar para la vista pública de autogestión (TV).
     * Ruta pública — sin autenticación.
     */
    public function pendingUnassigned(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        if (!$companyId) {
            return ApiResponse::error('El parámetro company_id es requerido', 400);
        }

        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $workOrders = WorkOrder::forCompany($companyId)
            ->where('status', WorkOrder::STATUS_PENDING)
            ->whereNull('assigned_to')
            ->with(['asset.category', 'asset.productionLine', 'workRequest'])
            // ->orderByRaw("FIELD(priority, 'critical','high','medium','low')")
            ->orderBy('scheduled_start', 'asc')
            ->get()
            ->map(function ($wo) {
                return [
                    'id'              => $wo->id,
                    'code'            => $wo->code,
                    'title'           => $wo->title,
                    'description'     => $wo->description,
                    'priority'        => $wo->priority,
                    'work_order_type' => $wo->work_order_type,
                    'scheduled_start' => $wo->scheduled_start,
                    'scheduled_end'   => $wo->scheduled_end,
                    'created_at'      => $wo->created_at,
                    'asset'           => $wo->asset ? [
                        'id'              => $wo->asset->id,
                        'name'            => $wo->asset->name,
                        'code'            => $wo->asset->code,
                        'category'        => $wo->asset->category?->name,
                        'production_line' => $wo->asset->productionLine?->name,
                    ] : null,
                ];
            });

        return ApiResponse::success($workOrders);
    }

    /**
     * Lista OTs asignadas recientemente (últimas 8 horas) para la vista TV.
     * Ruta pública — sin autenticación.
     */
    public function recentlyAssigned(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        if (!$companyId) {
            return ApiResponse::error('El parámetro company_id es requerido', 400);
        }

        $since = now()->subHours(8);

        $workOrders = WorkOrder::forCompany($companyId)
            ->whereNotNull('assigned_to')
            ->where('assigned_at', '>=', $since)
            ->with(['asset.productionLine', 'assignedTo'])
            ->orderBy('assigned_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($wo) {
                return [
                    'id'              => $wo->id,
                    'code'            => $wo->code,
                    'title'           => $wo->title,
                    'priority'        => $wo->priority,
                    'work_order_type' => $wo->work_order_type,
                    'assigned_at'     => $wo->assigned_at,
                    'asset'           => $wo->asset ? [
                        'name'            => $wo->asset->name,
                        'production_line' => $wo->asset->productionLine?->name,
                    ] : null,
                    'assigned_user'   => $wo->assignedTo ? [
                        'id'   => $wo->assignedTo->id,
                        'name' => trim(($wo->assignedTo->first_name ?? '') . ' ' . ($wo->assignedTo->last_name ?? '')),
                    ] : null,
                ];
            });

        return ApiResponse::success($workOrders);
    }

    /**
     * Autoasignación pública: un técnico se asigna a una OT pendiente sin asignar.
     * Ruta pública — sin autenticación.
     */
    public function selfAssign(Request $request, int $id): JsonResponse
    {
        $companyId = $request->query('company_id');

        if (!$companyId) {
            return ApiResponse::error('El parámetro company_id es requerido', 400);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $workOrder = WorkOrder::forCompany($companyId)->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        if ($workOrder->status !== WorkOrder::STATUS_PENDING) {
            return ApiResponse::error('Solo se pueden autoasignar órdenes en estado Pendiente', 422);
        }

        if ($workOrder->assigned_to) {
            return ApiResponse::error('Esta orden ya tiene un técnico asignado', 422);
        }

        DB::beginTransaction();
        try {
            $workOrder->assigned_to = $request->user_id;
            $workOrder->assigned_by = $request->user_id;
            $workOrder->assigned_at = now();
            $workOrder->save();

            // Registrar en historial
            DB::table('work_order_status_history')->insert([
                'work_order_id' => $workOrder->id,
                'from_status'   => $workOrder->status,
                'to_status'     => $workOrder->status,
                'changed_by'    => $request->user_id,
                'reason'        => 'Autoasignación desde panel público de técnicos',
                'changed_at'    => now(),
            ]);

            DB::commit();

            NotificationDispatcher::workOrder($workOrder, 'assigned');

            $assignedUser = User::find($workOrder->assigned_to);
            if ($assignedUser && $workOrder->asset) {
                $assignedUser->notify(new AssetWorkOrderNotification($workOrder, $workOrder->asset, 'create'));
            }

            return ApiResponse::success([
                'id'          => $workOrder->id,
                'code'        => $workOrder->code,
                'title'       => $workOrder->title,
                'assigned_to' => $workOrder->assigned_to,
                'assigned_at' => $workOrder->assigned_at,
            ], 'Orden asignada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en autoAsignarse', ['error' => $e->getMessage()]);
            return ApiResponse::error('Error al asignar la orden', 500);
        }
    }

    /**
     * Listar órdenes de trabajo con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = WorkOrder::forCompany($companyId)
            ->with([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

        // Visibilidad por rol: sin VIEW_ALL, ver propias + sin asignar
        $user = Auth::user();
        // if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.VIEW_ALL', (int) $companyId)) {
        //     $query->where(function ($q) use ($user) {
        //         $q->where('assigned_to', $user->id)
        //           ->orWhereHas('assignments', fn($q2) => $q2->where('user_id', $user->id))
        //           ->orWhere('created_by', $user->id)
        //           ->orWhereNull('assigned_to');
        //     });
        // }

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        if ($request->filled('priority')) {
            $query->byPriority($request->priority);
        }

        if ($request->filled('work_order_type')) {
            $query->byType($request->work_order_type);
        }

        if ($request->filled('asset_id')) {
            $query->forAsset($request->asset_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('assigned_to')) {
            $query->assignedTo($request->assigned_to);
        }

        if ($request->boolean('only_overdue')) {
            $query->overdue();
        }

        if ($request->boolean('only_sla_breached')) {
            $query->slaBreached();
        }

        if ($request->boolean('only_emergency')) {
            $query->emergency();
        }

        // Filtro por rango de fechas
        if ($request->filled('scheduled_start_from') && $request->filled('scheduled_start_to')) {
            $query->scheduledBetween($request->scheduled_start_from, $request->scheduled_start_to);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $workOrders = $query->paginate($perPage);

        // Verificar SLA
        $workOrders->getCollection()->transform(function ($workOrder) {
            $workOrder->checkSlaStatus();
            if ($workOrder->isDirty('sla_breached')) {
                $workOrder->save();
            }
            return $workOrder;
        });

        return response()->json(
            new WorkOrderCollection($workOrders)
        );
    }

    /**
     * Crear nueva orden de trabajo
     */
    public function store(StoreWorkOrderRequest $request): JsonResponse
    {
        DB::beginTransaction();
        
        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            // Validar que la solicitud no tenga ya una OT vinculada
            if ($request->work_request_id) {
                $existingWorkRequest = WorkRequest::find($request->work_request_id);
                if ($existingWorkRequest && $existingWorkRequest->work_order_id) {
                    return ApiResponse::error(
                        'La solicitud de trabajo ya tiene una orden de trabajo vinculada (OT: ' . WorkOrder::find($existingWorkRequest->work_order_id)->code . ')',
                        400
                    );
                }
            }

            // Generar código único
            $code = WorkOrder::generateCode($companyId);

            // Calcular duración estimada si no se proporciona pero sí las fechas programadas
            $estimatedDuration = $request->estimated_duration_hours;
            if (!$estimatedDuration && $request->scheduled_start && $request->scheduled_end) {
                $start = \Carbon\Carbon::parse($request->scheduled_start);
                $end = \Carbon\Carbon::parse($request->scheduled_end);
                $estimatedDuration = $start->diffInHours($end, true); // true para obtener valor decimal
            }

            // Crear orden de trabajo
            $workOrder = WorkOrder::create([
                'company_id' => $companyId,
                'code' => $code,
                'title' => $request->title,
                'description' => $request->description,
                'asset_id' => $request->asset_id,
                'project_id' => $request->project_id,
                'work_request_id' => $request->work_request_id,
                'maintenance_plan_id' => $request->maintenance_plan_id,
                'work_order_type' => $request->work_order_type,
                'priority' => $request->priority,
                'status' => $request->status ?? WorkOrder::STATUS_PENDING,
                'assigned_to' => $request->assigned_to,
                'assigned_by' => $request->assigned_to ? $user->id : null,
                'assigned_at' => $request->assigned_to ? now() : null,
                'scheduled_start' => $request->scheduled_start,
                'scheduled_end' => $request->scheduled_end,
                'estimated_duration_hours' => $estimatedDuration,
                'estimated_labor_cost' => $request->estimated_labor_cost,
                'estimated_material_cost' => $request->estimated_material_cost,
                'estimated_other_cost' => $request->estimated_other_cost,
                'failure_type' => $request->failure_type,
                'is_emergency' => $request->is_emergency ?? false,
                'requires_shutdown' => $request->requires_shutdown ?? false,
                'created_by' => $user->id,
            ]);

            // Calcular SLA
            $workOrder->calculateSlaDeadline();
            $workOrder->save();

            // Registrar cambio de estado inicial
            $workOrder->recordStatusChange(
                null,
                $workOrder->status,
                $user->id,
                'Orden de trabajo creada'
            );

            // Crear checklist items si se proporcionan
            if ($request->has('checklist_items')) {
                foreach ($request->checklist_items as $index => $item) {
                    WorkOrderChecklistItem::create([
                        'work_order_id' => $workOrder->id,
                        'item_text' => $item['item_text'],
                        'is_required' => $item['is_required'] ?? false,
                        'display_order' => $item['display_order'] ?? $index,
                    ]);
                }
            }

            // Asignar miembros del equipo si se proporcionan
            if ($request->has('team_members')) {
                foreach ($request->team_members as $member) {
                    WorkOrderAssignment::create([
                        'work_order_id' => $workOrder->id,
                        'user_id' => $member['user_id'],
                        'role' => $member['role'],
                        'assigned_by' => $user->id,
                        'assigned_at' => now(),
                        'notes' => $member['notes'] ?? null,
                    ]);
                }
            }

            // Sincronizar técnico principal con assignments
            if ($workOrder->assigned_to) {
                $this->syncAssignedTechnician($workOrder, null, $user->id);
            }

            // Vincular Work Order con la solicitud (relación bidireccional)
            if ($workOrder->work_request_id) {
                $existingWorkRequest = WorkRequest::find($workOrder->work_request_id);

                // Validar que la solicitud no tenga ya otra OT vinculada
                if ($existingWorkRequest->work_order_id && $existingWorkRequest->work_order_id !== $workOrder->id) {
                    DB::rollBack();
                    $existingWorkOrder = WorkOrder::find($existingWorkRequest->work_order_id);
                    return ApiResponse::error(
                        'La solicitud de trabajo ya tiene una orden de trabajo vinculada: ' . $existingWorkOrder->code,
                        400
                    );
                }

                WorkRequest::where('id', $workOrder->work_request_id)
                    ->update([
                        'work_order_id' => $workOrder->id,
                        'updated_by' => $user->id,
                    ]);

                // Copiar la nota de aprobación de la solicitud a la OT
                $approvalHistory = \App\Models\WorkRequestStatusHistory::where('work_request_id', $existingWorkRequest->id)
                    ->where('to_status', 'approved')
                    ->whereNotNull('reason')
                    ->latest('changed_at')
                    ->first();
                if ($approvalHistory?->reason) {
                    $workOrder->update(['approval_notes_request' => $approvalHistory->reason]);
                }

                // Copiar adjuntos de la solicitud como evidencia inicial de la OT
                $requestAttachments = $existingWorkRequest->attachments()->get();
                if ($requestAttachments->isNotEmpty()) {
                    foreach ($requestAttachments as $att) {
                        WorkOrderAttachment::create([
                            'work_order_id'   => $workOrder->id,
                            'file_name'       => $att->file_name,
                            'file_path'       => $att->file_path,
                            'file_type'       => $att->file_type,
                            'file_size'       => $att->file_size,
                            'attachment_type' => WorkOrderAttachment::TYPE_OTHER,
                            'uploaded_by'     => $att->uploaded_by ?? $user->id,
                            'uploaded_at'     => $att->created_at,
                            'description'     => 'Evidencia inicial (desde solicitud ' . $existingWorkRequest->code . ')',
                        ]);
                    }
                }
            }

            DB::commit();

            // Registrar actividad de creación de OT
            $this->activityService->logWorkOrderCreated($workOrder);

            // Enviar notificaciones a emails configurados
            try {
                $this->notificationService->notifyWorkOrderCreated($workOrder);
            } catch (\Exception $e) {
                Log::warning('Error al enviar notificaciones de creación de OT', [
                    'work_order_id' => $workOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Cargar relaciones para respuesta
            $workOrder->load([
                'asset',
                'assignedTo',
                'workRequest',
                'checklistItems',
                'assignments.user',
            ]);

            Log::info('Orden de trabajo creada', [
                'work_order_id' => $workOrder->id,
                'code' => $workOrder->code,
                'created_by' => $user->id,
            ]);

            return ApiResponse::created(
                new WorkOrderResource($workOrder),
                'Orden de trabajo creada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al crear orden de trabajo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(
                'Error al crear orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Mostrar orden de trabajo específica
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with([
            'company',
            'asset.category',
            'workRequest',
            'project',
            'assignedTo',
            'assignedBy',
            'completedBy',
            'validatedBy',
            'cancelledBy',
            'createdBy',
            'updatedBy',
            'assignments.user',
            'materials.consumedBy',
            'materials.material.category',
            'materials.warehouse',
            'timeLogs.user',
            'attachments.uploadedBy',
            'checklistItems.checkedBy',
            'comments.user',
            'comments.replies.user',
            'statusHistory.changedBy',
        ])
        ->forCompany($companyId)
        ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        // Verificar SLA
        $workOrder->checkSlaStatus();
        if ($workOrder->isDirty('sla_breached')) {
            $workOrder->save();
        }

        return ApiResponse::success(
            new WorkOrderResource($workOrder)
        );
    }

    /**
     * Actualizar orden de trabajo
     */
    public function update(UpdateWorkOrderRequest $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $oldStatus = $workOrder->status;
            $newStatus = $request->input('status', $oldStatus);
            $isStatusChange = $oldStatus !== $newStatus;

            // Si NO se está cambiando el estado, verificar si puede ser editada
            if (!$isStatusChange && !$workOrder->can_be_edited) {
                return ApiResponse::error(
                    'La orden de trabajo no puede ser editada en su estado actual',
                    403
                );
            }

            // Calcular duración estimada si se actualizan las fechas programadas
            if ($request->has('scheduled_start') && $request->has('scheduled_end')) {
                $scheduledStart = $request->scheduled_start;
                $scheduledEnd = $request->scheduled_end;
                
                if ($scheduledStart && $scheduledEnd && !$request->has('estimated_duration_hours')) {
                    $start = \Carbon\Carbon::parse($scheduledStart);
                    $end = \Carbon\Carbon::parse($scheduledEnd);
                    $request->merge([
                        'estimated_duration_hours' => $start->diffInHours($end, true)
                    ]);
                }
            }

            // Si se está intentando cambiar el estado
            if ($isStatusChange) {
                // Validar transición de estado
                if (!$workOrder->canTransitionTo($newStatus)) {
                    return ApiResponse::error(
                        "No se puede cambiar de '{$oldStatus}' a '{$newStatus}'. Transición no permitida.",
                        403
                    );
                }

                // Validaciones adicionales según el estado destino
                if ($newStatus === WorkOrder::STATUS_SCHEDULED) {
                    if (!$request->filled('assigned_to') && !$workOrder->assigned_to) {
                        return ApiResponse::error(
                            'Debe asignar un técnico antes de programar la orden',
                            400
                        );
                    }
                    
                    // Calcular duración estimada si se proporcionan las fechas programadas
                    if ($request->filled('scheduled_start') && $request->filled('scheduled_end') && !$request->filled('estimated_duration_hours')) {
                        $start = \Carbon\Carbon::parse($request->scheduled_start);
                        $end = \Carbon\Carbon::parse($request->scheduled_end);
                        $request->merge([
                            'estimated_duration_hours' => $start->diffInHours($end, true)
                        ]);
                    }
                }

                if ($newStatus === WorkOrder::STATUS_IN_PROGRESS) {
                    $assignedTo = $request->input('assigned_to', $workOrder->assigned_to);
                    if (!$assignedTo) {
                        return ApiResponse::error(
                            'Debe asignar un técnico antes de iniciar la orden',
                            400
                        );
                    }

                    // ⚠️ VALIDACIÓN: El técnico no puede tener otra orden en progreso
                    $otherInProgress = WorkOrder::where('assigned_to', $assignedTo)
                        ->where('status', WorkOrder::STATUS_IN_PROGRESS)
                        ->where('id', '!=', $workOrder->id)
                        ->first();

                    if ($otherInProgress) {
                        return ApiResponse::error(
                            "El técnico asignado ya tiene la orden {$otherInProgress->code} en progreso. " .
                            "Debe completar o pausar la orden actual antes de iniciar una nueva.",
                            400
                        );
                    }

                    if (!$workOrder->actual_start) {
                        $workOrder->actual_start = now();
                    }
                }

                if ($newStatus === WorkOrder::STATUS_COMPLETED) {
                    if (!$workOrder->actual_start) {
                        return ApiResponse::error(
                            'La orden debe haber sido iniciada antes de completarla',
                            400
                        );
                    }

                    // VALIDACIÓN: Verificar que el checklist esté completo
                    $totalChecklistItems = $workOrder->checklistItems()->count();
                    if ($totalChecklistItems > 0) {
                        $requiredPendingItems = $workOrder->checklistItems()
                            ->where('is_required', true)
                            ->where('is_checked', false)
                            ->count();

                        if ($requiredPendingItems > 0) {
                            return ApiResponse::error(
                                "Debe completar todos los items obligatorios del checklist antes de completar la orden. Faltan {$requiredPendingItems} item(s)",
                                400
                            );
                        }
                    }

                    // Validar materiales antes de completar
                    $materialsInPossession = $workOrder->materials()
                        ->whereIn('material_status', ['delivered', 'in_use'])
                        ->count();

                    if ($materialsInPossession > 0) {
                        return ApiResponse::error(
                            "Hay {$materialsInPossession} material(es)/herramienta(s) aún en manos del técnico (entregados o en uso). Debe reportar consumo o devolución antes de completar.",
                            400
                        );
                    }

                    $pendingWarehouseConfirmation = $workOrder->materials()
                        ->where('material_status', 'returned')
                        ->count();

                    if ($pendingWarehouseConfirmation > 0) {
                        return ApiResponse::error(
                            "Hay {$pendingWarehouseConfirmation} material(es)/herramienta(s) devueltos pendientes de confirmación por el almacén. Espere a que el almacén confirme la recepción.",
                            400
                        );
                    }

                    if (!$workOrder->actual_end) {
                        $workOrder->actual_end = now();
                    }
                    $workOrder->calculateActualDuration();
                    $workOrder->calculateActualCosts();
                    $workOrder->completed_by = $user->id;
                    $workOrder->completed_at = now();
                }

                if ($newStatus === WorkOrder::STATUS_VALIDATED) {
                    if ($oldStatus !== WorkOrder::STATUS_COMPLETED) {
                        return ApiResponse::error(
                            'Solo se pueden validar órdenes completadas',
                            400
                        );
                    }
                    $workOrder->validated_by = $user->id;
                    $workOrder->validated_at = now();
                }

                if ($newStatus === WorkOrder::STATUS_CANCELLED) {
                    $workOrder->cancelled_by = $user->id;
                    $workOrder->cancelled_at = now();
                    $workOrder->cancellation_reason = $request->input('cancellation_reason', 'Cancelada desde actualización');
                }
            }

            // Aplicar cambios de campos (sin status aún)
            $oldAssignedTo = $workOrder->assigned_to; // Guardar el valor anterior
            $data = $request->validated();
            unset($data['status']); // Remover status temporalmente
            unset($data['status_change_reason']); // No va al modelo
            unset($data['cancellation_reason']); // Ya se manejó arriba
            
            $workOrder->fill($data);
            
            // Ahora sí establecer el nuevo status si cambió
            if ($isStatusChange) {
                $workOrder->status = $newStatus;
            }
            
            $workOrder->updated_by = $user->id;
            $workOrder->save();

            // Sincronizar técnico principal con assignments si cambió
            $this->syncAssignedTechnician($workOrder, $oldAssignedTo, $user->id);

            // Registrar cambio de estado si hubo
            if ($isStatusChange) {
                $workOrder->recordStatusChange(
                    $oldStatus,
                    $newStatus,
                    $user->id,
                    $request->input('status_change_reason', 'Estado actualizado')
                );
            }

            // Actualizar checklist items si se proporcionan
            if ($request->has('checklist_items')) {
                // Eliminar items existentes que no estén marcados como completados
                $workOrder->checklistItems()->where('is_checked', false)->delete();
                
                // Crear nuevos items
                foreach ($request->checklist_items as $index => $item) {
                    WorkOrderChecklistItem::create([
                        'work_order_id' => $workOrder->id,
                        'item_text' => $item['item_text'],
                        'is_required' => $item['is_required'] ?? false,
                        'display_order' => $item['display_order'] ?? $index,
                    ]);
                }
            }

            DB::commit();

            // Recargar todas las relaciones para respuesta completa
            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo actualizada', [
                'work_order_id' => $workOrder->id,
                'updated_by' => $user->id,
                'status_changed' => $isStatusChange,
                'old_status' => $oldStatus,
                'new_status' => $isStatusChange ? $newStatus : $oldStatus,
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                'Orden de trabajo actualizada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al actualizar orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al actualizar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Eliminar orden de trabajo (soft delete)
     * Requiere permiso WORK_ORDERS_DELETE_ADMIN (solo admin / super admin).
     * Si la OT tiene solicitud vinculada, también se elimina en cascada.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            // ── Verificar permiso admin ────────────────────────────────────────
            if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.DELETE_ADMIN', $companyId)) {
                return ApiResponse::error('No tienes permiso para eliminar órdenes de trabajo', 403);
            }

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            // ── Soft-delete de la solicitud vinculada (si existe) ──────────────
            if ($workOrder->work_request_id) {
                $relatedRequest = \App\Models\WorkRequest::where('company_id', $companyId)
                    ->find($workOrder->work_request_id);

                if ($relatedRequest) {
                    $relatedRequest->delete();

                    Log::info('Solicitud de trabajo eliminada en cascada desde OT', [
                        'work_request_id' => $relatedRequest->id,
                        'work_order_id'   => $id,
                        'deleted_by'      => $user->id,
                    ]);
                }
            }

            // ── Soft-delete de la OT ──────────────────────────────────────────
            $workOrder->deleted_by = $user->id;
            $workOrder->save();
            $workOrder->delete();

            DB::commit();

            Log::info('Orden de trabajo eliminada', [
                'work_order_id' => $id,
                'deleted_by'    => $user->id,
            ]);

            return ApiResponse::success(null, 'Orden de trabajo eliminada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al eliminar orden de trabajo', [
                'work_order_id' => $id,
                'error'         => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al eliminar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Asignar orden de trabajo a usuario/equipo
     */
    public function assign(AssignWorkOrderRequest $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            // Verificar transición de estado
            if (!$workOrder->canTransitionTo(WorkOrder::STATUS_SCHEDULED)) {
                return ApiResponse::error(
                    'No se puede asignar la orden en su estado actual',
                    403
                );
            }

            $oldStatus = $workOrder->status;
            $oldAssignedTo = $workOrder->assigned_to; // Guardar el valor anterior

            // Actualizar asignación principal
            $workOrder->assigned_to = $request->assigned_to;
            $workOrder->assigned_by = $user->id;
            $workOrder->assigned_at = now();
            
            // Actualizar programación si se envía
            if ($request->filled('scheduled_start')) {
                $workOrder->scheduled_start = $request->scheduled_start;
            }
            if ($request->filled('scheduled_end')) {
                if (!$request->filled('scheduled_start') && !$workOrder->scheduled_start) {
                    return ApiResponse::error(
                        'Debe especificar scheduled_start antes de scheduled_end',
                        400
                    );
                }
                $workOrder->scheduled_end = $request->scheduled_end;
            }
            
            // Calcular duración estimada si se proporcionan ambas fechas
            if ($workOrder->scheduled_start && $workOrder->scheduled_end && !$request->filled('estimated_duration_hours')) {
                $start = \Carbon\Carbon::parse($workOrder->scheduled_start);
                $end = \Carbon\Carbon::parse($workOrder->scheduled_end);
                $workOrder->estimated_duration_hours = $start->diffInHours($end, true);
            } elseif ($request->filled('estimated_duration_hours')) {
                $workOrder->estimated_duration_hours = $request->estimated_duration_hours;
            }
            
            $workOrder->status = WorkOrder::STATUS_SCHEDULED;
            $workOrder->updated_by = $user->id;
            $workOrder->save();

            // Registrar cambio de estado
            $workOrder->recordStatusChange(
                $oldStatus,
                $workOrder->status,
                $user->id,
                'Orden asignada a ' . $workOrder->assignedTo->first_name
            );

            // Asignar miembros del equipo
            if ($request->has('team_members')) {
                // Eliminar asignaciones anteriores
                WorkOrderAssignment::where('work_order_id', $workOrder->id)->delete();
                
                // Crear nuevas asignaciones
                foreach ($request->team_members as $member) {
                    WorkOrderAssignment::create([
                        'work_order_id' => $workOrder->id,
                        'user_id' => $member['user_id'],
                        'role' => $member['role'],
                        'assigned_by' => $user->id,
                        'assigned_at' => now(),
                        'notes' => $member['notes'] ?? null,
                    ]);
                }
            }

            // Sincronizar técnico principal con assignments
            $this->syncAssignedTechnician($workOrder, $oldAssignedTo, $user->id);

            DB::commit();

            NotificationDispatcher::workOrder($workOrder, 'assigned');

            $assignedUser = User::find($workOrder->assigned_to);
            if ($assignedUser && $workOrder->asset) {
                $workOrder->loadMissing('asset');
                $assignedUser->notify(new AssetWorkOrderNotification($workOrder, $workOrder->asset, 'create'));
            }

            // Cargar relaciones completas
            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo asignada', [
                'work_order_id' => $workOrder->id,
                'assigned_to' => $request->assigned_to,
                'assigned_by' => $user->id,
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                'Orden de trabajo asignada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al asignar orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al asignar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Iniciar orden de trabajo
     */
    public function start(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            // Validar que tiene técnico asignado
            if (!$workOrder->assigned_to) {
                return ApiResponse::error(
                    'Debe asignar un técnico antes de iniciar la orden',
                    400
                );
            }

            //VALIDACIÓN: Debe tener al menos un material agregado (solo si la empresa lo exige)
            $requireMaterials = CompanySetting::get((int) $companyId, 'require_materials_to_complete', false);
            if ($requireMaterials) {
                $materialsCount = $workOrder->materials()->count();
                if ($materialsCount === 0) {
                    return ApiResponse::error(
                        'Debe agregar al menos un material antes de iniciar la orden de trabajo',
                        400
                    );
                }
            }

            // VALIDACIÓN: Debe tener checklist con al menos un ítem (solo si la empresa lo exige)
            $requireChecklist = CompanySetting::get((int) $companyId, 'require_checklist_to_start', false);
            if ($requireChecklist) {
                $checklistItemsCount = $workOrder->checklistItems()->count();
                if ($checklistItemsCount === 0) {
                    return ApiResponse::error(
                        'Debe agregar al menos un ítem al checklist antes de iniciar la orden de trabajo',
                        400
                    );
                }
            }

            // VALIDACIÓN: El técnico no puede tener otra orden en progreso
            $otherInProgress = WorkOrder::where('assigned_to', $workOrder->assigned_to)
                ->where('status', WorkOrder::STATUS_IN_PROGRESS)
                ->where('id', '!=', $workOrder->id)
                ->first();

            if ($otherInProgress) {
                return ApiResponse::error(
                    "El técnico asignado ya tiene la orden {$otherInProgress->code} en progreso. " .
                    "Debe completar o pausar la orden actual antes de iniciar una nueva.",
                    400
                );
            }

            if (!$workOrder->can_be_started) {
                return ApiResponse::error(
                    'La orden de trabajo no puede ser iniciada en su estado actual',
                    403
                );
            }

            $oldStatus = $workOrder->status;

            $workOrder->status = WorkOrder::STATUS_IN_PROGRESS;
            $workOrder->actual_start = now();
            $workOrder->updated_by = $user->id;
            $workOrder->save();

            // Crear time_log automático al iniciar
            $timeLog = WorkOrderTimeLog::create([
                'work_order_id' => $workOrder->id,
                'user_id' => $workOrder->assigned_to,
                'start_time' => now(),
                'end_time' => null,
                'hours_worked' => 0,
                'hourly_rate' => $workOrder->assignedTo->hourly_rate ?? 0,
                'description' => 'Tiempo de trabajo',
            ]);

            $workOrder->recordStatusChange(
                $oldStatus,
                $workOrder->status,
                $user->id,
                'Orden iniciada'
            );

            DB::commit();

            // Registrar actividad de inicio de OT
            $this->activityService->logWorkOrderStarted($workOrder);

            // Enviar notificaciones a emails configurados (OT abierta)
            try {
                $this->notificationService->notifyWorkOrderStarted($workOrder);
            } catch (\Exception $e) {
                Log::warning('Error al enviar notificaciones de inicio de OT', [
                    'work_order_id' => $workOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Cargar relaciones completas
            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo iniciada', [
                'work_order_id' => $workOrder->id,
                'started_by' => $user->id,
                'time_log_id' => $timeLog->id,
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                'Orden de trabajo iniciada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al iniciar orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al iniciar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Completar orden de trabajo
     */
    public function complete(CompleteWorkOrderRequest $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            // Validar que actual_start no es nulo
            if (!$workOrder->actual_start) {
                return ApiResponse::error(
                    'La orden debe haber sido iniciada antes de completarla',
                    400
                );
            }

            if (!$workOrder->can_be_completed) {
                return ApiResponse::error(
                    'La orden de trabajo no puede ser completada en su estado actual',
                    403
                );
            }

            // VALIDACIÓN: Verificar que el checklist esté completo
            $totalChecklistItems = $workOrder->checklistItems()->count();
            if ($totalChecklistItems > 0) {
                $requiredPendingItems = $workOrder->checklistItems()
                    ->where('is_required', true)
                    ->where('is_checked', false)
                    ->count();

                if ($requiredPendingItems > 0) {
                    return ApiResponse::error(
                        "Debe completar todos los items obligatorios del checklist antes de completar la orden. Faltan {$requiredPendingItems} item(s)",
                        400
                    );
                }
            }

            // Auto-cancelar materiales nunca entregados (planned/requested/approved)
            $workOrder->materials()
                ->whereIn('material_status', ['planned', 'requested', 'approved'])
                ->update([
                    'material_status' => 'cancelled',
                    'return_notes' => 'Cancelado automáticamente: material no fue entregado al técnico',
                ]);

            // Bloquear si hay materiales en manos del técnico
            $materialsInPossession = $workOrder->materials()
                ->whereIn('material_status', ['delivered', 'in_use'])
                ->count();

            if ($materialsInPossession > 0) {
                DB::rollBack();
                return ApiResponse::error(
                    "Hay {$materialsInPossession} material(es)/herramienta(s) aún en manos del técnico. Debe reportar consumo o devolución antes de completar la orden.",
                    400
                );
            }

            // Bloquear si hay materiales devueltos pendientes de confirmación del almacén
            $pendingWarehouseConfirmation = $workOrder->materials()
                ->where('material_status', 'returned')
                ->count();

            if ($pendingWarehouseConfirmation > 0) {
                DB::rollBack();
                return ApiResponse::error(
                    "Hay {$pendingWarehouseConfirmation} material(es)/herramienta(s) devueltos pendientes de confirmación por el almacén. La orden no puede completarse hasta que el almacén confirme la recepción.",
                    400
                );
            }

            // VALIDACIÓN: Materiales requeridos si la empresa lo exige
            $requireMaterials = CompanySetting::get((int) $companyId, 'require_materials_to_complete', false);
            if ($requireMaterials) {
                $registeredMaterials = $workOrder->materials()
                    ->whereNotIn('material_status', ['cancelled'])
                    ->count();
                if ($registeredMaterials === 0) {
                    DB::rollBack();
                    return ApiResponse::error(
                        'La configuración de la empresa requiere registrar al menos un material o repuesto antes de completar la orden.',
                        400
                    );
                }
            }

            $oldStatus = $workOrder->status;

            // Cerrar todos los time_logs abiertos
            $openTimeLogs = WorkOrderTimeLog::where('work_order_id', $workOrder->id)
                ->whereNull('end_time')
                ->get();

            foreach ($openTimeLogs as $timeLog) {
                $timeLog->end_time = now();
                $timeLog->calculateHoursWorked();
                $timeLog->save();
            }

            $workOrder->status = WorkOrder::STATUS_COMPLETED;
            $workOrder->actual_end = now();
            $workOrder->completed_by = $user->id;
            $workOrder->completed_at = now();
            $workOrder->completion_notes = $request->completion_notes;
            $workOrder->signature_data = $request->signature_data;
            $workOrder->signature_name = $request->signature_name;
            $workOrder->signature_date = $request->signature_data ? now() : null;
            $workOrder->downtime_hours = $request->downtime_hours ?? 0;
            $workOrder->updated_by = $user->id;

            // Calcular duración real
            $workOrder->calculateActualDuration();

            // Actualizar costos reales si se proporcionan
            if ($request->filled('actual_labor_cost')) {
                $workOrder->actual_labor_cost = $request->actual_labor_cost;
            }
            if ($request->filled('actual_material_cost')) {
                $workOrder->actual_material_cost = $request->actual_material_cost;
            }
            if ($request->filled('actual_other_cost')) {
                $workOrder->actual_other_cost = $request->actual_other_cost;
            }

            // Calcular costos desde registros
            $workOrder->calculateActualCosts();
            
            $workOrder->save();

            $workOrder->recordStatusChange(
                $oldStatus,
                $workOrder->status,
                $user->id,
                'Orden completada'
            );

            DB::commit();

            NotificationDispatcher::workOrder($workOrder, 'completed');

            // Registrar actividad de completado de OT
            $this->activityService->logWorkOrderCompleted($workOrder);

            // Enviar notificaciones a emails configurados (OT cerrada)
            try {
                $this->notificationService->notifyWorkOrderCompleted($workOrder);
            } catch (\Exception $e) {
                Log::warning('Error al enviar notificaciones de completado de OT', [
                    'work_order_id' => $workOrder->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Cargar relaciones completas
            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo completada', [
                'work_order_id' => $workOrder->id,
                'completed_by' => $user->id,
                'closed_time_logs' => $openTimeLogs->count(),
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                'Orden de trabajo completada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al completar orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al completar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Validar orden de trabajo completada
     */
    public function validate(ValidateWorkOrderRequest $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.VALIDATE', $companyId)) {
                return ApiResponse::forbidden('No tienes permiso para validar órdenes de trabajo');
            }

            if (!$workOrder->can_be_validated) {
                return ApiResponse::error(
                    'La orden de trabajo no puede ser validada en su estado actual',
                    403
                );
            }

            // ⚠️ VALIDACIÓN: Verificar que todos los materiales/herramientas estén devueltos antes de validar
            if ($request->is_approved) {
                $materialsCount = $workOrder->materials()->count();
                
                if ($materialsCount > 0) {
                    // Verificar materiales pendientes de recepción
                    $pendingReception = $workOrder->materials()
                        ->where('material_status', 'returned')
                        ->count();

                    if ($pendingReception > 0) {
                        return ApiResponse::error(
                            "No se puede validar la orden. Hay {$pendingReception} material(es) pendientes de confirmación por el almacenista",
                            400
                        );
                    }

                    // Verificar herramientas no devueltas
                    $toolsNotReturned = $workOrder->materials()
                        ->join('materials', 'work_order_materials.material_id', '=', 'materials.id')
                        ->where('materials.is_tool', true)
                        ->where('work_order_materials.material_status', '!=', 'completed')
                        ->count();

                    if ($toolsNotReturned > 0) {
                        return ApiResponse::error(
                            "No se puede validar la orden. Hay {$toolsNotReturned} herramienta(s) sin devolver confirmadas",
                            400
                        );
                    }
                }
            }

            $oldStatus = $workOrder->status;

            if ($request->is_approved) {
                $workOrder->status = WorkOrder::STATUS_VALIDATED;
                $workOrder->validated_by = $user->id;
                $workOrder->validated_at = now();
                $workOrder->validation_notes = $request->validation_notes;
                $message = 'Orden validada exitosamente';
            } else {
                // Si no se aprueba, poner en pausa para correcciones (no afecta métricas del técnico)
                $workOrder->status = WorkOrder::STATUS_ON_HOLD;
                $workOrder->validation_notes = $request->validation_notes;
                $message = 'Orden puesta en pausa para correcciones';
            }

            $workOrder->updated_by = $user->id;
            $workOrder->save();

            $workOrder->recordStatusChange(
                $oldStatus,
                $workOrder->status,
                $user->id,
                $request->is_approved ? 'Orden validada' : $request->rejection_reason
            );

            DB::commit();

            NotificationDispatcher::workOrder($workOrder, 'validated');

            // Cargar relaciones completas
            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo validada', [
                'work_order_id' => $workOrder->id,
                'validated_by' => $user->id,
                'approved' => $request->is_approved,
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                $message
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al validar orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al validar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Cancelar orden de trabajo
     */
    public function cancel(CancelWorkOrderRequest $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            if (!$workOrder->can_be_cancelled) {
                return ApiResponse::error(
                    'La orden de trabajo no puede ser cancelada en su estado actual',
                    403
                );
            }

            $oldStatus = $workOrder->status;

            $workOrder->status = WorkOrder::STATUS_CANCELLED;
            $workOrder->cancelled_by = $user->id;
            $workOrder->cancelled_at = now();
            $workOrder->cancellation_reason = $request->cancellation_reason;
            $workOrder->updated_by = $user->id;
            $workOrder->save();

            $workOrder->recordStatusChange(
                $oldStatus,
                $workOrder->status,
                $user->id,
                $request->cancellation_reason
            );

            DB::commit();

            NotificationDispatcher::workOrder($workOrder, 'cancelled');

            // Cargar relaciones completas
            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo cancelada', [
                'work_order_id' => $workOrder->id,
                'cancelled_by' => $user->id,
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                'Orden de trabajo cancelada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al cancelar orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al cancelar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Reabrir orden de trabajo (validada → completada, cancelada → pendiente)
     */
    public function reopen(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            if (!in_array($workOrder->status, [WorkOrder::STATUS_VALIDATED, WorkOrder::STATUS_CANCELLED])) {
                return ApiResponse::error(
                    'Solo se pueden reabrir órdenes validadas o canceladas',
                    403
                );
            }

            $oldStatus = $workOrder->status;
            $newStatus = $workOrder->status === WorkOrder::STATUS_VALIDATED
                ? WorkOrder::STATUS_COMPLETED
                : WorkOrder::STATUS_PENDING;

            $workOrder->status = $newStatus;
            $workOrder->updated_by = $user->id;
            $workOrder->save();

            $workOrder->recordStatusChange($oldStatus, $newStatus, $user->id, 'Orden reabierta');

            DB::commit();

            NotificationDispatcher::workOrder($workOrder, 'reopened');

            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo reabierta', [
                'work_order_id' => $workOrder->id,
                'reopened_by' => $user->id,
                'from_status' => $oldStatus,
                'to_status' => $newStatus,
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                'Orden de trabajo reabierta exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al reabrir orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al reabrir orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Pausar orden de trabajo
     */
    public function pause(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
                return ApiResponse::error(
                    'Solo se pueden pausar órdenes en progreso',
                    403
                );
            }

            $oldStatus = $workOrder->status;

            // 🔥 Cerrar todos los time_logs abiertos
            $openTimeLogs = WorkOrderTimeLog::where('work_order_id', $workOrder->id)
                ->whereNull('end_time')
                ->get();

            foreach ($openTimeLogs as $timeLog) {
                $timeLog->end_time = now();
                $timeLog->calculateHoursWorked();
                $timeLog->save();
            }

            $workOrder->status = WorkOrder::STATUS_ON_HOLD;
            $workOrder->updated_by = $user->id;
            $workOrder->save();

            $workOrder->recordStatusChange(
                $oldStatus,
                $workOrder->status,
                $user->id,
                $request->input('reason', 'Orden pausada')
            );

            DB::commit();

            // Cargar relaciones completas
            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo pausada', [
                'work_order_id' => $workOrder->id,
                'paused_by' => $user->id,
                'closed_time_logs' => $openTimeLogs->count(),
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                'Orden de trabajo pausada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al pausar orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al pausar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Reanudar orden de trabajo
     */
    public function resume(Request $request, int $id): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            if ($workOrder->status !== WorkOrder::STATUS_ON_HOLD) {
                return ApiResponse::error(
                    'Solo se pueden reanudar órdenes pausadas',
                    403
                );
            }

            $oldStatus = $workOrder->status;

            $workOrder->status = WorkOrder::STATUS_IN_PROGRESS;
            $workOrder->updated_by = $user->id;
            $workOrder->save();

            // 🔥 Crear nuevo time_log al reanudar
            $timeLog = WorkOrderTimeLog::create([
                'work_order_id' => $workOrder->id,
                'user_id' => $workOrder->assigned_to,
                'start_time' => now(),
                'end_time' => null,
                'hours_worked' => 0,
                'hourly_rate' => $workOrder->assignedTo->hourly_rate ?? 0,
                'description' => 'Tiempo de trabajo (reanudado)',
            ]);

            $workOrder->recordStatusChange(
                $oldStatus,
                $workOrder->status,
                $user->id,
                'Orden reanudada'
            );

            DB::commit();

            // Cargar relaciones completas
            $workOrder->load([
                'asset.category',
                'assignedTo',
                'assignedBy',
                'workRequest',
                'assignments.user',
                'materials.consumedBy',
                'timeLogs.user',
                'attachments.uploadedBy',
                'checklistItems.checkedBy',
                'comments.user',
                'comments.replies.user',
                'statusHistory.changedBy',
                'completedBy',
                'validatedBy',
                'cancelledBy',
                'createdBy',
                'updatedBy',
            ]);

            Log::info('Orden de trabajo reanudada', [
                'work_order_id' => $workOrder->id,
                'resumed_by' => $user->id,
                'time_log_id' => $timeLog->id,
            ]);

            return ApiResponse::success(
                new WorkOrderResource($workOrder),
                'Orden de trabajo reanudada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Error al reanudar orden de trabajo', [
                'work_order_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al reanudar orden de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Estadísticas y métricas de órdenes de trabajo
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $byStatus = WorkOrder::forCompany($companyId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $stats = [
            'total'       => $byStatus->sum(),
            'pending'     => $byStatus->get('pending', 0),
            'scheduled'   => $byStatus->get('scheduled', 0),
            'in_progress' => $byStatus->get('in_progress', 0),
            'on_hold'     => $byStatus->get('on_hold', 0),
            'completed'   => $byStatus->get('completed', 0),
            'validated'   => $byStatus->get('validated', 0),
            'cancelled'   => $byStatus->get('cancelled', 0),
            'overdue'      => WorkOrder::forCompany($companyId)->overdue()->count(),
            'sla_breached' => WorkOrder::forCompany($companyId)->where('sla_breached', true)->count(),
        ];

        return ApiResponse::success($stats);
    }

    // ========================================
    // GESTIÓN DE ASIGNACIONES
    // ========================================

    public function getAssignments(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with(['assignments.user', 'assignments.assignedBy'])
            ->forCompany($companyId)
            ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        return ApiResponse::success($workOrder->assignments);
    }

    public function addAssignment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'user_id'    => 'nullable|integer|exists:users,id',
            'user_ids'   => 'nullable|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'role'       => 'required|in:technician,supervisor,helper,specialist',
            'notes'      => 'nullable|string|max:500',
        ]);

        // Normalizar: aceptar user_id (singular) o user_ids (array)
        $userIds = $request->filled('user_ids')
            ? $request->user_ids
            : ($request->filled('user_id') ? [$request->user_id] : []);

        if (empty($userIds)) {
            return ApiResponse::validation(['user_ids' => ['Debe seleccionar al menos un usuario']]);
        }

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user      = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $created = [];
            foreach ($userIds as $userId) {
                // Evitar duplicados
                $exists = WorkOrderAssignment::where('work_order_id', $workOrder->id)
                    ->where('user_id', $userId)
                    ->exists();
                if ($exists) continue;

                $assignment = WorkOrderAssignment::create([
                    'work_order_id' => $workOrder->id,
                    'user_id'       => $userId,
                    'role'          => $request->role,
                    'assigned_by'   => $user->id,
                    'assigned_at'   => now(),
                    'notes'         => $request->notes,
                ]);
                $assignment->load(['user', 'assignedBy']);
                $created[] = $assignment;
            }

            // Si se agrega un técnico y la OT no tenía técnico principal, auto-asignar el primero
            if ($request->role === 'technician' && !$workOrder->assigned_to && !empty($created)) {
                $workOrder->assigned_to = $created[0]->user_id;
                $workOrder->assigned_by = $user->id;
                $workOrder->assigned_at = now();
                $workOrder->save();
            }

            DB::commit();

            $message = count($created) === 1
                ? 'Miembro del equipo agregado exitosamente'
                : count($created) . ' miembros agregados al equipo';

            return ApiResponse::success($created, $message, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al agregar miembros: ' . $e->getMessage(), 500);
        }
    }

    public function removeAssignment(Request $request, int $id, int $assignmentId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $assignment = WorkOrderAssignment::where('work_order_id', $id)
                ->where('id', $assignmentId)
                ->first();

            if (!$assignment) {
                return ApiResponse::notFound('Asignación no encontrada');
            }

            $assignment->delete();

            DB::commit();

            return ApiResponse::success(null, 'Miembro del equipo removido exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al remover miembro: ' . $e->getMessage(), 500);
        }
    }

    // ========================================
    // GESTIÓN DE MATERIALES Y HERRAMIENTAS
    // ========================================

    /**
     * Obtener materiales/herramientas de una orden
     */
    public function getMaterials(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with([
            'materials.material.category',
            'materials.warehouse',
            'materials.requestedBy',
            'materials.approvedBy',
            'materials.deliveredBy',
            'materials.consumedBy',
            'materials.returnedBy',
            'materials.receivedBy'
        ])
        ->forCompany($companyId)
        ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        return ApiResponse::success($workOrder->materials);
    }

    /**
     * Agregar material/herramienta planificado a la orden
     * Se usa al crear o actualizar la orden
     */
    public function addMaterial(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'material_id' => 'required|integer|exists:materials,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'quantity_planned' => 'required|numeric|min:0',
            'unit' => 'required|string|max:50',
            'unit_cost' => 'nullable|numeric|min:0',
            'request_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            // ⚠️ VALIDACIÓN: Solo se pueden planear materiales en estados 'pending' o 'scheduled'
            if (!in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED])) {
                return ApiResponse::error(
                    'Solo se pueden agregar materiales planeados cuando la orden está en estado "Pendiente" o "Programada". '
                    . 'Estado actual: ' . WorkOrder::getStatusLabel($workOrder->status),
                    400
                );
            }

            // Obtener info del material
            $material = \App\Models\Material::find($request->material_id);
            
            // 🔍 VERIFICAR si ya existe el mismo material del mismo almacén en estado 'planned'
            $existingMaterial = \App\Models\WorkOrderMaterial::where('work_order_id', $workOrder->id)
                ->where('material_id', $request->material_id)
                ->where('warehouse_id', $request->warehouse_id)
                ->where('material_status', 'planned')
                ->first();

            if ($existingMaterial) {
                // ✅ Si existe, SUMAR la cantidad a la existente
                $existingMaterial->quantity_planned += $request->quantity_planned;
                
                // Actualizar notas si se proporciona nueva información
                if ($request->filled('request_notes')) {
                    $existingMaterial->request_notes = $existingMaterial->request_notes 
                        ? $existingMaterial->request_notes . "\n---\n" . $request->request_notes
                        : $request->request_notes;
                }
                
                // Actualizar costo unitario si se proporciona
                if ($request->filled('unit_cost')) {
                    $existingMaterial->unit_cost = $request->unit_cost;
                }
                
                $existingMaterial->save();
                $existingMaterial->load(['material.category', 'warehouse']);
                
                DB::commit();
                
                return ApiResponse::success(
                    $existingMaterial, 
                    'Cantidad actualizada. Se sumó ' . $request->quantity_planned . ' ' . $request->unit . ' al material existente'
                );
            }
            
            // ➕ Si NO existe, crear nuevo registro
            $workOrderMaterial = \App\Models\WorkOrderMaterial::create([
                'work_order_id' => $workOrder->id,
                'material_id' => $request->material_id,
                'warehouse_id' => $request->warehouse_id,
                'material_status' => 'planned',
                'quantity_planned' => $request->quantity_planned,
                'unit' => $request->unit,
                'unit_cost' => $request->unit_cost ?? $material->unit_cost ?? 0,
                'request_notes' => $request->request_notes,
            ]);

            $workOrderMaterial->load(['material.category', 'warehouse']);

            DB::commit();

            return ApiResponse::created($workOrderMaterial, 'Material agregado a la orden');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al agregar material: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Técnico solicita material/herramienta al almacén
     */
    public function requestMaterial(Request $request, int $id, int $materialId): JsonResponse
    {
        $request->validate([
            'quantity_requested' => 'required|numeric|min:0',
            'request_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            // Validar permiso
            if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.MATERIALS_REQUEST', $companyId)) {
                return ApiResponse::error('No tienes permiso para solicitar materiales', 403);
            }

            $workOrder = WorkOrder::forCompany($companyId)->find($id);
            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            // ⚠️ VALIDACIÓN: Solo se pueden solicitar materiales cuando la orden está "En Progreso"
            if ($workOrder->status !== WorkOrder::STATUS_IN_PROGRESS) {
                return ApiResponse::error(
                    'Solo se pueden solicitar materiales cuando la orden de trabajo está en estado "En Progreso". '
                    . 'Estado actual: ' . WorkOrder::getStatusLabel($workOrder->status),
                    400
                );
            }

            $workOrderMaterial = \App\Models\WorkOrderMaterial::where('work_order_id', $id)
                ->where('id', $materialId)
                ->first();

            if (!$workOrderMaterial) {
                return ApiResponse::notFound('Material no encontrado en la orden');
            }

            if (!in_array($workOrderMaterial->material_status, ['planned', 'requested'])) {
                return ApiResponse::error('Material ya está en proceso', 400);
            }

            $workOrderMaterial->update([
                'material_status' => 'requested',
                'quantity_requested' => $request->quantity_requested,
                'requested_by' => $user->id,
                'requested_at' => now(),
                'request_notes' => $request->request_notes ?? $workOrderMaterial->request_notes,
            ]);

            DB::commit();

            return ApiResponse::success($workOrderMaterial, 'Solicitud de material enviada al almacén');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al solicitar material: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Almacenista aprueba/rechaza solicitud de material
     */
    public function approveMaterial(Request $request, int $id, int $materialId): JsonResponse
    {
        $request->validate([
            'approved' => 'required|boolean',
            'quantity_approved' => 'required_if:approved,true|nullable|numeric|min:0',
            'approval_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            // Validar permiso
            if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.MATERIALS_APPROVE', $companyId)) {
                return ApiResponse::error('No tienes permiso para aprobar solicitudes de materiales', 403);
            }

            $workOrder = WorkOrder::forCompany($companyId)->find($id);
            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $workOrderMaterial = \App\Models\WorkOrderMaterial::where('work_order_id', $id)
                ->where('id', $materialId)
                ->first();

            if (!$workOrderMaterial) {
                return ApiResponse::notFound('Material no encontrado');
            }

            if ($workOrderMaterial->material_status !== 'requested') {
                return ApiResponse::error('Material no está en estado de solicitud', 400);
            }

            if ($request->approved) {
                $workOrderMaterial->update([
                    'material_status' => 'approved',
                    'quantity_approved' => $request->quantity_approved,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'approval_notes' => $request->approval_notes,
                ]);

                $message = 'Material aprobado para entrega';
            } else {
                $workOrderMaterial->update([
                    'material_status' => 'planned',
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                    'approval_notes' => $request->approval_notes,
                ]);

                $message = 'Solicitud de material rechazada';
            }

            DB::commit();

            return ApiResponse::success($workOrderMaterial, $message);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al aprobar material: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Almacenista entrega material/herramienta (checking de salida)
     */
    public function deliverMaterial(Request $request, int $id, int $materialId): JsonResponse
    {
        $request->validate([
            'quantity_delivered' => 'required|numeric|min:0',
            'delivery_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            // Validar permiso
            if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.MATERIALS_DELIVER', $companyId)) {
                return ApiResponse::error('No tienes permiso para entregar materiales', 403);
            }

            $workOrder = WorkOrder::forCompany($companyId)->find($id);
            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $workOrderMaterial = \App\Models\WorkOrderMaterial::with('material')
                ->where('work_order_id', $id)
                ->where('id', $materialId)
                ->first();

            if (!$workOrderMaterial) {
                return ApiResponse::notFound('Material no encontrado');
            }

            if ($workOrderMaterial->material_status !== 'approved') {
                return ApiResponse::error('Material no está aprobado para entrega', 400);
            }

            // Verificar stock disponible
            $stock = \App\Models\WarehouseStock::where('warehouse_id', $workOrderMaterial->warehouse_id)
                ->where('material_id', $workOrderMaterial->material_id)
                ->first();

            $isTool = $workOrderMaterial->material->is_tool;

            if (!$isTool) {
                // Para materiales consumibles, verificar stock
                if (!$stock || $stock->quantity < $request->quantity_delivered) {
                    return ApiResponse::error('Stock insuficiente en almacén', 400);
                }

                // Descontar stock
                $stock->quantity -= $request->quantity_delivered;
                $stock->save();

                // Registrar transacción de salida
                \App\Models\InventoryTransaction::create([
                    'company_id' => $companyId,
                    'transaction_code' => 'INV-' . date('Ym') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                    'transaction_type' => 'work_order_out',
                    'warehouse_id' => $workOrderMaterial->warehouse_id,
                    'material_id' => $workOrderMaterial->material_id,
                    'quantity' => -$request->quantity_delivered,
                    'unit_cost' => $workOrderMaterial->unit_cost,
                    'total_cost' => -($request->quantity_delivered * $workOrderMaterial->unit_cost),
                    'balance_after' => $stock->quantity,
                    'reason' => 'Entrega para Orden de Trabajo ' . $workOrder->code,
                    'work_order_id' => $workOrder->id,
                    'transaction_date' => now(),
                    'performed_by' => $user->id,
                ]);
            } else {
                // Para herramientas, registrar asignación (NO descuenta stock)
                \App\Models\InventoryTransaction::create([
                    'company_id' => $companyId,
                    'transaction_code' => 'TOOL-' . date('Ym') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                    'transaction_type' => 'tool_assignment',
                    'warehouse_id' => $workOrderMaterial->warehouse_id,
                    'material_id' => $workOrderMaterial->material_id,
                    'quantity' => $request->quantity_delivered,
                    'unit_cost' => 0,
                    'total_cost' => 0,
                    'balance_after' => $stock ? $stock->quantity : 0,
                    'reason' => 'Asignación de herramienta para OT ' . $workOrder->code,
                    'work_order_id' => $workOrder->id,
                    'transaction_date' => now(),
                    'performed_by' => $user->id,
                ]);
            }

            $workOrderMaterial->update([
                'material_status' => 'delivered',
                'quantity_delivered' => $request->quantity_delivered,
                'delivered_by' => $user->id,
                'delivered_at' => now(),
                'delivery_notes' => $request->delivery_notes,
            ]);

            DB::commit();

            return ApiResponse::success($workOrderMaterial, $isTool ? 'Herramienta entregada' : 'Material entregado y stock descontado');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al entregar material: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Técnico reporta consumo real de material/uso de herramienta
     */
    public function consumeMaterial(Request $request, int $id, int $materialId): JsonResponse
    {
        $request->validate([
            'quantity_consumed' => 'required|numeric|min:0',
            'consumption_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            // Validar permiso
            if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.MATERIALS_CONSUME', $companyId)) {
                return ApiResponse::error('No tienes permiso para registrar consumo de materiales', 403);
            }

            $workOrder = WorkOrder::forCompany($companyId)->find($id);
            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $workOrderMaterial = \App\Models\WorkOrderMaterial::with('material')
                ->where('work_order_id', $id)
                ->where('id', $materialId)
                ->first();

            if (!$workOrderMaterial) {
                return ApiResponse::notFound('Material no encontrado');
            }

            // Validar que no sea una herramienta
            if ($workOrderMaterial->material && $workOrderMaterial->material->is_tool) {
                return ApiResponse::error('Las herramientas no son consumibles. Deben ser devueltas después de su uso.', 400);
            }

            if ($workOrderMaterial->material_status !== 'delivered') {
                return ApiResponse::error('Material no ha sido entregado', 400);
            }

            $workOrderMaterial->update([
                'material_status' => 'consumed',
                'quantity_consumed' => $request->quantity_consumed,
                'consumed_by' => $user->id,
                'consumed_at' => now(),
                'consumption_notes' => $request->consumption_notes,
            ]);

            // Calcular costo total
            $workOrderMaterial->calculateTotalCost();
            $workOrderMaterial->save();

            DB::commit();

            return ApiResponse::success($workOrderMaterial, 'Consumo/uso registrado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al registrar consumo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Técnico devuelve sobrantes de material o herramienta
     */
    public function returnMaterial(Request $request, int $id, int $materialId): JsonResponse
    {
        $request->validate([
            'quantity_returned' => 'required|numeric|min:0',
            'return_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            // Validar permiso
            if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.MATERIALS_RETURN', $companyId)) {
                return ApiResponse::error('No tienes permiso para devolver materiales', 403);
            }

            $workOrder = WorkOrder::forCompany($companyId)->find($id);
            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $workOrderMaterial = \App\Models\WorkOrderMaterial::with('material')
                ->where('work_order_id', $id)
                ->where('id', $materialId)
                ->first();

            if (!$workOrderMaterial) {
                return ApiResponse::notFound('Material no encontrado');
            }

            // Validar estado según tipo de material
            $isToolReturn = $workOrderMaterial->material && $workOrderMaterial->material->is_tool;

            if ($isToolReturn) {
                // Las herramientas se devuelven directamente desde 'delivered'
                if ($workOrderMaterial->material_status !== 'delivered') {
                    return ApiResponse::error('La herramienta debe estar en estado entregado para ser devuelta', 400);
                }
            } else {
                // Los materiales consumibles se devuelven después de reportar consumo
                if ($workOrderMaterial->material_status !== 'consumed') {
                    return ApiResponse::error('Debe registrar el consumo antes de devolver sobrantes', 400);
                }
            }

            $workOrderMaterial->update([
                'material_status' => 'returned',
                'quantity_returned' => $request->quantity_returned,
                'returned_by' => $user->id,
                'returned_at' => now(),
                'return_notes' => $request->return_notes,
            ]);

            DB::commit();

            return ApiResponse::success(
                $workOrderMaterial, 
                $isToolReturn 
                    ? 'Devolución de herramienta registrada. Pendiente de confirmación por el almacenista.' 
                    : 'Devolución de sobrantes registrada. Pendiente de recepción en almacén.'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al registrar devolución: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Almacenista recibe devolución y actualiza stock
     */
    public function receiveMaterial(Request $request, int $id, int $materialId): JsonResponse
    {
        $request->validate([
            'quantity_received' => 'required|numeric|min:0',
            'reception_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            // Validar permiso
            if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_ORDERS.MATERIALS_RECEIVE', $companyId)) {
                return ApiResponse::error('No tienes permiso para recibir devoluciones de materiales', 403);
            }

            $workOrder = WorkOrder::forCompany($companyId)->find($id);
            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $workOrderMaterial = \App\Models\WorkOrderMaterial::with('material')
                ->where('work_order_id', $id)
                ->where('id', $materialId)
                ->first();

            if (!$workOrderMaterial) {
                return ApiResponse::notFound('Material no encontrado');
            }

            if ($workOrderMaterial->material_status !== 'returned') {
                return ApiResponse::error('Material no está en estado de devolución', 400);
            }

            $isTool = $workOrderMaterial->material->is_tool;

            if (!$isTool && $request->quantity_received > 0) {
                // Material consumible: regresar a stock
                $stock = \App\Models\WarehouseStock::where('warehouse_id', $workOrderMaterial->warehouse_id)
                    ->where('material_id', $workOrderMaterial->material_id)
                    ->first();

                if ($stock) {
                    $stock->quantity += $request->quantity_received;
                    $stock->save();
                }

                // Registrar transacción de entrada
                \App\Models\InventoryTransaction::create([
                    'company_id' => $companyId,
                    'transaction_code' => 'RET-' . date('Ym') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                    'transaction_type' => 'work_order_return',
                    'warehouse_id' => $workOrderMaterial->warehouse_id,
                    'material_id' => $workOrderMaterial->material_id,
                    'quantity' => $request->quantity_received,
                    'unit_cost' => $workOrderMaterial->unit_cost,
                    'total_cost' => $request->quantity_received * $workOrderMaterial->unit_cost,
                    'balance_after' => $stock->quantity,
                    'reason' => 'Devolución de sobrante de OT ' . $workOrder->code,
                    'work_order_id' => $workOrder->id,
                    'transaction_date' => now(),
                    'performed_by' => $user->id,
                ]);
            } else {
                // Herramienta: solo registrar devolución
                \App\Models\InventoryTransaction::create([
                    'company_id' => $companyId,
                    'transaction_code' => 'TRET-' . date('Ym') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT),
                    'transaction_type' => 'tool_return',
                    'warehouse_id' => $workOrderMaterial->warehouse_id,
                    'material_id' => $workOrderMaterial->material_id,
                    'quantity' => $request->quantity_received,
                    'unit_cost' => 0,
                    'total_cost' => 0,
                    'balance_after' => 0,
                    'reason' => 'Devolución de herramienta de OT ' . $workOrder->code,
                    'work_order_id' => $workOrder->id,
                    'transaction_date' => now(),
                    'performed_by' => $user->id,
                ]);
            }

            $workOrderMaterial->update([
                'material_status' => 'completed',
                'received_by' => $user->id,
                'completed_at' => now(),
                'reception_notes' => $request->reception_notes,
            ]);

            DB::commit();

            return ApiResponse::success(
                $workOrderMaterial, 
                $isTool 
                    ? 'Herramienta recibida y confirmada en almacén' 
                    : 'Material recibido, stock actualizado'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al recibir devolución: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar material (mantener compatibilidad)
     */
    public function updateMaterial(Request $request, int $id, int $materialId): JsonResponse
    {
        $request->validate([
            'quantity_planned' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'request_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $material = \App\Models\WorkOrderMaterial::where('work_order_id', $id)
                ->where('id', $materialId)
                ->first();

            if (!$material) {
                return ApiResponse::notFound('Material no encontrado');
            }

            // Solo permitir actualizar si está en planned
            if ($material->material_status !== 'planned') {
                return ApiResponse::error('Solo se pueden modificar materiales en estado planificado', 400);
            }

            // ⚠️ VALIDACIÓN: Solo se pueden editar materiales planeados en estados 'pending' o 'scheduled'
            if (!in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED])) {
                return ApiResponse::error(
                    'Solo se pueden editar materiales planeados cuando la orden está en estado "Pendiente" o "Programada". '
                    . 'Estado actual: ' . WorkOrder::getStatusLabel($workOrder->status),
                    400
                );
            }

            $updateData = [];
            if ($request->filled('quantity_planned')) {
                $updateData['quantity_planned'] = $request->quantity_planned;
            }
            if ($request->filled('unit_cost')) {
                $updateData['unit_cost'] = $request->unit_cost;
            }
            if ($request->filled('request_notes')) {
                $updateData['request_notes'] = $request->request_notes;
            }

            $material->update($updateData);

            DB::commit();

            return ApiResponse::success($material, 'Material actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar material: ' . $e->getMessage(), 500);
        }
    }

    public function removeMaterial(Request $request, int $id, int $materialId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $material = \App\Models\WorkOrderMaterial::where('work_order_id', $id)
                ->where('id', $materialId)
                ->first();

            if (!$material) {
                return ApiResponse::notFound('Material no encontrado');
            }

            // Solo permitir eliminar si está en estado 'planned'
            if ($material->material_status !== 'planned') {
                return ApiResponse::error(
                    'Solo se pueden eliminar materiales en estado "planeado"',
                    400
                );
            }

            // ⚠️ VALIDACIÓN: Solo se pueden eliminar materiales cuando la orden está en 'pending' o 'scheduled'
            if (!in_array($workOrder->status, [WorkOrder::STATUS_PENDING, WorkOrder::STATUS_SCHEDULED])) {
                return ApiResponse::error(
                    'Solo se pueden eliminar materiales planeados cuando la orden está en estado "Pendiente" o "Programada". '
                    . 'Estado actual: ' . WorkOrder::getStatusLabel($workOrder->status),
                    400
                );
            }

            $material->delete();

            DB::commit();

            return ApiResponse::success(null, 'Material removido exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al remover material: ' . $e->getMessage(), 500);
        }
    }

    // ========================================
    // GESTIÓN DE REGISTROS DE TIEMPO
    // ========================================

    public function getTimeLogs(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with(['timeLogs.user'])
            ->forCompany($companyId)
            ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        return ApiResponse::success($workOrder->timeLogs);
    }

    public function addTimeLog(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'start_time' => 'required|date_format:Y-m-d\TH:i:sP,Y-m-d\TH:i:s,Y-m-d H:i:s',
            'end_time'   => 'required|date_format:Y-m-d\TH:i:sP,Y-m-d\TH:i:s,Y-m-d H:i:s',
            'hourly_rate' => 'nullable|numeric|min:0',
            'labor_type' => 'required|in:regular,overtime,weekend,holiday',
            'work_description' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user      = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $start       = \Carbon\Carbon::parse($request->start_time)->utc();
            $end         = \Carbon\Carbon::parse($request->end_time)->utc();
            $hoursWorked = round($start->diffInMinutes($end) / 60, 2);

            // Auto-precargar tarifa horaria desde user_companies si no se envió
            if ($request->filled('hourly_rate')) {
                $hourlyRate = (float) $request->hourly_rate;
            } else {
                $hourlyRate = (float) (UserCompany::where('user_id', $user->id)
                    ->where('company_id', $companyId)
                    ->value('hourly_rate') ?? 0);
            }

            $timeLog = \App\Models\WorkOrderTimeLog::create([
                'work_order_id' => $workOrder->id,
                'user_id'       => $user->id,
                'start_time'    => $request->start_time,
                'end_time'      => $request->end_time,
                'hours_worked'  => $hoursWorked,
                'hourly_rate'   => $hourlyRate,
                'total_cost'    => round($hoursWorked * $hourlyRate, 2),
                'labor_type'    => $request->labor_type,
                'description'   => $request->input('work_description') ?? $request->input('description'),
            ]);

            DB::commit();

            $timeLog->load('user');

            return ApiResponse::created($timeLog, 'Registro de tiempo agregado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al agregar registro: ' . $e->getMessage(), 500);
        }
    }

    public function updateTimeLog(Request $request, int $id, int $logId): JsonResponse
    {
        $request->validate([
            'start_time' => 'sometimes|date',
            'end_time' => 'sometimes|date|after:start_time',
            'work_description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $timeLog = \App\Models\WorkOrderTimeLog::where('work_order_id', $id)
                ->where('id', $logId)
                ->first();

            if (!$timeLog) {
                return ApiResponse::notFound('Registro de tiempo no encontrado');
            }

            if ($request->filled('start_time')) {
                $timeLog->start_time = $request->start_time;
            }
            if ($request->filled('end_time')) {
                $timeLog->end_time = $request->end_time;
            }
            if ($request->filled('work_description')) {
                $timeLog->work_description = $request->work_description;
            }

            $timeLog->calculateAll();
            $timeLog->save();

            DB::commit();

            return ApiResponse::success($timeLog, 'Registro de tiempo actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar registro: ' . $e->getMessage(), 500);
        }
    }

    public function removeTimeLog(Request $request, int $id, int $logId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $timeLog = \App\Models\WorkOrderTimeLog::where('work_order_id', $id)
                ->where('id', $logId)
                ->first();

            if (!$timeLog) {
                return ApiResponse::notFound('Registro de tiempo no encontrado');
            }

            $timeLog->delete();

            DB::commit();

            return ApiResponse::success(null, 'Registro de tiempo removido exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al remover registro: ' . $e->getMessage(), 500);
        }
    }

    // ========================================
    // GESTIÓN DE ARCHIVOS ADJUNTOS
    // ========================================

    public function getAttachments(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with(['attachments.uploadedBy'])
            ->forCompany($companyId)
            ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        return ApiResponse::success($workOrder->attachments);
    }

    public function uploadAttachments(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'attachments' => 'required|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            'attachment_type' => 'required|in:photo_before,photo_during,photo_after,document,signature,other',
            'description' => 'nullable|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $uploadedFiles = [];

            foreach ($request->file('attachments') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $filePath = $file->storeAs("work_orders/{$workOrder->id}/attachments", $fileName, 'public');

                $attachment = \App\Models\WorkOrderAttachment::create([
                    'work_order_id' => $workOrder->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                    'attachment_type' => $request->attachment_type,
                    'uploaded_by' => $user->id,
                    'uploaded_at' => now(),
                    'description' => $request->description,
                ]);

                $uploadedFiles[] = $attachment;
            }

            DB::commit();

            return ApiResponse::created($uploadedFiles, 'Archivos subidos exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al subir archivos: ' . $e->getMessage(), 500);
        }
    }

    public function destroyAttachment(Request $request, int $id, int $attachmentId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $attachment = \App\Models\WorkOrderAttachment::where('work_order_id', $id)
                ->where('id', $attachmentId)
                ->first();

            if (!$attachment) {
                return ApiResponse::notFound('Archivo no encontrado');
            }

            // Eliminar archivo físico
            Storage::disk('public')->delete($attachment->file_path);

            $attachment->delete();

            DB::commit();

            return ApiResponse::success(null, 'Archivo eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al eliminar archivo: ' . $e->getMessage(), 500);
        }
    }

    // ========================================
    // GESTIÓN DE CHECKLIST
    // ========================================

    public function getChecklist(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with(['checklistItems.checkedBy'])
            ->forCompany($companyId)
            ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        return ApiResponse::success($workOrder->checklistItems);
    }

    public function addChecklistItem(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'item_text'  => 'required|string|max:500',
            'is_required' => 'boolean',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $companyId = $request->header('x-company-id');
        $workOrder = WorkOrder::forCompany($companyId)->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        $nextOrder = $workOrder->checklistItems()->max('display_order') + 1;

        $item = WorkOrderChecklistItem::create([
            'work_order_id' => $workOrder->id,
            'item_text'     => $request->item_text,
            'is_required'   => $request->boolean('is_required', false),
            'is_checked'    => false,
            'display_order' => $nextOrder,
            'notes'         => $request->notes,
        ]);

        return ApiResponse::success($item, 'Ítem agregado exitosamente', 201);
    }

    public function toggleChecklistItem(Request $request, int $id, int $itemId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $item = WorkOrderChecklistItem::where('work_order_id', $id)
                ->where('id', $itemId)
                ->first();

            if (!$item) {
                return ApiResponse::notFound('Item de checklist no encontrado');
            }

            if ($item->is_checked) {
                $item->uncheck();
            } else {
                $item->check($user->id);
            }

            DB::commit();

            return ApiResponse::success($item, 'Item actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar item: ' . $e->getMessage(), 500);
        }
    }




    public function updateChecklistItemText(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate(["item_text" => "required|string|max:500"]);
        DB::beginTransaction();
        try {
            $companyId = $request->header("x-company-id");
            $workOrder = WorkOrder::forCompany($companyId)->find($id);
            if (!$workOrder) return ApiResponse::notFound("Orden de trabajo no encontrada");
            $item = WorkOrderChecklistItem::where("work_order_id", $id)->where("id", $itemId)->first();
            if (!$item) return ApiResponse::notFound("Item de checklist no encontrado");
            $item->item_text = $request->item_text;
            $item->save();
            DB::commit();
            return ApiResponse::success($item, "Item actualizado exitosamente");
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error("Error al actualizar item: " . $e->getMessage(), 500);
        }
    }

    public function deleteChecklistItem(Request $request, int $id, int $itemId): JsonResponse
    {
        DB::beginTransaction();
        try {
            $companyId = $request->header("x-company-id");
            $workOrder = WorkOrder::forCompany($companyId)->find($id);
            if (!$workOrder) return ApiResponse::notFound("Orden de trabajo no encontrada");
            $item = WorkOrderChecklistItem::where("work_order_id", $id)->where("id", $itemId)->first();
            if (!$item) return ApiResponse::notFound("Item de checklist no encontrado");
            $item->delete();
            DB::commit();
            return ApiResponse::success(null, "Item eliminado exitosamente");
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error("Error al eliminar item: " . $e->getMessage(), 500);
        }
    }

    public function updateChecklistNotes(Request $request, int $id, int $itemId): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            $item = WorkOrderChecklistItem::where('work_order_id', $id)
                ->where('id', $itemId)
                ->first();

            if (!$item) {
                return ApiResponse::notFound('Item de checklist no encontrado');
            }

            $item->notes = $request->notes;
            $item->save();

            DB::commit();

            return ApiResponse::success($item, 'Notas actualizadas exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar notas: ' . $e->getMessage(), 500);
        }
    }

    // ========================================
    // GESTIÓN DE COMENTARIOS
    // ========================================

    public function getComments(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with(['comments.user', 'comments.replies.user'])
            ->forCompany($companyId)
            ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        return ApiResponse::success($workOrder->comments()->topLevel()->recent()->get());
    }

    public function storeComment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'comment' => 'required|string',
            'is_internal' => 'boolean',
            'parent_id' => 'nullable|integer|exists:work_order_comments,id',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            if ($workOrder->status === WorkOrder::STATUS_VALIDATED) {
                return ApiResponse::error('No se pueden agregar o modificar comentarios en una orden ya validada.', 422);
            }
            $comment = \App\Models\WorkOrderComment::create([
                'work_order_id' => $workOrder->id,
                'user_id' => $user->id,
                'parent_id' => $request->parent_id,
                'comment' => $request->comment,
                'is_internal' => $request->is_internal ?? false,
            ]);

            DB::commit();

            $comment->load('user');

            return ApiResponse::created($comment, 'Comentario agregado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al agregar comentario: ' . $e->getMessage(), 500);
        }
    }

    public function updateComment(Request $request, int $id, int $commentId): JsonResponse
    {
        $request->validate([
            'comment' => 'required|string',
        ]);

        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            if ($workOrder->status === WorkOrder::STATUS_VALIDATED) {
                return ApiResponse::error('No se pueden agregar o modificar comentarios en una orden ya validada.', 422);
            }

            $comment = \App\Models\WorkOrderComment::where('work_order_id', $id)
                ->where('id', $commentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$comment) {
                return ApiResponse::notFound('Comentario no encontrado o no tiene permiso para editarlo');
            }
            $comment->comment = $request->comment;
            $comment->save();

            DB::commit();

            return ApiResponse::success($comment, 'Comentario actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar comentario: ' . $e->getMessage(), 500);
        }
    }

    public function destroyComment(Request $request, int $id, int $commentId): JsonResponse
    {
        DB::beginTransaction();

        try {
            $companyId = $request->header('x-company-id');
            $user = Auth::user();

            $workOrder = WorkOrder::forCompany($companyId)->find($id);

            if (!$workOrder) {
                return ApiResponse::notFound('Orden de trabajo no encontrada');
            }

            if ($workOrder->status === WorkOrder::STATUS_VALIDATED) {
                return ApiResponse::error('No se pueden agregar o modificar comentarios en una orden ya validada.', 422);
            }

            $comment = \App\Models\WorkOrderComment::where('work_order_id', $id)
                ->where('id', $commentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$comment) {
                return ApiResponse::notFound('Comentario no encontrado o no tiene permiso para eliminarlo');
            }
            $comment->delete();

            DB::commit();

            return ApiResponse::success(null, 'Comentario eliminado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al eliminar comentario: ' . $e->getMessage(), 500);
        }
    }

    // ========================================
    // HISTORIAL DE ESTADOS
    // ========================================

    public function getStatusHistory(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with(['statusHistory.changedBy'])
            ->forCompany($companyId)
            ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        return ApiResponse::success($workOrder->statusHistory);
    }

    // ========================================
    // VISTA DE ALMACÉN - TODOS LOS MATERIALES
    // ========================================

    /**
     * Obtener todos los materiales de órdenes de trabajo con filtros
     * Útil para que el almacenista vea todas las solicitudes pendientes
     */
    public function getAllMaterials(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $validator = Validator::make($request->all(), [
            'material_status' => 'nullable|in:planned,requested,approved,delivered,in_use,consumed,returned,received,completed',
            'work_order_id' => 'nullable|integer|exists:work_orders,id',
            'warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'material_id' => 'nullable|integer|exists:materials,id',
            'search' => 'nullable|string|max:255',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = \App\Models\WorkOrderMaterial::query()
            ->whereHas('workOrder', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->with([
                'workOrder:id,code,title,status,priority,asset_id,assigned_to',
                'workOrder.asset:id,name,code',
                'workOrder.assignedTo:id,first_name,last_name,email',
                'material:id,name,code,sku,unit_of_measure',
                'material.category:id,name',
                'material.stock.warehouse:id,name,code',
                'warehouse:id,name,code',
                'requestedBy:id,first_name,last_name,email',
                'approvedBy:id,first_name,last_name,email',
                'deliveredBy:id,first_name,last_name,email',
                'consumedBy:id,first_name,last_name,email',
                'returnedBy:id,first_name,last_name,email',
                'receivedBy:id,first_name,last_name,email',
            ]);

        // Filtro por estado del material
        if ($request->filled('material_status')) {
            $query->where('material_status', $request->material_status);
        }

        // Filtro por orden de trabajo específica
        if ($request->filled('work_order_id')) {
            $query->where('work_order_id', $request->work_order_id);
        }

        // Filtro por almacén
        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filtro por material específico
        if ($request->filled('material_id')) {
            $query->where('material_id', $request->material_id);
        }

        // Búsqueda por nombre de material o código de orden
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('material', function ($mq) use ($search) {
                    $mq->where('name', 'like', "%{$search}%")
                       ->orWhere('code', 'like', "%{$search}%")
                       ->orWhere('sku', 'like', "%{$search}%");
                })
                ->orWhereHas('workOrder', function ($woq) use ($search) {
                    $woq->where('code', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            });
        }

        // Ordenamiento: Priorizar por fecha de solicitud (más recientes primero)
        $query->orderBy('requested_at', 'desc')
              ->orderBy('created_at', 'desc');

        // Paginación
        $perPage = $request->get('per_page', 15);
        $materials = $query->paginate($perPage);

        // Transformar datos
        $transformedMaterials = $materials->getCollection()->map(function ($material) {
            return [
                'id' => $material->id,
                'work_order_id' => $material->work_order_id,
                'material_id' => $material->material_id,
                'warehouse_id' => $material->warehouse_id,
                'material_status' => $material->material_status,
                'quantity_planned' => $material->quantity_planned,
                'quantity_requested' => $material->quantity_requested,
                'quantity_approved' => $material->quantity_approved,
                'quantity_delivered' => $material->quantity_delivered,
                'quantity_consumed' => $material->quantity_consumed,
                'quantity_returned' => $material->quantity_returned,
                'unit' => $material->unit,
                'unit_cost' => $material->unit_cost,
                'total_cost' => $material->quantity_planned * $material->unit_cost,
                'request_notes' => $material->request_notes,
                'approval_notes' => $material->approval_notes,
                'delivery_notes' => $material->delivery_notes,
                'consumption_notes' => $material->consumption_notes,
                'return_notes' => $material->return_notes,
                'requested_at' => $material->requested_at,
                'approved_at' => $material->approved_at,
                'delivered_at' => $material->delivered_at,
                'consumed_at' => $material->consumed_at,
                'returned_at' => $material->returned_at,
                'created_at' => $material->created_at,
                'updated_at' => $material->updated_at,
                
                'warehouse' => [
                    'id' => $material->warehouse->id,
                    'code' => $material->warehouse->code,
                    'name' => $material->warehouse->name,
                ],
                
                'material' => [
                    'id' => $material->material->id,
                    'code' => $material->material->code,
                    'name' => $material->material->name,
                    'sku' => $material->material->sku,
                    'unit' => $material->material->unit_of_measure,
                    'category' => $material->material->category ? [
                        'id' => $material->material->category->id,
                        'name' => $material->material->category->name,
                    ] : null,
                    'stock' => $material->material->stock->map(function ($stock) {
                        return [
                            'warehouse_id' => $stock->warehouse_id,
                            'quantity' => $stock->quantity,
                            'location' => $stock->location,
                            'warehouse' => [
                                'id' => $stock->warehouse->id,
                                'code' => $stock->warehouse->code,
                                'name' => $stock->warehouse->name,
                            ],
                        ];
                    }),
                ],
                
                'work_order' => [
                    'id' => $material->workOrder->id,
                    'code' => $material->workOrder->code,
                    'title' => $material->workOrder->title,
                    'status' => $material->workOrder->status,
                    'priority' => $material->workOrder->priority,
                    'asset' => $material->workOrder->asset ? [
                        'id' => $material->workOrder->asset->id,
                        'name' => $material->workOrder->asset->name,
                        'code' => $material->workOrder->asset->code,
                    ] : null,
                    'assigned_to' => $material->workOrder->assignedTo ? [
                        'id' => $material->workOrder->assignedTo->id,
                        'name' => $material->workOrder->assignedTo->full_name,
                        'email' => $material->workOrder->assignedTo->email,
                    ] : null,
                ],
                
                'requested_by' => $material->requestedBy ? [
                    'id' => $material->requestedBy->id,
                    'name' => $material->requestedBy->full_name,
                    'email' => $material->requestedBy->email,
                ] : null,
                'approved_by' => $material->approvedBy ? [
                    'id' => $material->approvedBy->id,
                    'name' => $material->approvedBy->full_name,
                    'email' => $material->approvedBy->email,
                ] : null,
                'delivered_by' => $material->deliveredBy ? [
                    'id' => $material->deliveredBy->id,
                    'name' => $material->deliveredBy->full_name,
                    'email' => $material->deliveredBy->email,
                ] : null,
            ];
        });

        return ApiResponse::paginated(
            $transformedMaterials,
            [
                'current_page' => $materials->currentPage(),
                'last_page' => $materials->lastPage(),
                'per_page' => $materials->perPage(),
                'total' => $materials->total(),
                'from' => $materials->firstItem(),
                'to' => $materials->lastItem(),
            ],
            'Materiales de órdenes de trabajo recuperados exitosamente'
        );
    }

    /**
     * Sincroniza el técnico asignado (assigned_to) con la tabla de assignments
     * Si el técnico cambia o se asigna por primera vez, crea un assignment con rol 'technician'
     */
    private function syncAssignedTechnician(WorkOrder $workOrder, ?int $oldAssignedTo, int $currentUserId): void
    {
        $newAssignedTo = $workOrder->assigned_to;

        // Si hay un nuevo técnico asignado y es diferente al anterior
        if ($newAssignedTo && $newAssignedTo !== $oldAssignedTo) {
            // Verificar si ya existe un assignment para este técnico en esta orden
            $existingAssignment = WorkOrderAssignment::where('work_order_id', $workOrder->id)
                ->where('user_id', $newAssignedTo)
                ->where('role', WorkOrderAssignment::ROLE_TECHNICIAN)
                ->first();

            // Solo crear si no existe
            if (!$existingAssignment) {
                WorkOrderAssignment::create([
                    'work_order_id' => $workOrder->id,
                    'user_id' => $newAssignedTo,
                    'role' => WorkOrderAssignment::ROLE_TECHNICIAN,
                    'assigned_by' => $currentUserId,
                    'assigned_at' => now(),
                    'notes' => 'Asignado automáticamente como técnico principal',
                ]);
            }
        }
    }

    /**
     * Generar PDF de una orden de trabajo
     */
    public function generatePdf(Request $request, int $id)
    {
        $companyId = $request->header('x-company-id');

        $workOrder = WorkOrder::with([
            'company',
            'asset.category',
            'asset.companySite',
            'assignedTo',
            'assignedBy',
            'completedBy',
            'validatedBy',
            'cancelledBy',
            'assignments.user',
            'timeLogs.user',
            'checklistItems.checkedBy',
            'workRequest',
        ])
        ->forCompany($companyId)
        ->find($id);

        if (!$workOrder) {
            return ApiResponse::notFound('Orden de trabajo no encontrada');
        }

        try {
            $logoBase64 = $this->getLogoBase64();
            $pdf = Pdf::loadView('pdf.work-order', [
                'workOrder'  => $workOrder,
                'logoBase64' => $logoBase64,
            ]);
            $filename = "OT_{$workOrder->code}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al generar el PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtiene el logo del sistema como base64 para embeber en PDFs.
     */
    private function getLogoBase64(): ?string
    {
        $path = public_path('logo-recylo.png');
        if (!file_exists($path)) return null;
        $mime = mime_content_type($path);
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = (int) $request->header('x-company-id');

        $export = new WorkOrderExport(
            companyId: $companyId,
            from:      $request->query('from'),
            to:        $request->query('to'),
            status:    $request->query('status'),
            type:      $request->query('type'),
        );

        return $export->download('ordenes_trabajo_' . now()->format('Y-m-d') . '.xlsx');
    }
}
