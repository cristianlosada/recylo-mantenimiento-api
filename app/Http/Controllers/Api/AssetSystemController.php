<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AssetSystem;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssetSystemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id') ?: $request->query('company_id');

        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = AssetSystem::byCompany($companyId);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->active();
        }

        $systems = $query->orderBy('name')->get();

        return ApiResponse::success($systems, 'Sistemas recuperados exitosamente');
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $system = AssetSystem::create([
            'company_id'  => $companyId,
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => $request->get('is_active', true),
        ]);

        return ApiResponse::success($system, 'Sistema creado exitosamente', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $system = AssetSystem::byCompany($companyId)->find($id);
        if (!$system) {
            return ApiResponse::notFound('Sistema no encontrado');
        }

        return ApiResponse::success($system, 'Sistema recuperado exitosamente');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $system = AssetSystem::byCompany($companyId)->find($id);
        if (!$system) {
            return ApiResponse::notFound('Sistema no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $system->update($request->only(['name', 'description', 'is_active']));

        return ApiResponse::success($system->fresh(), 'Sistema actualizado exitosamente');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $system = AssetSystem::byCompany($companyId)->find($id);
        if (!$system) {
            return ApiResponse::notFound('Sistema no encontrado');
        }

        $assetsCount = $system->assets()->count();
        if ($assetsCount > 0) {
            return ApiResponse::error(
                "No se puede eliminar: hay {$assetsCount} activo(s) asignados a este sistema",
                422
            );
        }

        $system->delete();

        return ApiResponse::success(null, 'Sistema eliminado exitosamente');
    }
}
