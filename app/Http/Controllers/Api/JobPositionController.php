<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\JobPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class JobPositionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $query = JobPosition::byCompany($companyId)->orderBy('name');

        // Si no se pide explícitamente todos, devolver solo activos (para selects del formulario)
        if ($request->query('all') !== '1') {
            $query->active();
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('code', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('is_active') && $request->query('all') === '1') {
            $query->where('is_active', (bool) $request->is_active);
        }

        $positions = $query->get(['id', 'name', 'code', 'is_active', 'can_lead_projects']);

        return ApiResponse::success($positions, 'Cargos obtenidos exitosamente');
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:120',
            'code'              => 'nullable|string|max:50',
            'can_lead_projects' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Unicidad de código por empresa
        if ($request->filled('code')) {
            $exists = JobPosition::byCompany($companyId)
                ->where('code', $request->code)
                ->exists();
            if ($exists) {
                return ApiResponse::validation(['code' => ['Ya existe un cargo con este código en la empresa']]);
            }
        }

        $position = JobPosition::create([
            'company_id'         => $companyId,
            'name'               => $request->name,
            'code'               => $request->code,
            'is_active'          => true,
            'can_lead_projects'  => $request->boolean('can_lead_projects', false),
        ]);

        return ApiResponse::success($position, 'Cargo creado exitosamente', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $position = JobPosition::byCompany($companyId)->find($id);
        if (!$position) {
            return ApiResponse::notFound('Cargo no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'name'               => 'sometimes|string|max:120',
            'code'               => 'nullable|string|max:50',
            'is_active'          => 'boolean',
            'can_lead_projects'  => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        if ($request->filled('code') && $request->code !== $position->code) {
            $exists = JobPosition::byCompany($companyId)
                ->where('code', $request->code)
                ->where('id', '!=', $id)
                ->exists();
            if ($exists) {
                return ApiResponse::validation(['code' => ['Ya existe un cargo con este código en la empresa']]);
            }
        }

        $position->update($request->only(['name', 'code', 'is_active', 'can_lead_projects']));

        return ApiResponse::success($position, 'Cargo actualizado exitosamente');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $position = JobPosition::byCompany($companyId)->find($id);
        if (!$position) {
            return ApiResponse::notFound('Cargo no encontrado');
        }

        $position->delete();

        return ApiResponse::success(null, 'Cargo eliminado exitosamente');
    }
}
