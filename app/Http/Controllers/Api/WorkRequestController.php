<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkRequestRequest;
use App\Http\Requests\StorePublicWorkRequestRequest;
use App\Http\Requests\UpdateWorkRequestRequest;
use App\Http\Requests\ApproveWorkRequestRequest;
use App\Http\Requests\RejectWorkRequestRequest;
use App\Http\Resources\WorkRequestResource;
use App\Http\Resources\WorkRequestCollection;
use App\Http\Responses\ApiResponse;
use App\Models\WorkRequest;
use App\Models\WorkRequestAttachment;
use App\Models\WorkRequestStatusHistory;
use App\Models\WorkRequestNotification;
use App\Models\Company;
use App\Models\Asset;
use App\Models\User;
use App\Services\NotificationDispatcher;
use App\Services\QRCodeService;
use App\Services\AssetActivityService;
use App\Exports\WorkRequestExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WorkRequestController extends Controller
{
    protected $qrCodeService;
    protected $activityService;

    public function __construct(QRCodeService $qrCodeService, AssetActivityService $activityService)
    {
        $this->qrCodeService = $qrCodeService;
        $this->activityService = $activityService;
    }
    /**
     * Listar solicitudes de trabajo con filtros y paginación
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Obtener company_id del header
        $companyId = $request->header('x-company-id');
        
        // Validar company existe
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Construir query con relaciones
        $query = WorkRequest::forCompany($companyId)
            ->with([
                'asset.category',
                'requester',
                'approvedBy',
                'rejectedBy',
                'tags',
                'workOrder:id,code,status,priority,assigned_to,completed_at,validated_at',
                'workOrder.assignedTo:id,first_name,last_name,email',
            ]);

        // Visibilidad por rol: sin VIEW_ALL, ver propias + sin asignar + pendientes/en_revision
        $user = Auth::user();
        // if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_REQUESTS_VIEW_ALL', (int) $companyId)) {
        //     $pendingStatuses = ['pending', 'under_review'];
        //     $query->where(function ($q) use ($user, $pendingStatuses) {
        //         $q->where('requester_id', $user->id)
        //           ->orWhere('created_by', $user->id)
        //           ->orWhereNull('requester_id')
        //           ->orWhereIn('status', $pendingStatuses);
        //     });
        // }

        // Aplicar filtros opcionales
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

        if ($request->filled('request_type')) {
            $query->byType($request->request_type);
        }

        if ($request->filled('asset_id')) {
            $query->forAsset($request->asset_id);
        }

        if ($request->filled('requester_id')) {
            $query->requestedBy($request->requester_id);
        }

        if ($request->boolean('only_overdue')) {
            $query->overdue();
        }

        if ($request->boolean('only_sla_breached')) {
            $query->slaBreached();
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $workRequests = $query->paginate($perPage);

        // Verificar SLA para cada solicitud en el listado
        $workRequests->getCollection()->transform(function ($workRequest) {
            $workRequest->checkSlaStatus();
            if ($workRequest->isDirty('sla_breached')) {
                $workRequest->save();
            }
            return $workRequest;
        });

        return response()->json(
            new WorkRequestCollection($workRequests)
        );
    }

    /**
     * Mostrar una solicitud de trabajo específica
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        // Buscar solicitud con todas las relaciones
        $workRequest = WorkRequest::with([
            'company',
            'asset.category',
            'asset.productionLine',
            'requester',
            'approvedBy',
            'rejectedBy',
            'createdBy',
            'updatedBy',
            'attachments.uploadedBy',
            'comments.user',
            'tags',
            'watchers.user',
            'checklistItems.checkedBy',
            'statusHistory.changedBy',
            'relatedRequests.relatedRequest',
            'workOrder',
            'workOrder.assignedTo',
            'workOrder.assignedBy',
            'workOrder.asset',
        ])
        ->where('company_id', $companyId)
        ->find($id);

        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud de trabajo no encontrada');
        }

        // Verificar y actualizar estado de SLA
        $workRequest->checkSlaStatus();
        if ($workRequest->isDirty('sla_breached')) {
            $workRequest->save();
        }

        return response()->json([
            'success' => true,
            'data' => new WorkRequestResource($workRequest),
            'message' => 'Solicitud recuperada exitosamente',
        ]);
    }

    /**
     * Crear nueva solicitud de trabajo
     * 
     * @param StoreWorkRequestRequest $request
     * @return JsonResponse
     */
    public function store(StoreWorkRequestRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $companyId = $request->header('x-company-id');
            $userId = $request->user()->id;

            // Generar código único
            $code = WorkRequest::generateCode($companyId);

            // Auto-generar título si no se proporcionó
            $title = $request->title
                ? $request->title
                : substr(strip_tags($request->description), 0, 120);

            // Solicitante: puede ser diferente al usuario autenticado (planificador crea en nombre de alguien)
            $requesterId = $request->requester_id ?? $userId;

            // Preparar datos de la solicitud
            $workRequestData = [
                'company_id'       => $companyId,
                'asset_id'         => $request->asset_id,
                'code'             => $code,
                'title'            => $title,
                'description'      => $request->description,
                'request_type'     => $request->request_type,
                'priority'         => $request->priority,
                'equipment_status' => $request->equipment_status,
                'status'           => 'pending',
                'requester_id'     => $requesterId,
                'created_by'       => $userId,
                'updated_by'       => $userId,
            ];

            // Crear solicitud
            $workRequest = WorkRequest::create($workRequestData);

            // Calcular SLA automáticamente
            $workRequest->calculateSlaDeadlines();
            $workRequest->save();

            // Aplicar checklist template si corresponde
            $workRequest->applyChecklistTemplate();

            // Generar QR code automáticamente
            try {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                $qrUrl = $frontendUrl . '/solicitudes/' . $workRequest->code;
                $qrCodeUrl = $this->qrCodeService->generateAndUpload($qrUrl, 'work-requests');
                
                $workRequest->qr_code_url = $qrCodeUrl;
                $workRequest->qr_code_generated_at = now();
                $workRequest->save();
            } catch (\Exception $e) {
                Log::warning('No se pudo generar QR para solicitud ' . $workRequest->code . ': ' . $e->getMessage());
            }

            // Procesar archivos adjuntos
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('work-requests/' . $workRequest->id, $filename, 'public');

                    WorkRequestAttachment::create([
                        'work_request_id' => $workRequest->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $userId,
                    ]);
                }
            }

            // Asignar etiquetas
            if ($request->filled('tags')) {
                $workRequest->tags()->attach($request->tags, [
                    'assigned_by' => $userId,
                    'assigned_at' => now(),
                ]);
            }

            // Registrar en historial
            WorkRequestStatusHistory::record(
                $workRequest->id,
                'created',
                'pending',
                $userId,
                'Solicitud creada'
            );

            DB::commit();

            NotificationDispatcher::workRequest($workRequest, 'created');

            // � Registrar actividad de nueva solicitud
            $this->activityService->logWorkRequestCreated($workRequest);

            // Cargar relaciones para respuesta
            $workRequest->load([
                'asset.category',
                'requester',
                'attachments',
                'tags',
            ]);

            return response()->json([
                'success' => true,
                'data' => new WorkRequestResource($workRequest),
                'message' => 'Solicitud de trabajo creada correctamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al crear la solicitud de trabajo: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Actualizar solicitud de trabajo existente
     * Solo permitido si está en estado 'pending'
     * 
     * @param UpdateWorkRequestRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateWorkRequestRequest $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId = $request->user()->id;

        // Buscar solicitud
        $workRequest = WorkRequest::where('company_id', $companyId)->find($id);
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud de trabajo no encontrada');
        }

        // Cargar la OT vinculada para evaluar permisos de edición
        $workRequest->load('workOrder');

        // Validar que puede ser editada
        if (!$workRequest->can_be_edited) {
            $reason = ($workRequest->status === 'approved' && $workRequest->work_order_id)
                ? 'No se puede editar: la orden de trabajo vinculada ya fue completada, validada o cancelada'
                : 'La solicitud no se puede editar en su estado actual';
            return ApiResponse::error($reason, 400);
        }

        try {
            DB::beginTransaction();

            // Actualizar campos enviados
            $updateData = $request->only([
                'asset_id',
                'title',
                'description',
                'request_type',
                'priority',
                'estimated_cost',
                'estimated_hours',
            ]);
            $updateData['updated_by'] = $userId;

            $workRequest->update($updateData);

            // Recalcular SLA si cambió la prioridad
            if ($request->filled('priority')) {
                $workRequest->calculateSlaDeadlines();
                $workRequest->save();
            }

            // ── Propagación a la OT vinculada ─────────────────────────────────
            $woUpdated = false;
            if ($workRequest->work_order_id && $workRequest->workOrder) {
                $wo = $workRequest->workOrder;
                // Solo propagar si la OT no ha comenzado aún
                if (in_array($wo->status, ['pending', 'scheduled'])) {
                    $woPropagation = [];
                    if ($request->filled('title'))       $woPropagation['title']           = $request->title;
                    if ($request->filled('description')) $woPropagation['description']     = $request->description;
                    if ($request->filled('priority'))    $woPropagation['priority']        = $request->priority;
                    if ($request->filled('asset_id'))    $woPropagation['asset_id']        = $request->asset_id;
                    if (!empty($woPropagation)) {
                        $woPropagation['updated_by'] = $userId;
                        $wo->update($woPropagation);
                        $woUpdated = true;
                    }
                }
            }

            // Procesar archivos adjuntos adicionales
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('work-requests/' . $workRequest->id, $filename, 'public');

                    WorkRequestAttachment::create([
                        'work_request_id' => $workRequest->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => $userId,
                    ]);
                }
            }

            // Actualizar etiquetas (reemplaza las existentes)
            if ($request->has('tags')) {
                $workRequest->tags()->sync(
                    collect($request->tags)->mapWithKeys(function ($tagId) use ($userId) {
                        return [$tagId => [
                            'assigned_by' => $userId,
                            'assigned_at' => now(),
                        ]];
                    })
                );
            }

            DB::commit();

            $workRequest->load([
                'asset.category',
                'requester',
                'attachments',
                'tags',
            ]);

            $message = $woUpdated
                ? 'Solicitud actualizada exitosamente. Los cambios también se aplicaron a la orden de trabajo vinculada.'
                : 'Solicitud actualizada exitosamente';

            return response()->json([
                'success' => true,
                'data' => new WorkRequestResource($workRequest),
                'message' => $message,
                'wo_updated' => $woUpdated,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al actualizar la solicitud: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Eliminar solicitud de trabajo (soft delete)
     * Solo permitido si está en estado 'pending'
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $user = Auth::user();

        // ── Verificar permiso admin ────────────────────────────────────────────
        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_REQUESTS_DELETE_ADMIN', $companyId)) {
            return ApiResponse::error('No tienes permiso para eliminar solicitudes de trabajo', 403);
        }

        // Buscar solicitud
        $workRequest = WorkRequest::where('company_id', $companyId)->find($id);
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud de trabajo no encontrada');
        }

        DB::beginTransaction();

        try {
            // ── Soft-delete de la OT vinculada (si existe) ────────────────────
            if ($workRequest->work_order_id) {
                $relatedOrder = \App\Models\WorkOrder::forCompany($companyId)
                    ->find($workRequest->work_order_id);

                if ($relatedOrder) {
                    $relatedOrder->deleted_by = $user->id;
                    $relatedOrder->save();
                    $relatedOrder->delete();

                    Log::info('Orden de trabajo eliminada en cascada desde solicitud', [
                        'work_order_id'   => $relatedOrder->id,
                        'work_request_id' => $id,
                        'deleted_by'      => $user->id,
                    ]);
                }
            }

            // ── Soft-delete de la solicitud ───────────────────────────────────
            $workRequest->delete();

            DB::commit();

            Log::info('Solicitud de trabajo eliminada', [
                'work_request_id' => $id,
                'deleted_by'      => $user->id,
            ]);

            return ApiResponse::success(null, 'Solicitud eliminada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error al eliminar solicitud de trabajo', [
                'work_request_id' => $id,
                'error'           => $e->getMessage(),
            ]);

            return ApiResponse::error(
                'Error al eliminar la solicitud: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Aprobar solicitud de trabajo
     * Cambia estado a 'approved' y opcionalmente crea Work Order
     * 
     * @param ApproveWorkRequestRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function approve(ApproveWorkRequestRequest $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId = $request->user()->id;

        // Buscar solicitud
        $workRequest = WorkRequest::where('company_id', $companyId)->find($id);
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud de trabajo no encontrada');
        }

        // Validar que puede ser aprobada
        if (!$workRequest->can_be_approved) {
            return ApiResponse::error(
                'La solicitud no puede ser aprobada en su estado actual',
                400
            );
        }

        try {
            DB::beginTransaction();

            // Actualizar solicitud
            $workRequest->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
                'actual_cost' => $request->actual_cost,
                'actual_hours' => $request->actual_hours,
                'updated_by' => $userId,
            ]);

            // Registrar primera respuesta si no existe
            $workRequest->recordFirstResponse();

            // Registrar en historial
            WorkRequestStatusHistory::record(
                $workRequest->id,
                $workRequest->getOriginal('status'),
                'approved',
                $userId,
                $request->approval_comment
            );

            // Crear comentario si se proporcionó
            if ($request->filled('approval_comment')) {
                $workRequest->comments()->create([
                    'user_id' => $userId,
                    'comment' => $request->approval_comment,
                    'is_internal' => false,
                ]);
            }

            // Crear Work Order vinculada si se solicitó
            if ($request->boolean('create_work_order') && $request->has('work_order_data')) {
                $woData = $request->input('work_order_data');
                $assignedTo = $woData['assigned_to'] ?? $request->input('assigned_to') ?? $userId;

                $woCode = \App\Models\WorkOrder::generateCode($companyId);

                $workOrder = \App\Models\WorkOrder::create([
                    'company_id'               => $companyId,
                    'work_request_id'          => $workRequest->id,
                    'asset_id'                 => $workRequest->asset_id,
                    'code'                     => $woCode,
                    'title'                    => $workRequest->title,
                    'description'              => $workRequest->description,
                    'work_order_type'          => $workRequest->request_type === 'emergency' ? 'emergency' : 'corrective',
                    'priority'                 => $workRequest->priority,
                    'status'                   => \App\Models\WorkOrder::STATUS_PENDING,
                    'scheduled_start'          => $woData['scheduled_date'] ?? now(),
                    'estimated_duration_hours' => $workRequest->estimated_hours ?? 1,
                    'assigned_to'              => $assignedTo,
                    'assigned_by'              => $userId,
                    'assigned_at'              => now(),
                    'notes'                    => $woData['notes'] ?? null,
                    'approval_notes_request'   => $request->approval_comment ?? null,
                    'created_by'               => $userId,
                    'updated_by'               => $userId,
                ]);

                $workRequest->work_order_id = $workOrder->id;
                $workRequest->save();

                $workOrder->recordStatusChange(null, \App\Models\WorkOrder::STATUS_PENDING, $userId, 'OT creada desde solicitud ' . $workRequest->code);
            }

            // Notificar al solicitante (solo si tiene requester_id - solicitud interna)
            if ($workRequest->requester_id) {
                WorkRequestNotification::createNotification(
                    $workRequest->id,
                    $workRequest->requester_id,
                    'approved',
                    'Solicitud Aprobada',
                    "Tu solicitud {$workRequest->code} ha sido aprobada",
                    'in_app'
                );
            }

            DB::commit();

            NotificationDispatcher::workRequest($workRequest, 'approved');

            // Registrar actividad de aprobación
            $this->activityService->logWorkRequestApproved($workRequest);

            $workRequest->load([
                'asset.category',
                'requester',
                'approvedBy',
                'tags',
            ]);

            return response()->json([
                'success' => true,
                'data' => new WorkRequestResource($workRequest),
                'message' => 'Solicitud aprobada exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al aprobar la solicitud: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Rechazar solicitud de trabajo
     * Cambia estado a 'rejected' con motivo
     * 
     * @param RejectWorkRequestRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function reject(RejectWorkRequestRequest $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId = $request->user()->id;

        // Buscar solicitud
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud de trabajo no encontrada');
        }

        // Validar que puede ser rechazada
        if (!$workRequest->can_be_rejected) {
            return ApiResponse::error(
                'La solicitud no puede ser rechazada en su estado actual',
                400
            );
        }

        try {
            DB::beginTransaction();

            // Actualizar solicitud
            $workRequest->update([
                'status' => 'rejected',
                'rejected_by' => $userId,
                'rejected_at' => now(),
                'rejection_reason' => $request->rejection_reason,
                'updated_by' => $userId,
            ]);

            // Registrar primera respuesta si no existe
            $workRequest->recordFirstResponse();

            // Registrar en historial
            WorkRequestStatusHistory::record(
                $workRequest->id,
                $workRequest->getOriginal('status'),
                'rejected',
                $userId,
                $request->rejection_reason,
                ['suggestions' => $request->suggestions]
            );

            // Crear comentario con motivo y sugerencias
            $commentText = "**Solicitud Rechazada**\n\n";
            $commentText .= "**Motivo:** {$request->rejection_reason}\n\n";
            if ($request->filled('suggestions')) {
                $commentText .= "**Sugerencias:** {$request->suggestions}";
            }

            $workRequest->comments()->create([
                'user_id' => $userId,
                'comment' => $commentText,
                'is_internal' => false,
            ]);

            // Notificar al solicitante si se indicó
            if ($request->boolean('notify_requester')) {
                WorkRequestNotification::createNotification(
                    $workRequest->id,
                    $workRequest->requester_id,
                    'rejected',
                    'Solicitud Rechazada',
                    "Tu solicitud {$workRequest->code} ha sido rechazada",
                    'in_app'
                );
            }

            DB::commit();

            NotificationDispatcher::workRequest($workRequest, 'rejected');

            $workRequest->load([
                'asset.category',
                'requester',
                'rejectedBy',
                'tags',
            ]);

            return response()->json([
                'success' => true,
                'data' => new WorkRequestResource($workRequest),
                'message' => 'Solicitud rechazada exitosamente',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al rechazar la solicitud: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Obtener estadísticas de solicitudes de trabajo
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $stats = [
            'by_status' => WorkRequest::forCompany($companyId)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->get()
                ->pluck('count', 'status'),
            
            'by_priority' => WorkRequest::forCompany($companyId)
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->get()
                ->pluck('count', 'priority'),
            
            'by_type' => WorkRequest::forCompany($companyId)
                ->select('request_type', DB::raw('count(*) as count'))
                ->groupBy('request_type')
                ->get()
                ->pluck('count', 'request_type'),
            
            'overdue' => WorkRequest::forCompany($companyId)->overdue()->count(),
            'sla_breached' => WorkRequest::forCompany($companyId)->slaBreached()->count(),
            'pending_approval' => WorkRequest::forCompany($companyId)
                ->whereIn('status', ['pending', 'under_review'])
                ->count(),
            
            'total' => WorkRequest::forCompany($companyId)->count(),
        ];

        return ApiResponse::success($stats, 'Estadísticas recuperadas exitosamente');
    }

    /**
     * Obtener información pública del activo para formulario de solicitud
     * Endpoint público para acceso desde QR code
     * Incluye solicitudes activas del activo para evitar duplicados
     * 
     * @param string $assetCode
     * @return JsonResponse
     */
    public function getAssetInfo(string $assetCode): JsonResponse
    {
        // Buscar activo por código
        $asset = Asset::with(['category', 'companySite', 'company'])
            ->where('code', $assetCode)
            ->where('is_active', true)
            ->first();

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado o inactivo');
        }

        // Obtener solicitudes activas del activo (excluir completadas, rechazadas, canceladas)
        $activeRequests = WorkRequest::where('asset_id', $asset->id)
            ->whereNotIn('status', ['completed', 'rejected', 'cancelled'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($request) {
                return [
                    'code' => $request->code,
                    'title' => $request->title,
                    'status' => $request->status,
                    'status_label' => $this->getStatusLabel($request->status),
                    'priority' => $request->priority,
                    'priority_label' => $this->getPriorityLabel($request->priority),
                    'request_type' => $request->request_type,
                    'type_label' => $this->getRequestTypeLabel($request->request_type),
                    'created_at' => $request->created_at->toISOString(),
                    'created_ago' => $request->created_at->diffForHumans(),
                    'sla_deadline' => $request->sla_deadline ? $request->sla_deadline->toISOString() : null,
                ];
            });

        // Estadísticas rápidas de solicitudes del activo
        $requestStats = [
            'pending' => WorkRequest::where('asset_id', $asset->id)->where('status', 'pending')->count(),
            'under_review' => WorkRequest::where('asset_id', $asset->id)->where('status', 'under_review')->count(),
            'approved' => WorkRequest::where('asset_id', $asset->id)->where('status', 'approved')->count(),
            'in_progress' => WorkRequest::where('asset_id', $asset->id)->where('status', 'in_progress')->count(),
        ];

        // Retornar información completa
        return ApiResponse::success([
            'asset' => [
                'id' => $asset->id,
                'code' => $asset->code,
                'name' => $asset->name,
                'category' => $asset->category ? $asset->category->name : null,
                'location' => $asset->location_path,
                'site' => $asset->companySite ? $asset->companySite->name : null,
            ],
            'company' => [
                'id' => $asset->company_id,
                'name' => $asset->company->name,
            ],
            'active_requests' => $activeRequests,
            'request_stats' => $requestStats,
            'request_types' => [
                ['value' => 'corrective', 'label' => 'Correctivo'],
                ['value' => 'preventive', 'label' => 'Preventivo'],
                ['value' => 'improvement', 'label' => 'Mejora'],
                ['value' => 'inspection', 'label' => 'Inspección'],
            ],
            'priorities' => [
                ['value' => 'low', 'label' => 'Baja'],
                ['value' => 'medium', 'label' => 'Media'],
                ['value' => 'high', 'label' => 'Alta'],
                ['value' => 'critical', 'label' => 'Crítica'],
            ],
        ], 'Información del activo recuperada exitosamente');
    }

    /**
     * Helper para obtener etiqueta de estado en español
     */
    private function getStatusLabel(string $status): string
    {
        $labels = [
            'pending' => 'Pendiente',
            'under_review' => 'En Revisión',
            'approved' => 'Aprobada',
            'rejected' => 'Rechazada',
            'in_progress' => 'En Progreso',
            'on_hold' => 'En Espera',
            'completed' => 'Completada',
            'cancelled' => 'Cancelada',
        ];

        return $labels[$status] ?? $status;
    }

    /**
     * Helper para obtener etiqueta de prioridad en español
     */
    private function getPriorityLabel(string $priority): string
    {
        $labels = [
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            'critical' => 'Crítica',
        ];

        return $labels[$priority] ?? $priority;
    }

    /**
     * Helper para obtener etiqueta de tipo de solicitud en español
     */
    private function getRequestTypeLabel(string $type): string
    {
        $labels = [
            'corrective' => 'Correctivo',
            'preventive' => 'Preventivo',
            'improvement' => 'Mejora',
            'inspection' => 'Inspección',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Crear solicitud de trabajo desde formulario público (QR Code)
     * Endpoint público sin autenticación
     * 
     * @param StorePublicWorkRequestRequest $request
     * @return JsonResponse
     */
    /**
     * Retorna la lista de usuarios activos de una empresa para el formulario público.
     * Sólo expone id, nombre, site_id, production_line_id — sin datos sensibles.
     */
    public function getPublicUsers(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        $query = DB::table('users')
            ->join('user_companies', 'users.id', '=', 'user_companies.user_id')
            ->join('user_roles', 'users.id', '=', 'user_roles.user_id')
            ->join('roles', 'user_roles.role_id', '=', 'roles.id')
            ->leftJoin('production_lines', 'user_companies.production_line_id', '=', 'production_lines.id')
            ->where('user_companies.status', 'active')
            ->where('users.status', 'active')
            ->whereIn('roles.code', ['EMPLOYEE', 'OPERATOR'])
            ->select(
                'users.id',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS name"),
                'user_companies.company_id',
                'user_companies.site_id',
                'user_companies.production_line_id',
                'user_companies.job_position',
                'production_lines.name AS production_line_name'
            )
            ->distinct();

        // Filtrar por empresa si se indica
        if ($companyId) {
            $query->where('user_companies.company_id', $companyId);
        }

        return ApiResponse::success($query->get(), 'Usuarios recuperados');
    }

    /**
     * Listado público de activos — acepta filtros opcionales: q, site_id, production_line_id, user_id
     * user_id permite filtrar automáticamente por el site y la línea del usuario.
     */
    public function publicAssetsFiltered(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        // Si no viene company_id pero sí user_id, lo derivamos del pivote
        if (!$companyId && $request->filled('user_id')) {
            $uc = DB::table('user_companies')
                ->where('user_id', $request->user_id)
                ->where('status', 'active')
                ->first();
            $companyId = $uc->company_id ?? null;
        }

        if (!$companyId) {
            return ApiResponse::error('Se requiere company_id o user_id', 422);
        }

        $query = Asset::where('company_id', $companyId)
            ->where('is_active', true)
            ->with(['category:id,name,icon', 'productionLine:id,name', 'companySite:id,name', 'system:id,name']);

        // Filtros opcionales
        if ($request->filled('site_id')) {
            $query->where('company_site_id', $request->site_id);
        }
        if ($request->filled('production_line_id')) {
            $query->where('production_line_id', $request->production_line_id);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sq) use ($q) {
                $sq->where('name', 'like', "%{$q}%")
                   ->orWhere('code', 'like', "%{$q}%");
            });
        }

        $assets = $query->select('id', 'code', 'name', 'company_site_id', 'production_line_id', 'system_id', 'category_id')
            ->orderBy('company_site_id')
            ->orderBy('production_line_id')
            ->orderBy('name')
            ->limit(500)
            ->get()
            ->map(fn($a) => [
                'id'              => $a->id,
                'code'            => $a->code,
                'name'            => $a->name,
                'site'            => $a->companySite?->name,
                'production_line' => $a->productionLine?->name,
                'system'          => $a->system?->name,
                'category'        => $a->category ? [
                    'id'   => $a->category->id,
                    'name' => $a->category->name,
                    'icon' => $a->category->icon,
                ] : null,
            ]);

        return ApiResponse::success($assets, 'Activos recuperados');
    }

    public function storePublic(StorePublicWorkRequestRequest $request): JsonResponse
    {
        // Resolver activo: por asset_code (QR) o asset_id directo
        if ($request->asset_code) {
            $asset = Asset::where('code', $request->asset_code)->first();
        } elseif ($request->asset_id) {
            $asset = Asset::find($request->asset_id);
        } else {
            $asset = null;
        }

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $companyId = $asset->company_id;
        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                // Iniciar transacción ANTES de generar código para que lockForUpdate funcione
                DB::beginTransaction();

                // Generar código único (DEBE estar dentro de la transacción)
                $code = WorkRequest::generateCode($companyId);

                // Resolver solicitante
                $requesterId   = $request->requester_id ?? null;
                $requesterName = $request->requester_name;
                $requesterEmail = $request->requester_email;
                if ($requesterId) {
                    $reqUser = User::find($requesterId);
                    if ($reqUser) {
                        $requesterName  = $requesterName  ?? trim("{$reqUser->first_name} {$reqUser->last_name}");
                        $requesterEmail = $requesterEmail ?? $reqUser->email;
                    }
                }

                // Auto-generar título a partir de la descripción
                $title = $request->title
                    ? $request->title
                    : substr(strip_tags($request->description), 0, 120);

                // Preparar datos de la solicitud
                $workRequestData = [
                    'company_id'       => $companyId,
                    'asset_id'         => $asset->id,
                    'code'             => $code,
                    'title'            => $title,
                    'description'      => $request->description,
                    'request_type'     => $request->request_type,
                    'priority'         => $request->priority,
                    'equipment_status' => $request->equipment_status,
                    'status'           => 'pending',
                    'requester_id'     => $requesterId,
                    'requester_name'   => $requesterName,
                    'requester_email'  => $requesterEmail,
                    'requester_phone'  => $request->requester_phone,
                    'is_public_request' => true,
                ];

                // Crear solicitud
                $workRequest = WorkRequest::create($workRequestData);

            // Calcular SLA automáticamente
            $workRequest->calculateSlaDeadlines();
            $workRequest->save();

            // Aplicar checklist template si corresponde
            $workRequest->applyChecklistTemplate();

            // Generar QR code automáticamente
            try {
                $frontendUrl = env('FRONTEND_URL');
                $qrUrl = $frontendUrl . '/solicitudes/' . $workRequest->code;
                $qrCodeUrl = $this->qrCodeService->generateAndUpload($qrUrl, 'work-requests');
                
                $workRequest->qr_code_url = $qrCodeUrl;
                $workRequest->qr_code_generated_at = now();
                $workRequest->save();
            } catch (\Exception $e) {
                Log::warning('No se pudo generar QR para solicitud pública ' . $workRequest->code . ': ' . $e->getMessage());
            }

            // Procesar archivos adjuntos (máximo 5, más pequeños)
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('work-requests/' . $workRequest->id, $filename, 'public');

                    WorkRequestAttachment::create([
                        'work_request_id' => $workRequest->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'file_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'uploaded_by' => null, // Público, sin usuario
                    ]);
                }
            }

            // Registrar en historial
            WorkRequestStatusHistory::record(
                $workRequest->id,
                null,
                'pending',
                null, // Sin usuario (solicitud pública)
                'Solicitud creada desde formulario público (QR Code)'
            );

            DB::commit();

            // � Registrar actividad de nueva solicitud pública
            $this->activityService->logWorkRequestCreated($workRequest);

            // Cargar relaciones para respuesta
            $workRequest->load([
                'asset.category',
                'asset.companySite',
                'attachments',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'request_code' => $workRequest->code,
                    'message' => 'Su solicitud ha sido registrada exitosamente. En breve recibirá una respuesta al email proporcionado.',
                    'tracking_info' => [
                        'code' => $workRequest->code,
                        'status' => 'pending',
                        'email' => $request->requester_email,
                    ],
                    'pdf_url' => url("/api/public/work-requests/{$workRequest->code}/pdf"),
                ],
                'message' => 'Solicitud de trabajo creada correctamente',
            ], 201);

            } catch (\Illuminate\Database\QueryException $e) {
                DB::rollBack();
                
                // Si es error de código duplicado, reintentar
                if ($e->getCode() === '23000' && str_contains($e->getMessage(), 'Duplicate entry')) {
                    $attempt++;
                    Log::warning("Código duplicado en storePublic (intento {$attempt}/{$maxRetries})", [
                        'company_id' => $companyId,
                        'asset_code' => $request->asset_code,
                        'error' => $e->getMessage(),
                    ]);
                    
                    if ($attempt < $maxRetries) {
                        // Esperar tiempo aleatorio entre 50-150ms para reducir colisiones
                        usleep(rand(50000, 150000));
                        continue;
                    }
                    
                    // Se agotaron los reintentos
                    Log::error("Se agotaron los reintentos en storePublic después de {$maxRetries} intentos", [
                        'company_id' => $companyId,
                        'asset_code' => $request->asset_code,
                    ]);
                    return ApiResponse::error(
                        'No se pudo generar un código único. Por favor, intente nuevamente en unos segundos.',
                        500
                    );
                }
                
                // Otro tipo de error de base de datos
                Log::error('Error de base de datos en storePublic: ' . $e->getMessage(), [
                    'code' => $e->getCode(),
                    'trace' => $e->getTraceAsString(),
                    'request_data' => $request->except(['attachments'])
                ]);
                return ApiResponse::error(
                    'Error al crear la solicitud de trabajo. Por favor, intente nuevamente.',
                    500
                );
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error en storePublic: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'request_data' => $request->except(['attachments'])
                ]);
                return ApiResponse::error(
                    'Error al crear la solicitud de trabajo. Por favor, intente nuevamente.',
                    500
                );
            }
        }

        // Este código nunca debería alcanzarse debido a los returns en los catch
        Log::error('storePublic alcanzó el final del while loop sin return');
        return ApiResponse::error(
            'Error inesperado al crear la solicitud. Por favor, intente nuevamente.',
            500
        );
    }

    // ===================================
    // TAGS MANAGEMENT
    // ===================================

    /**
     * Listar tags disponibles para la empresa
     * 
     * @return JsonResponse
     */
    public function getTags(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $tags = \App\Models\WorkRequestTag::forCompany($companyId)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'color', 'description']);

        return ApiResponse::success($tags, 'Tags recuperados exitosamente');
    }

    // ===================================
    // COMMENTS MANAGEMENT
    // ===================================

    /**
     * Listar comentarios de una solicitud
     * 
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function getComments(int $id, Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        // Filtrar comentarios internos si el usuario no tiene permiso
        $query = $workRequest->comments()->with(['user', 'replies.user']);
        
        $includeInternal = $request->boolean('include_internal', false);
        if (!$includeInternal || !($request->user() !== null) || !$request->user()->can('WORK_REQUESTS_READ_INTERNAL')) {
            $query->where('is_internal', false);
        }

        $comments = $query->whereNull('parent_id')
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::success(
            \App\Http\Resources\WorkRequestCommentResource::collection($comments),
            'Comentarios recuperados exitosamente'
        );
    }

    /**
     * Crear comentario en una solicitud
     * 
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function storeComment(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'comment' => 'required|string',
            'is_internal' => 'boolean',
            'parent_id' => 'nullable|integer|exists:work_request_comments,id',
        ]);

        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $comment = $workRequest->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->comment,
            'is_internal' => $request->boolean('is_internal', false),
            'parent_id' => $request->parent_id,
        ]);

        $comment->load('user', 'replies.user');

        return ApiResponse::success(
            new \App\Http\Resources\WorkRequestCommentResource($comment),
            'Comentario creado exitosamente',
            201
        );
    }

    /**
     * Actualizar comentario
     * 
     * @param int $id
     * @param int $commentId
     * @param Request $request
     * @return JsonResponse
     */
    public function updateComment(int $id, int $commentId, Request $request): JsonResponse
    {
        $request->validate([
            'comment' => 'required|string',
        ]);

        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $comment = $workRequest->comments()->find($commentId);
        
        if (!$comment) {
            return ApiResponse::notFound('Comentario no encontrado');
        }

        // Solo el autor o admin puede editar
        if ($comment->user_id !== $request->user()->id && !$request->user()->can('WORK_REQUESTS_ADMIN')) {
            return ApiResponse::error('No tiene permiso para editar este comentario', 403);
        }

        $comment->update([
            'comment' => $request->comment,
            'is_edited' => true,
        ]);

        $comment->load('user', 'replies.user');

        return ApiResponse::success(
            new \App\Http\Resources\WorkRequestCommentResource($comment),
            'Comentario actualizado exitosamente'
        );
    }

    /**
     * Eliminar comentario
     * 
     * @param int $id
     * @param int $commentId
     * @return JsonResponse
     */
    public function destroyComment(Request $request, int $id, int $commentId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $comment = $workRequest->comments()->find($commentId);
        
        if (!$comment) {
            return ApiResponse::notFound('Comentario no encontrado');
        }

        // Solo el autor o admin puede eliminar
        if ($comment->user_id !== $request->user()->id && !$request->user()->can('WORK_REQUESTS_ADMIN')) {
            return ApiResponse::error('No tiene permiso para eliminar este comentario', 403);
        }

        $comment->delete();

        return ApiResponse::success(null, 'Comentario eliminado exitosamente');
    }

    // ===================================
    // ATTACHMENTS MANAGEMENT
    // ===================================

    /**
     * Subir archivos adicionales a una solicitud
     * 
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function uploadAttachments(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'attachments' => 'required|array|max:10',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx|max:10240',
        ]);

        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $uploadedFiles = [];

        try {
            DB::beginTransaction();

            foreach ($request->file('attachments') as $file) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs("work-requests/{$workRequest->id}", $filename, 'public');

                $attachment = $workRequest->attachments()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'uploaded_by' => $request->user()->id,
                ]);

                $uploadedFiles[] = $attachment;
            }

            DB::commit();

            return ApiResponse::success(
                \App\Http\Resources\WorkRequestAttachmentResource::collection($uploadedFiles),
                'Archivos subidos exitosamente',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al subir archivos', 500);
        }
    }

    /**
     * Eliminar archivo adjunto
     * 
     * @param int $id
     * @param int $attachmentId
     * @return JsonResponse
     */
    public function destroyAttachment(Request $request, int $id, int $attachmentId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $attachment = $workRequest->attachments()->find($attachmentId);
        
        if (!$attachment) {
            return ApiResponse::notFound('Archivo no encontrado');
        }

        try {
            // Eliminar archivo físico
            Storage::disk('public')->delete($attachment->file_path);
            
            // Eliminar registro
            $attachment->delete();

            return ApiResponse::success(null, 'Archivo eliminado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar archivo', 500);
        }
    }

    // ===================================
    // WATCHERS MANAGEMENT
    // ===================================

    /**
     * Listar watchers de una solicitud
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function getWatchers(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $watchers = $workRequest->watchers()->with('user')->get();

        return ApiResponse::success($watchers, 'Watchers recuperados exitosamente');
    }

    /**
     * Agregar watcher a una solicitud
     * 
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function addWatcher(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        // Verificar si ya existe
        $exists = $workRequest->watchers()->where('user_id', $request->user_id)->exists();
        
        if ($exists) {
            return ApiResponse::error('El usuario ya está siguiendo esta solicitud', 400);
        }

        $watcher = $workRequest->watchers()->create([
            'user_id' => $request->user_id,
            'added_by' => $request->user()->id,
        ]);

        $watcher->load('user');

        return ApiResponse::success($watcher, 'Watcher agregado exitosamente', 201);
    }

    /**
     * Remover watcher de una solicitud
     * 
     * @param int $id
     * @param int $userId
     * @return JsonResponse
     */
    public function removeWatcher(Request $request, int $id, int $userId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $watcher = $workRequest->watchers()->where('user_id', $userId)->first();
        
        if (!$watcher) {
            return ApiResponse::notFound('Watcher no encontrado');
        }

        $watcher->delete();

        return ApiResponse::success(null, 'Watcher removido exitosamente');
    }

    // ===================================
    // RELATED REQUESTS MANAGEMENT
    // ===================================

    /**
     * Listar solicitudes relacionadas
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function getRelated(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $related = $workRequest->relatedRequests()
            ->with('relatedRequest')
            ->get();

        return ApiResponse::success($related, 'Solicitudes relacionadas recuperadas exitosamente');
    }

    /**
     * Vincular solicitudes
     * 
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function linkRelated(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'related_work_request_id' => 'required|integer|exists:work_requests,id',
            'relationship_type' => 'required|in:duplicate,related,blocks,caused_by,parent,child',
        ]);

        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        // Verificar que la solicitud relacionada existe y es de la misma empresa
        $relatedRequest = WorkRequest::forCompany($companyId)->find($request->related_work_request_id);
        
        if (!$relatedRequest) {
            return ApiResponse::notFound('Solicitud relacionada no encontrada');
        }

        // Evitar auto-relación
        if ($id == $request->related_work_request_id) {
            return ApiResponse::error('No puede relacionar una solicitud consigo misma', 400);
        }

        // Verificar si ya existe la relación
        $exists = $workRequest->relatedRequests()
            ->where('related_work_request_id', $request->related_work_request_id)
            ->where('relationship_type', $request->relationship_type)
            ->exists();
        
        if ($exists) {
            return ApiResponse::error('Esta relación ya existe', 400);
        }

        $relation = $workRequest->relatedRequests()->create([
            'related_work_request_id' => $request->related_work_request_id,
            'relationship_type' => $request->relationship_type,
            'created_by' => $request->user()->id,
        ]);

        $relation->load('relatedRequest');

        return ApiResponse::success($relation, 'Solicitudes vinculadas exitosamente', 201);
    }

    /**
     * Desvincular solicitudes
     * 
     * @param int $id
     * @param int $relatedId
     * @return JsonResponse
     */
    public function unlinkRelated(Request $request, int $id, int $relatedId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $relation = $workRequest->relatedRequests()
            ->where('id', $relatedId)
            ->first();
        
        if (!$relation) {
            return ApiResponse::notFound('Relación no encontrada');
        }

        $relation->delete();

        return ApiResponse::success(null, 'Solicitudes desvinculadas exitosamente');
    }

    // ===================================
    // CHECKLIST MANAGEMENT
    // ===================================

    /**
     * Marcar/desmarcar item del checklist
     * 
     * @param int $id
     * @param int $itemId
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleChecklistItem(int $id, int $itemId, Request $request): JsonResponse
    {
        $request->validate([
            'is_checked' => 'required|boolean',
            'notes' => 'nullable|string|max:500',
        ]);

        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $item = $workRequest->checklistItems()->find($itemId);
        
        if (!$item) {
            return ApiResponse::notFound('Item del checklist no encontrado');
        }

        $item->update([
            'is_checked' => $request->is_checked,
            'checked_by' => $request->is_checked ? $request->user()->id : null,
            'checked_at' => $request->is_checked ? now() : null,
            'notes' => $request->notes,
        ]);

        return ApiResponse::success($item, 'Item actualizado exitosamente');
    }

    /**
     * Actualizar notas de un item del checklist
     * 
     * @param int $id
     * @param int $itemId
     * @param Request $request
     * @return JsonResponse
     */
    public function updateChecklistNotes(int $id, int $itemId, Request $request): JsonResponse
    {
        $request->validate([
            'notes' => 'required|string|max:500',
        ]);

        $companyId = $request->header('x-company-id');
        $workRequest = WorkRequest::forCompany($companyId)->find($id);
        
        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud no encontrada');
        }

        $item = $workRequest->checklistItems()->find($itemId);
        
        if (!$item) {
            return ApiResponse::notFound('Item del checklist no encontrado');
        }

        $item->update([
            'notes' => $request->notes,
        ]);

        return ApiResponse::success($item, 'Notas actualizadas exitosamente');
    }

    /**
     * Generar PDF de una solicitud de trabajo (autenticado)
     */
    public function generatePdf(Request $request, int $id)
    {
        $companyId = $request->header('x-company-id');

        $workRequest = WorkRequest::where('company_id', $companyId)
            ->with([
                'company',
                'asset.category',
                'asset.companySite',
                'requester',
                'approvedBy',
                'rejectedBy',
                'checklistItems',
                'tags',
                'workOrder',
            ])
            ->find($id);

        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud de trabajo no encontrada');
        }

        try {
            $logoBase64 = $this->getLogoBase64();
            $pdf = Pdf::loadView('pdf.work-request', [
                'workRequest' => $workRequest,
                'logoBase64'  => $logoBase64,
            ]);
            $filename = "Solicitud_{$workRequest->code}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al generar el PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generar PDF de una solicitud de trabajo (acceso público por código)
     */
    public function generatePublicPdf(string $code)
    {
        $workRequest = WorkRequest::where('code', $code)
            ->with([
                'company',
                'asset.category',
                'asset.companySite',
                'requester',
                'approvedBy',
                'rejectedBy',
                'checklistItems',
                'tags',
                'workOrder',
            ])
            ->first();

        if (!$workRequest) {
            return ApiResponse::notFound('Solicitud de trabajo no encontrada');
        }

        try {
            $logoBase64 = $this->getLogoBase64();
            $pdf = Pdf::loadView('pdf.work-request', [
                'workRequest' => $workRequest,
                'logoBase64'  => $logoBase64,
            ]);
            $filename = "Solicitud_{$workRequest->code}.pdf";
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

    /**
     * Aprobar múltiples solicitudes de trabajo en lote
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        $request->validate([
            'ids'             => 'required|array|min:1',
            'ids.*'           => 'integer',
            'approval_comment' => 'nullable|string|max:1000',
        ]);

        $companyId = $request->header('x-company-id');
        $userId    = $request->user()->id;

        $ids = $request->input('ids');

        $approved = [];
        $skipped  = [];
        $errors   = [];

        try {
            DB::beginTransaction();

            $workRequests = WorkRequest::where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->get();

            foreach ($workRequests as $workRequest) {
                if (!$workRequest->can_be_approved) {
                    $skipped[] = [
                        'id'     => $workRequest->id,
                        'code'   => $workRequest->code,
                        'reason' => 'No puede ser aprobada en su estado actual (' . $workRequest->status . ')',
                    ];
                    continue;
                }

                $previousStatus = $workRequest->status;

                $workRequest->update([
                    'status'      => 'approved',
                    'approved_by' => $userId,
                    'approved_at' => now(),
                    'updated_by'  => $userId,
                ]);

                $workRequest->recordFirstResponse();

                WorkRequestStatusHistory::record(
                    $workRequest->id,
                    $previousStatus,
                    'approved',
                    $userId,
                    $request->approval_comment
                );

                if ($request->filled('approval_comment')) {
                    $workRequest->comments()->create([
                        'user_id'     => $userId,
                        'comment'     => $request->approval_comment,
                        'is_internal' => false,
                    ]);
                }

                if ($workRequest->requester_id) {
                    WorkRequestNotification::createNotification(
                        $workRequest->id,
                        $workRequest->requester_id,
                        'approved',
                        'Solicitud Aprobada',
                        "Tu solicitud {$workRequest->code} ha sido aprobada",
                        'in_app'
                    );
                }

                $this->activityService->logWorkRequestApproved($workRequest);

                $approved[] = ['id' => $workRequest->id, 'code' => $workRequest->code];
            }

            // IDs no encontrados
            $foundIds = $workRequests->pluck('id')->toArray();
            $notFound = array_diff($ids, $foundIds);
            foreach ($notFound as $missingId) {
                $errors[] = ['id' => $missingId, 'reason' => 'No encontrada'];
            }

            DB::commit();

            return ApiResponse::success([
                'approved' => $approved,
                'skipped'  => $skipped,
                'errors'   => $errors,
                'summary'  => [
                    'total_requested' => count($ids),
                    'approved_count'  => count($approved),
                    'skipped_count'   => count($skipped),
                    'error_count'     => count($errors),
                ],
            ], count($approved) . ' solicitud(es) aprobada(s) exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al aprobar las solicitudes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar múltiples solicitudes de trabajo en lote
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $companyId = $request->header('x-company-id');
        $user      = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'WORK_REQUESTS_DELETE_ADMIN', $companyId)) {
            return ApiResponse::error('No tienes permiso para eliminar solicitudes de trabajo', 403);
        }

        $ids     = $request->input('ids');
        $deleted = [];
        $skipped = [];
        $errors  = [];

        try {
            DB::beginTransaction();

            $workRequests = WorkRequest::where('company_id', $companyId)
                ->whereIn('id', $ids)
                ->get();

            foreach ($workRequests as $workRequest) {
                // Soft-delete OT vinculada si existe
                if ($workRequest->work_order_id) {
                    $relatedOrder = \App\Models\WorkOrder::forCompany($companyId)
                        ->find($workRequest->work_order_id);

                    if ($relatedOrder) {
                        $relatedOrder->deleted_by = $user->id;
                        $relatedOrder->save();
                        $relatedOrder->delete();
                    }
                }

                $workRequest->delete();
                $deleted[] = ['id' => $workRequest->id, 'code' => $workRequest->code];
            }

            // IDs no encontrados
            $foundIds = $workRequests->pluck('id')->toArray();
            $notFound = array_diff($ids, $foundIds);
            foreach ($notFound as $missingId) {
                $errors[] = ['id' => $missingId, 'reason' => 'No encontrada'];
            }

            DB::commit();

            return ApiResponse::success([
                'deleted' => $deleted,
                'skipped' => $skipped,
                'errors'  => $errors,
                'summary' => [
                    'total_requested' => count($ids),
                    'deleted_count'   => count($deleted),
                    'skipped_count'   => count($skipped),
                    'error_count'     => count($errors),
                ],
            ], count($deleted) . ' solicitud(es) eliminada(s) exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al eliminar las solicitudes: ' . $e->getMessage(), 500);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $companyId = (int) $request->header('x-company-id');

        $export = new WorkRequestExport(
            companyId: $companyId,
            from:      $request->query('from'),
            to:        $request->query('to'),
            status:    $request->query('status'),
            type:      $request->query('type'),
        );

        return $export->download('solicitudes_' . now()->format('Y-m-d') . '.xlsx');
    }
}


