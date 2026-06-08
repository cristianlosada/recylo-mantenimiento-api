<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkRequestChecklistTemplateRequest;
use App\Http\Requests\UpdateWorkRequestChecklistTemplateRequest;
use App\Http\Resources\WorkRequestChecklistTemplateResource;
use App\Http\Responses\ApiResponse;
use App\Models\WorkRequestChecklistTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class WorkRequestChecklistTemplateController extends Controller
{
    /**
     * Listar plantillas de checklist con filtros
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $query = WorkRequestChecklistTemplate::forCompany($companyId)
            ->with(['assetCategory', 'createdBy', 'checklistItems']);

        // Filtros opcionales
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('asset_category_id')) {
            $query->forCategory($request->asset_category_id);
        }

        if ($request->filled('request_type')) {
            $query->forType($request->request_type);
        }

        if ($request->filled('priority')) {
            $query->forPriority($request->priority);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'display_order');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación o lista completa
        if ($request->boolean('all')) {
            $templates = $query->get();
            return ApiResponse::success(
                WorkRequestChecklistTemplateResource::collection($templates),
                'Plantillas recuperadas exitosamente'
            );
        }

        $perPage = $request->get('per_page', 15);
        $templates = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => WorkRequestChecklistTemplateResource::collection($templates->items()),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
            'message' => 'Plantillas recuperadas exitosamente',
        ]);
    }

    /**
     * Mostrar una plantilla específica
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $template = WorkRequestChecklistTemplate::with([
            'assetCategory',
            'createdBy',
            'checklistItems.workRequest',
        ])
        ->where('company_id', $companyId)
        ->find($id);

        if (!$template) {
            return ApiResponse::notFound('Plantilla no encontrada');
        }

        return ApiResponse::success(
            new WorkRequestChecklistTemplateResource($template),
            'Plantilla recuperada exitosamente'
        );
    }

    /**
     * Crear nueva plantilla de checklist
     * 
     * @param StoreWorkRequestChecklistTemplateRequest $request
     * @return JsonResponse
     */
    public function store(StoreWorkRequestChecklistTemplateRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $companyId = $request->header('x-company-id');
            $userId = Auth::id();

            // Crear plantilla
            $template = WorkRequestChecklistTemplate::create([
                'company_id' => $companyId,
                'name' => $request->name,
                'description' => $request->description,
                'asset_category_id' => $request->asset_category_id,
                'request_type' => $request->request_type,
                'priority' => $request->priority,
                'is_active' => $request->is_active ?? true,
                'is_mandatory' => $request->is_mandatory ?? false,
                'display_order' => $request->display_order ?? 0,
                'created_by' => $userId,
            ]);

            DB::commit();

            $template->load(['assetCategory', 'createdBy']);

            return response()->json([
                'success' => true,
                'data' => new WorkRequestChecklistTemplateResource($template),
                'message' => 'Plantilla creada exitosamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al crear la plantilla: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Actualizar plantilla existente
     * 
     * @param UpdateWorkRequestChecklistTemplateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(UpdateWorkRequestChecklistTemplateRequest $request, int $id): JsonResponse
    {
        try {
            $companyId = $request->header('x-company-id');

            $template = WorkRequestChecklistTemplate::where('company_id', $companyId)->find($id);

            if (!$template) {
                return ApiResponse::notFound('Plantilla no encontrada');
            }

            DB::beginTransaction();

            // Actualizar campos
            $template->update([
                'name' => $request->name ?? $template->name,
                'description' => $request->description ?? $template->description,
                'asset_category_id' => $request->asset_category_id ?? $template->asset_category_id,
                'request_type' => $request->request_type ?? $template->request_type,
                'priority' => $request->priority ?? $template->priority,
                'is_active' => $request->is_active ?? $template->is_active,
                'is_mandatory' => $request->is_mandatory ?? $template->is_mandatory,
                'display_order' => $request->display_order ?? $template->display_order,
            ]);

            DB::commit();

            $template->load(['assetCategory', 'createdBy']);

            return ApiResponse::success(
                new WorkRequestChecklistTemplateResource($template),
                'Plantilla actualizada exitosamente'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al actualizar la plantilla: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Eliminar plantilla (soft delete)
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $template = WorkRequestChecklistTemplate::where('company_id', $companyId)->find($id);

        if (!$template) {
            return ApiResponse::notFound('Plantilla no encontrada');
        }

        // Verificar si está en uso
        $usageCount = $template->checklistItems()->count();
        
        if ($usageCount > 0) {
            return ApiResponse::error(
                "No se puede eliminar la plantilla porque está siendo utilizada en {$usageCount} solicitudes. Considere desactivarla en su lugar.",
                400
            );
        }

        $template->delete();

        return ApiResponse::success(
            null,
            'Plantilla eliminada exitosamente'
        );
    }

    /**
     * Activar/Desactivar plantilla
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function toggleActive(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $template = WorkRequestChecklistTemplate::where('company_id', $companyId)->find($id);

        if (!$template) {
            return ApiResponse::notFound('Plantilla no encontrada');
        }

        $template->is_active = !$template->is_active;
        $template->save();

        $status = $template->is_active ? 'activada' : 'desactivada';

        return ApiResponse::success(
            new WorkRequestChecklistTemplateResource($template),
            "Plantilla {$status} exitosamente"
        );
    }

    /**
     * Duplicar plantilla
     * 
     * @param int $id
     * @return JsonResponse
     */
    public function duplicate(Request $request, int $id): JsonResponse
    {
        try {
            $companyId = $request->header('x-company-id');
            $userId = Auth::id();

            $original = WorkRequestChecklistTemplate::where('company_id', $companyId)->find($id);

            if (!$original) {
                return ApiResponse::notFound('Plantilla no encontrada');
            }

            DB::beginTransaction();

            // Crear copia
            $duplicate = WorkRequestChecklistTemplate::create([
                'company_id' => $companyId,
                'name' => $original->name . ' (Copia)',
                'description' => $original->description,
                'asset_category_id' => $original->asset_category_id,
                'request_type' => $original->request_type,
                'priority' => $original->priority,
                'is_active' => false, // Desactivada por defecto
                'is_mandatory' => $original->is_mandatory,
                'display_order' => $original->display_order,
                'created_by' => $userId,
            ]);

            DB::commit();

            $duplicate->load(['assetCategory', 'createdBy']);

            return response()->json([
                'success' => true,
                'data' => new WorkRequestChecklistTemplateResource($duplicate),
                'message' => 'Plantilla duplicada exitosamente',
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al duplicar la plantilla: ' . $e->getMessage(),
                500
            );
        }
    }
}

