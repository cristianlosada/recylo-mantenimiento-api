<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\ProductionLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductionLineController extends Controller
{
    /**
     * Listar líneas de producción de la empresa autenticada.
     */
    public function index(Request $request): JsonResponse
    {
        // Acepta company_id desde header o desde query param (para formularios de admin)
        $companyId = $request->header('x-company-id') ?: $request->query('company_id');

        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = ProductionLine::byCompany($companyId);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->active();
        }

        $lines = $query->orderBy('name')->get();

        return ApiResponse::success($lines, 'Líneas de producción recuperadas exitosamente');
    }

    /**
     * Crear una nueva línea de producción.
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
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $exists = ProductionLine::byCompany($companyId)
            ->where('code', strtoupper($request->code))
            ->exists();

        if ($exists) {
            return ApiResponse::error('Ya existe una línea de producción con ese código para esta empresa', 422);
        }

        $line = ProductionLine::create([
            'company_id'  => $companyId,
            'code'        => strtoupper($request->code),
            'name'        => $request->name,
            'description' => $request->description,
            'is_active'   => $request->get('is_active', true),
        ]);

        return ApiResponse::success($line, 'Línea de producción creada exitosamente', 201);
    }

    /**
     * Mostrar una línea de producción.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $line = ProductionLine::byCompany($companyId)->find($id);
        if (!$line) {
            return ApiResponse::notFound('Línea de producción no encontrada');
        }

        return ApiResponse::success($line, 'Línea de producción recuperada exitosamente');
    }

    /**
     * Actualizar una línea de producción.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $line = ProductionLine::byCompany($companyId)->find($id);
        if (!$line) {
            return ApiResponse::notFound('Línea de producción no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'code'        => 'sometimes|string|max:50',
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        if ($request->filled('code') && strtoupper($request->code) !== $line->code) {
            $exists = ProductionLine::byCompany($companyId)
                ->where('code', strtoupper($request->code))
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return ApiResponse::error('Ya existe una línea de producción con ese código para esta empresa', 422);
            }
        }

        $line->update($request->only(['code', 'name', 'description', 'is_active']));

        return ApiResponse::success($line->fresh(), 'Línea de producción actualizada exitosamente');
    }

    /**
     * Eliminar (soft delete) una línea de producción.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $line = ProductionLine::byCompany($companyId)->find($id);
        if (!$line) {
            return ApiResponse::notFound('Línea de producción no encontrada');
        }

        $assetsCount = $line->assets()->count();
        if ($assetsCount > 0) {
            return ApiResponse::error(
                "No se puede eliminar: hay {$assetsCount} activo(s) asignados a esta línea de producción",
                422
            );
        }

        $line->delete();

        return ApiResponse::success(null, 'Línea de producción eliminada exitosamente');
    }
}
