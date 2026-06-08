<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMaintenancePlanRequest;
use App\Http\Requests\UpdateMaintenancePlanRequest;
use App\Http\Resources\MaintenancePlanResource;
use App\Http\Resources\MaintenancePlanCollection;
use App\Http\Resources\MaintenancePlanExecutionResource;
use App\Http\Resources\MaintenancePlanExecutionCollection;
use App\Http\Resources\WorkOrderResource;
use App\Http\Responses\ApiResponse;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanExecution;
use App\Models\Company;
use App\Services\MaintenancePlanService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MaintenancePlanController extends Controller
{
    protected $maintenancePlanService;

    public function __construct(MaintenancePlanService $maintenancePlanService)
    {
        $this->maintenancePlanService = $maintenancePlanService;
    }

    /**
     * Listar planes de mantenimiento con filtros y paginación
     * 
     * GET /api/maintenance-plans
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = MaintenancePlan::forCompany($companyId)
            ->with([
                'asset.category',
                'assetCategory',
                'site',
                'creator'
            ]);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('plan_name', 'like', "%{$search}%")
                  ->orWhereHas('asset', function ($assetQuery) use ($search) {
                      $assetQuery->where('name', 'like', "%{$search}%")
                                 ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('plan_type')) {
            $query->where('plan_type', $request->plan_type);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        if ($request->filled('asset_category_id')) {
            $query->where('asset_category_id', $request->asset_category_id);
        }

        if ($request->filled('company_site_id')) {
            $query->forSite($request->company_site_id);
        }

        if ($request->boolean('only_active')) {
            $query->active();
        }

        if ($request->boolean('only_due_today')) {
            $query->dueToday();
        }

        if ($request->boolean('only_overdue')) {
            $query->overdue();
        }

        if ($request->filled('upcoming_days')) {
            $days = (int) $request->upcoming_days;
            $query->upcoming($days);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $plans = $query->paginate($perPage);

        return ApiResponse::success(
            new MaintenancePlanCollection($plans),
            'Planes de mantenimiento obtenidos exitosamente'
        );
    }

    /**
     * Mostrar plan de mantenimiento específico
     * 
     * GET /api/maintenance-plans/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $plan = MaintenancePlan::with([
            'asset.category',
            'assetCategory',
            'company',
            'site',
            'creator',
            'checklistTemplates',
            'materialTemplates.material',
            'executions' => fn($q) => $q->latest()->limit(10),
            'executions.workOrder'
        ])
        ->forCompany($companyId)
        ->find($id);

        if (!$plan) {
            return ApiResponse::notFound('Plan de mantenimiento no encontrado');
        }

        return ApiResponse::success(
            new MaintenancePlanResource($plan),
            'Plan de mantenimiento obtenido exitosamente'
        );
    }

    /**
     * Crear nuevo plan de mantenimiento
     * 
     * POST /api/maintenance-plans
     */
    public function store(StoreMaintenancePlanRequest $request): JsonResponse
    {
        Log::info('=== INICIO store MaintenancePlan ===', [
            'header_x_company_id' => $request->header('x-company-id'),
            'validated_data' => $request->validated(),
            'user_id' => Auth::id()
        ]);

        try {
            $plan = $this->maintenancePlanService->createPlan($request->validated());

            Log::info('Plan de mantenimiento creado exitosamente', [
                'plan_id' => $plan->id,
                'code' => $plan->code,
                'plan_type' => $plan->plan_type,
                'asset_id' => $plan->asset_id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::created(
                new MaintenancePlanResource($plan->load([
                    'asset.category',
                    'creator',
                    'checklistTemplates',
                    'materialTemplates.material'
                ])),
                'Plan de mantenimiento creado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al crear plan de mantenimiento: ' . $e->getMessage(), [
                'asset_id' => $request->asset_id,
                'plan_type' => $request->plan_type,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al crear plan de mantenimiento: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Actualizar plan de mantenimiento existente
     * 
     * PUT /api/maintenance-plans/{id}
     */
    public function update(UpdateMaintenancePlanRequest $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $plan = MaintenancePlan::forCompany($companyId)->find($id);
        
        if (!$plan) {
            return ApiResponse::notFound('Plan de mantenimiento no encontrado');
        }

        try {
            $plan = $this->maintenancePlanService->updatePlan($id, $request->validated());

            Log::info('Plan de mantenimiento actualizado exitosamente', [
                'plan_id' => $plan->id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                new MaintenancePlanResource($plan->load([
                    'asset.category',
                    'creator',
                    'checklistTemplates',
                    'materialTemplates.material'
                ])),
                'Plan de mantenimiento actualizado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al actualizar plan de mantenimiento: ' . $e->getMessage(), [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al actualizar plan de mantenimiento: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Activar plan de mantenimiento
     * 
     * POST /api/maintenance-plans/{id}/activate
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $plan = MaintenancePlan::forCompany($companyId)->find($id);
        
        if (!$plan) {
            return ApiResponse::notFound('Plan de mantenimiento no encontrado');
        }

        try {
            $plan = $this->maintenancePlanService->activatePlan($id);

            Log::info('Plan de mantenimiento activado', [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                new MaintenancePlanResource($plan->load(['asset.category'])),
                'Plan de mantenimiento activado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al activar plan de mantenimiento: ' . $e->getMessage(), [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al activar plan de mantenimiento: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Desactivar plan de mantenimiento
     * 
     * POST /api/maintenance-plans/{id}/deactivate
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $plan = MaintenancePlan::forCompany($companyId)->find($id);
        
        if (!$plan) {
            return ApiResponse::notFound('Plan de mantenimiento no encontrado');
        }

        try {
            $plan = $this->maintenancePlanService->deactivatePlan($id);

            Log::info('Plan de mantenimiento desactivado', [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                new MaintenancePlanResource($plan->load(['asset.category'])),
                'Plan de mantenimiento desactivado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al desactivar plan de mantenimiento: ' . $e->getMessage(), [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al desactivar plan de mantenimiento: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Ejecutar plan de mantenimiento manualmente
     * 
     * POST /api/maintenance-plans/{id}/execute
     */
    public function executeManually(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $plan = MaintenancePlan::forCompany($companyId)->find($id);
        
        if (!$plan) {
            return ApiResponse::notFound('Plan de mantenimiento no encontrado');
        }

        try {
            $workOrder = $this->maintenancePlanService->executeManually($id);

            Log::info('Plan de mantenimiento ejecutado manualmente', [
                'plan_id' => $id,
                'work_order_id' => $workOrder->id,
                'work_order_code' => $workOrder->code,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::created(
                new WorkOrderResource($workOrder->load([
                    'asset.category',
                    'assignedTo',
                    'checklistItems',
                    'materials'
                ])),
                'Orden de trabajo generada exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al ejecutar plan de mantenimiento: ' . $e->getMessage(), [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al ejecutar plan de mantenimiento: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Obtener historial de ejecuciones de un plan
     * 
     * GET /api/maintenance-plans/{id}/executions
     */
    public function getExecutions(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $plan = MaintenancePlan::forCompany($companyId)->find($id);
        
        if (!$plan) {
            return ApiResponse::notFound('Plan de mantenimiento no encontrado');
        }

        $query = MaintenancePlanExecution::where('maintenance_plan_id', $id)
            ->with(['maintenancePlan', 'workOrder']);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('scheduled_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'scheduled_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $executions = $query->paginate($perPage);

        return ApiResponse::success(
            new MaintenancePlanExecutionCollection($executions),
            'Ejecuciones obtenidas exitosamente'
        );
    }

    /**
     * Obtener dashboard/métricas de planes de mantenimiento
     * 
     * GET /api/maintenance-plans/dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        try {
            $dashboard = $this->maintenancePlanService->getDashboard($companyId);

            return ApiResponse::success(
                $dashboard,
                'Dashboard obtenido exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener dashboard: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al obtener dashboard: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Verificar planes que necesitan ejecución (para scheduler manual)
     * 
     * POST /api/maintenance-plans/check-due
     */
    public function checkDuePlans(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        try {
            $result = $this->maintenancePlanService->checkDuePlans($companyId);

            Log::info('Verificación de planes vencidos ejecutada', [
                'company_id' => $companyId,
                'executed_count' => $result['executed'],
                'errors_count' => count($result['errors']),
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                $result,
                "Se ejecutaron {$result['executed']} planes de mantenimiento"
            );

        } catch (\Exception $e) {
            Log::error('Error al verificar planes vencidos: ' . $e->getMessage(), [
                'company_id' => $companyId,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al verificar planes vencidos: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Eliminar plan de mantenimiento (soft delete)
     * 
     * DELETE /api/maintenance-plans/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $plan = MaintenancePlan::forCompany($companyId)->find($id);
        
        if (!$plan) {
            return ApiResponse::notFound('Plan de mantenimiento no encontrado');
        }

        try {
            $this->maintenancePlanService->deletePlan($id);

            Log::info('Plan de mantenimiento eliminado', [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                null,
                'Plan de mantenimiento eliminado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al eliminar plan de mantenimiento: ' . $e->getMessage(), [
                'plan_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al eliminar plan de mantenimiento: ' . $e->getMessage(),
                500
            );
        }
    }
}
