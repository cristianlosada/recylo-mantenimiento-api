<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asset;
use App\Models\Company;
use App\Models\MaintenanceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaintenanceTypeController extends Controller
{
    /**
     * Listar tipos de mantenimiento de la empresa autenticada.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = MaintenanceType::byCompany($companyId);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->active();
        }

        $types = $query->orderBy('name')->get();

        return ApiResponse::success($types, 'Tipos de mantenimiento recuperados exitosamente');
    }

    /**
     * Crear un nuevo tipo de mantenimiento.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'code'        => 'required|string|max:50',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $exists = MaintenanceType::byCompany($companyId)
            ->where('code', strtoupper($request->code))
            ->exists();

        if ($exists) {
            return ApiResponse::error('Ya existe un tipo de mantenimiento con ese código para esta empresa', 422);
        }

        $type = MaintenanceType::create([
            'company_id'  => $companyId,
            'code'        => strtoupper($request->code),
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => $request->get('is_active', true),
        ]);

        return ApiResponse::success($type, 'Tipo de mantenimiento creado exitosamente', 201);
    }

    /**
     * Mostrar un tipo de mantenimiento.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $type = MaintenanceType::byCompany($companyId)->find($id);
        if (!$type) {
            return ApiResponse::notFound('Tipo de mantenimiento no encontrado');
        }

        return ApiResponse::success($type, 'Tipo de mantenimiento recuperado exitosamente');
    }

    /**
     * Actualizar un tipo de mantenimiento.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $type = MaintenanceType::byCompany($companyId)->find($id);
        if (!$type) {
            return ApiResponse::notFound('Tipo de mantenimiento no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'code'        => 'sometimes|string|max:50',
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        if ($request->filled('code') && strtoupper($request->code) !== $type->code) {
            $exists = MaintenanceType::byCompany($companyId)
                ->where('code', strtoupper($request->code))
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return ApiResponse::error('Ya existe un tipo de mantenimiento con ese código para esta empresa', 422);
            }
        }

        $type->update($request->only(['code', 'name', 'description', 'is_active']));

        return ApiResponse::success($type->fresh(), 'Tipo de mantenimiento actualizado exitosamente');
    }

    /**
     * Eliminar (soft delete) un tipo de mantenimiento.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $type = MaintenanceType::byCompany($companyId)->find($id);
        if (!$type) {
            return ApiResponse::notFound('Tipo de mantenimiento no encontrado');
        }

        $type->delete();

        return ApiResponse::success(null, 'Tipo de mantenimiento eliminado exitosamente');
    }

    // -------------------------------------------------------
    // GESTIÓN DE TIPOS EN UN ACTIVO
    // -------------------------------------------------------

    /**
     * Obtener tipos de mantenimiento asignados a un activo.
     * GET /companies/{companyId}/assets/{assetId}/maintenance-types
     */
    public function getAssetTypes(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);
        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $types = $asset->maintenanceTypes()
            ->select('maintenance_types.id', 'maintenance_types.code', 'maintenance_types.name', 'maintenance_types.description')
            ->get()
            ->map(fn($t) => [
                'id'          => $t->id,
                'code'        => $t->code,
                'name'        => $t->name,
                'description' => $t->description,
                'order_index' => $t->pivot->order_index,
            ]);

        return ApiResponse::success($types, 'Tipos de mantenimiento del activo recuperados exitosamente');
    }

    /**
     * Sincronizar tipos de mantenimiento de un activo (reemplaza la selección completa).
     * PUT /companies/{companyId}/assets/{assetId}/maintenance-types
     *
     * Body: { "maintenance_type_ids": [1, 3, 5] }
     * El orden del array define el order_index.
     */
    public function syncAssetTypes(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);
        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'maintenance_type_ids'   => 'required|array',
            'maintenance_type_ids.*' => 'integer|exists:maintenance_types,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $ids = $request->maintenance_type_ids;

        // Verificar que todos los tipos pertenecen a la misma empresa
        $validCount = MaintenanceType::byCompany($companyId)->whereIn('id', $ids)->count();
        if ($validCount !== count($ids)) {
            return ApiResponse::error('Uno o más tipos de mantenimiento no pertenecen a esta empresa', 422);
        }

        // Sincronizar preservando el order_index según posición en el array
        $syncData = [];
        foreach ($ids as $index => $typeId) {
            $syncData[$typeId] = ['order_index' => $index];
        }

        DB::beginTransaction();
        try {
            $asset->maintenanceTypes()->sync($syncData);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al sincronizar tipos de mantenimiento', 500);
        }

        $types = $asset->maintenanceTypes()
            ->select('maintenance_types.id', 'maintenance_types.code', 'maintenance_types.name')
            ->get()
            ->map(fn($t) => [
                'id'          => $t->id,
                'code'        => $t->code,
                'name'        => $t->name,
                'order_index' => $t->pivot->order_index,
            ]);

        return ApiResponse::success($types, 'Tipos de mantenimiento del activo actualizados exitosamente');
    }
}
