<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InspectionShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InspectionShiftController extends Controller
{
    public function index(): JsonResponse
    {
        $shifts = InspectionShift::orderBy('start_time')->get();
        return ApiResponse::success($shifts, 'Turnos recuperados exitosamente');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:100',
            'start_time' => 'required|date_format:H:i:s,H:i',
            'end_time'   => 'required|date_format:H:i:s,H:i',
            'is_active'  => 'nullable|boolean',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        $shift = InspectionShift::create([
            'name'       => $request->name,
            'start_time' => substr($request->start_time, 0, 5),
            'end_time'   => substr($request->end_time, 0, 5),
            'is_active'  => $request->get('is_active', true),
        ]);
        return ApiResponse::success($shift, 'Turno creado exitosamente', 201);
    }

    public function show(int $id): JsonResponse
    {
        $shift = InspectionShift::find($id);
        if (!$shift) return ApiResponse::notFound('Turno no encontrado');
        return ApiResponse::success($shift);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $shift = InspectionShift::find($id);
        if (!$shift) return ApiResponse::notFound('Turno no encontrado');

        $validator = Validator::make($request->all(), [
            'name'       => 'sometimes|string|max:100',
            'start_time' => 'sometimes|date_format:H:i:s,H:i',
            'end_time'   => 'sometimes|date_format:H:i:s,H:i',
            'is_active'  => 'nullable|boolean',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        $updateData = $request->only(['name', 'is_active']);
        if ($request->filled('start_time')) $updateData['start_time'] = substr($request->start_time, 0, 5);
        if ($request->filled('end_time'))   $updateData['end_time']   = substr($request->end_time, 0, 5);
        $shift->update($updateData);
        return ApiResponse::success($shift->fresh(), 'Turno actualizado exitosamente');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user      = Auth::user();
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INSPECTIONS_DELETE_ADMIN', $companyId)) {
            return ApiResponse::error('No tienes permiso para eliminar turnos de inspección', 403);
        }

        $shift = InspectionShift::find($id);
        if (!$shift) return ApiResponse::notFound('Turno no encontrado');

        $shift->delete();
        return ApiResponse::success(null, 'Turno eliminado exitosamente');
    }
}
