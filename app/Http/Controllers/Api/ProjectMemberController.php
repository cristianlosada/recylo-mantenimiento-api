<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectMemberResource;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\UserCompany;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectMemberController extends Controller
{
    public function index(Request $request, int $projectId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $members = ProjectMember::where('project_id', $projectId)
            ->where('is_active', true)
            ->with(['user', 'role'])
            ->get();

        return ApiResponse::success(
            ProjectMemberResource::collection($members),
            'Miembros obtenidos exitosamente'
        );
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'user_id'     => 'required|integer|exists:users,id',
            'role_id'     => 'required|integer|exists:project_member_roles,id',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Auto-precargar tarifa horaria del usuario si no se envió
        if (!$request->filled('hourly_rate')) {
            $userCompany = UserCompany::where('user_id', $request->user_id)
                ->where('company_id', $companyId)
                ->value('hourly_rate');
            $request->merge(['hourly_rate' => $userCompany]);
        }

        // Verificar si ya es miembro activo
        $existing = ProjectMember::where('project_id', $projectId)
            ->where('user_id', $request->user_id)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return ApiResponse::error('El usuario ya es miembro activo de este proyecto', 422);
        }

        DB::beginTransaction();
        try {
            // Si existía como inactivo, reactivar; si no, crear
            $member = ProjectMember::where('project_id', $projectId)
                ->where('user_id', $request->user_id)
                ->first();

            if ($member) {
                $member->update([
                    'role_id'     => $request->role_id,
                    'hourly_rate' => $request->hourly_rate,
                    'is_active'   => true,
                    'assigned_at' => now()->toDateString(),
                    'assigned_by' => auth()->id(),
                ]);
            } else {
                $member = ProjectMember::create([
                    'project_id'  => $projectId,
                    'user_id'     => $request->user_id,
                    'role_id'     => $request->role_id,
                    'hourly_rate' => $request->hourly_rate,
                    'assigned_at' => now()->toDateString(),
                    'assigned_by' => auth()->id(),
                    'is_active'   => true,
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al agregar el miembro', 500);
        }

        return ApiResponse::success(
            new ProjectMemberResource($member->load(['user', 'role'])),
            'Miembro agregado exitosamente',
            201
        );
    }

    public function update(Request $request, int $projectId, int $memberId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $member = ProjectMember::where('project_id', $projectId)->find($memberId);
        if (!$member) {
            return ApiResponse::notFound('Miembro no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'role_id'     => 'nullable|integer|exists:project_member_roles,id',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        DB::beginTransaction();
        try {
            $member->update($request->only(['role_id', 'hourly_rate']));
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar el miembro', 500);
        }

        return ApiResponse::success(
            new ProjectMemberResource($member->load(['user', 'role'])),
            'Miembro actualizado exitosamente'
        );
    }

    public function destroy(Request $request, int $projectId, int $memberId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $member = ProjectMember::where('project_id', $projectId)->find($memberId);
        if (!$member) {
            return ApiResponse::notFound('Miembro no encontrado');
        }

        DB::beginTransaction();
        try {
            // Soft-remove: marcar como inactivo en lugar de eliminar
            $member->update(['is_active' => false]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al remover el miembro', 500);
        }

        return ApiResponse::success(null, 'Miembro removido del proyecto');
    }
}
