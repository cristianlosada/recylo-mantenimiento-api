<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ComponentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ComponentTypeController extends Controller
{
    /**
     * GET /component-types
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso para ver tipos de componentes', 403);
        }

        $query = ComponentType::forCompany($companyId)->with('createdBy');

        if ($request->boolean('only_active')) {
            $query->active();
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code_prefix', 'like', "%{$s}%");
            });
        }

        $types = $request->boolean('all')
            ? $query->orderBy('name')->get()
            : $query->orderBy('name')->paginate($request->get('per_page', 20));

        return ApiResponse::success($types, 'Tipos de componentes obtenidos');
    }

    /**
     * POST /component-types
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_MANAGE_COMPONENT_TYPES', $companyId)) {
            return ApiResponse::error('Sin permiso para gestionar tipos de componentes', 403);
        }

        $validator = Validator::make($request->all(), [
            'code_prefix' => 'required|string|max:10',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $prefix = strtoupper(trim($request->code_prefix));

        if (ComponentType::forCompany($companyId)->where('code_prefix', $prefix)->exists()) {
            return ApiResponse::error("Ya existe un tipo con el prefijo '{$prefix}'", 422);
        }

        $type = ComponentType::create([
            'company_id'  => $companyId,
            'code_prefix' => $prefix,
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => $request->boolean('is_active', true),
            'created_by'  => Auth::id(),
        ]);

        return ApiResponse::created($type, 'Tipo de componente creado');
    }

    /**
     * PUT /component-types/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_MANAGE_COMPONENT_TYPES', $companyId)) {
            return ApiResponse::error('Sin permiso para gestionar tipos de componentes', 403);
        }

        $type = ComponentType::forCompany($companyId)->find($id);
        if (!$type) {
            return ApiResponse::notFound('Tipo de componente no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'code_prefix' => 'sometimes|string|max:10',
            'name'        => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        if ($request->filled('code_prefix')) {
            $prefix = strtoupper(trim($request->code_prefix));
            if (ComponentType::forCompany($companyId)->where('code_prefix', $prefix)->where('id', '!=', $id)->exists()) {
                return ApiResponse::error("Ya existe un tipo con el prefijo '{$prefix}'", 422);
            }
            $type->code_prefix = $prefix;
        }

        if ($request->filled('name'))        $type->name        = $request->name;
        if ($request->has('description'))    $type->description = $request->description;
        if ($request->has('is_active'))      $type->is_active   = $request->boolean('is_active');

        $type->save();

        return ApiResponse::success($type, 'Tipo de componente actualizado');
    }

    /**
     * DELETE /component-types/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_MANAGE_COMPONENT_TYPES', $companyId)) {
            return ApiResponse::error('Sin permiso para gestionar tipos de componentes', 403);
        }

        $type = ComponentType::forCompany($companyId)->withCount('components')->find($id);
        if (!$type) {
            return ApiResponse::notFound('Tipo de componente no encontrado');
        }

        if ($type->components_count > 0) {
            return ApiResponse::error('No se puede eliminar un tipo con componentes asociados', 422);
        }

        $type->delete();

        return ApiResponse::success(null, 'Tipo de componente eliminado');
    }
}
