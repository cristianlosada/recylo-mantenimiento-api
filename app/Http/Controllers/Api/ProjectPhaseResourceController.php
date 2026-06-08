<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Material;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ProjectPhaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectPhaseResourceController extends Controller
{
    private static array $types = ['material', 'tool', 'external_service'];

    // ── Listar recursos de una fase ───────────────────────────────────────────

    public function index(Request $request, int $projectId, int $phaseId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!Project::byCompany($companyId)->find($projectId)) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phase = ProjectPhase::where('project_id', $projectId)->find($phaseId);
        if (!$phase) {
            return ApiResponse::notFound('Fase no encontrada');
        }

        $resources = $phase->resources()
            ->with('material:id,code,name,unit_of_measure')
            ->orderBy('resource_type')
            ->orderBy('name')
            ->get();

        return ApiResponse::success($this->formatCollection($resources), 'Recursos obtenidos exitosamente');
    }

    // ── Crear recurso ─────────────────────────────────────────────────────────

    public function store(Request $request, int $projectId, int $phaseId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!Project::byCompany($companyId)->find($projectId)) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phase = ProjectPhase::where('project_id', $projectId)->find($phaseId);
        if (!$phase) {
            return ApiResponse::notFound('Fase no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'resource_type'  => 'required|string|in:' . implode(',', self::$types),
            'name'           => 'required|string|max:150',
            'quantity'       => 'nullable|numeric|min:0',
            'unit'           => 'nullable|string|max:50',
            'unit_cost'      => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost'    => 'nullable|numeric|min:0',
            'material_id'    => 'nullable|integer|exists:materials,id',
            'notes'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        DB::beginTransaction();
        try {
            $resource = ProjectPhaseResource::create([
                'phase_id'       => $phaseId,
                'resource_type'  => $request->resource_type,
                'name'           => $request->name,
                'quantity'       => $request->quantity,
                'unit'           => $request->unit,
                'unit_cost'      => $request->unit_cost,
                'estimated_cost' => $this->resolveEstimatedCost($request),
                'actual_cost'    => $request->actual_cost,
                'material_id'    => $request->resource_type === 'material' ? $request->material_id : null,
                'notes'          => $request->notes,
                'created_by'     => auth()->id(),
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear el recurso', 500);
        }

        return ApiResponse::success(
            $this->format($resource->load('material:id,code,name,unit_of_measure')),
            'Recurso creado exitosamente',
            201
        );
    }

    // ── Actualizar recurso ────────────────────────────────────────────────────

    public function update(Request $request, int $projectId, int $phaseId, int $resourceId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!Project::byCompany($companyId)->find($projectId)) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phase = ProjectPhase::where('project_id', $projectId)->find($phaseId);
        if (!$phase) {
            return ApiResponse::notFound('Fase no encontrada');
        }

        $resource = ProjectPhaseResource::where('phase_id', $phaseId)->find($resourceId);
        if (!$resource) {
            return ApiResponse::notFound('Recurso no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'resource_type'  => 'sometimes|string|in:' . implode(',', self::$types),
            'name'           => 'sometimes|string|max:150',
            'quantity'       => 'nullable|numeric|min:0',
            'unit'           => 'nullable|string|max:50',
            'unit_cost'      => 'nullable|numeric|min:0',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost'    => 'nullable|numeric|min:0',
            'material_id'    => 'nullable|integer|exists:materials,id',
            'notes'          => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        DB::beginTransaction();
        try {
            $data = $request->only([
                'resource_type', 'name', 'quantity', 'unit',
                'unit_cost', 'actual_cost', 'notes',
            ]);

            // Recalcular estimated_cost si se cambia qty o unit_cost
            if ($request->hasAny(['quantity', 'unit_cost', 'estimated_cost'])) {
                $data['estimated_cost'] = $this->resolveEstimatedCost($request, $resource);
            }

            $newType = $request->resource_type ?? $resource->resource_type;
            $data['material_id'] = $newType === 'material' ? ($request->material_id ?? $resource->material_id) : null;

            $resource->update($data);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar el recurso', 500);
        }

        return ApiResponse::success(
            $this->format($resource->fresh()->load('material:id,code,name,unit_of_measure')),
            'Recurso actualizado exitosamente'
        );
    }

    // ── Eliminar recurso ──────────────────────────────────────────────────────

    public function destroy(Request $request, int $projectId, int $phaseId, int $resourceId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!Project::byCompany($companyId)->find($projectId)) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phase = ProjectPhase::where('project_id', $projectId)->find($phaseId);
        if (!$phase) {
            return ApiResponse::notFound('Fase no encontrada');
        }

        $resource = ProjectPhaseResource::where('phase_id', $phaseId)->find($resourceId);
        if (!$resource) {
            return ApiResponse::notFound('Recurso no encontrado');
        }

        $resource->delete();

        return ApiResponse::success(null, 'Recurso eliminado exitosamente');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function resolveEstimatedCost(Request $request, ?ProjectPhaseResource $existing = null): ?float
    {
        if ($request->filled('estimated_cost')) {
            return (float) $request->estimated_cost;
        }
        $qty  = $request->filled('quantity')   ? (float) $request->quantity   : ($existing?->quantity);
        $cost = $request->filled('unit_cost')   ? (float) $request->unit_cost  : ($existing?->unit_cost);
        if ($qty !== null && $cost !== null) {
            return round($qty * $cost, 2);
        }
        return $existing?->estimated_cost;
    }

    private function format(ProjectPhaseResource $r): array
    {
        return [
            'id'             => $r->id,
            'phase_id'       => $r->phase_id,
            'resource_type'  => $r->resource_type,
            'name'           => $r->name,
            'quantity'       => $r->quantity,
            'unit'           => $r->unit,
            'unit_cost'      => $r->unit_cost,
            'estimated_cost' => $r->estimated_cost,
            'actual_cost'    => $r->actual_cost,
            'material'       => $r->relationLoaded('material') && $r->material ? [
                'id'   => $r->material->id,
                'code' => $r->material->code,
                'name' => $r->material->name,
                'unit' => $r->material->unit_of_measure,
            ] : null,
            'notes'      => $r->notes,
            'created_at' => $r->created_at,
        ];
    }

    private function formatCollection($resources): array
    {
        return $resources->map(fn($r) => $this->format($r))->values()->toArray();
    }
}
