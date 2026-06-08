<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectPhaseResource;
use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\ProjectPhaseStatus;
use App\Models\ProjectPhaseStatusHistory;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectPhaseController extends Controller
{
    public function index(Request $request, int $projectId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phases = ProjectPhase::where('project_id', $projectId)
            ->with(['status', 'responsible', 'technicians'])
            ->orderBy('order_index')
            ->get();

        return ApiResponse::success(
            ProjectPhaseResource::collection($phases),
            'Fases obtenidas exitosamente'
        );
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $projStart = $project->planned_start_date?->toDateString();
        $projEnd   = $project->planned_end_date?->toDateString();

        $validator = Validator::make($request->all(), [
            'name'               => 'required|string|max:120',
            'description'        => 'nullable|string',
            'order_index'        => 'nullable|integer|min:0',
            'responsible_id'     => 'required|integer|exists:users,id',
            'technician_ids'     => 'nullable|array',
            'technician_ids.*'   => 'integer|exists:users,id',
            'planned_start_date' => array_filter(['nullable', 'date', $projStart ? "after_or_equal:{$projStart}" : null]),
            'planned_end_date'   => array_filter(['nullable', 'date', 'after_or_equal:planned_start_date', $projEnd ? "before_or_equal:{$projEnd}" : null]),
            'weight_percent'     => 'nullable|numeric|min:0|max:100',
        ], [
            'responsible_id.required'           => 'El responsable principal es obligatorio',
            'planned_start_date.after_or_equal' => "La fecha de inicio no puede ser anterior al inicio del proyecto ({$projStart})",
            'planned_end_date.before_or_equal'  => "La fecha de fin no puede superar el fin del proyecto ({$projEnd})",
            'planned_end_date.after_or_equal'   => 'La fecha de fin debe ser igual o posterior a la de inicio',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Validar que el peso no supere el disponible
        $newWeight = (float) $request->input('weight_percent', 0);
        if ($newWeight > 0) {
            $usedWeight = ProjectPhase::where('project_id', $projectId)->sum('weight_percent');
            $available  = round(100 - $usedWeight, 2);
            if ($newWeight > $available + 0.01) {
                return ApiResponse::validation(['weight_percent' => ["El peso excede el disponible. Máximo disponible: {$available}%"]]);
            }
        }

        $pendingStatus = ProjectPhaseStatus::where('code', 'pending')->first();
        $orderIndex    = $request->input('order_index',
            ProjectPhase::where('project_id', $projectId)->max('order_index') + 1
        );
        $userId = $request->user()->id;

        $warnings = $this->detectOverlaps($projectId, $request->planned_start_date, $request->planned_end_date);

        DB::beginTransaction();
        try {
            $phase = ProjectPhase::create([
                'project_id'         => $projectId,
                'status_id'          => $pendingStatus->id,
                'name'               => $request->name,
                'description'        => $request->description,
                'order_index'        => $orderIndex,
                'responsible_id'     => $request->responsible_id,
                'planned_start_date' => $request->planned_start_date,
                'planned_end_date'   => $request->planned_end_date,
                'weight_percent'     => $request->input('weight_percent', 0),
                'progress_percent'   => 0,
            ]);

            if ($request->filled('technician_ids')) {
                $syncData = collect($request->technician_ids)->mapWithKeys(fn($uid) => [
                    $uid => ['assigned_by' => $userId, 'assigned_at' => now()],
                ])->toArray();
                $phase->technicians()->sync($syncData);
            }

            ProjectPhaseStatusHistory::create([
                'phase_id'       => $phase->id,
                'type'           => 'created',
                'from_status_id' => null,
                'to_status_id'   => null,
                'changed_by'     => $userId,
                'changed_at'     => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear la fase', 500);
        }

        $meta = $warnings ? ['warnings' => $warnings] : [];

        return ApiResponse::success(
            new ProjectPhaseResource($phase->load(['status', 'responsible', 'technicians'])),
            'Fase creada exitosamente',
            201,
            $meta
        );
    }

    public function update(Request $request, int $projectId, int $phaseId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phase = ProjectPhase::where('project_id', $projectId)->with('status')->find($phaseId);
        if (!$phase) {
            return ApiResponse::notFound('Fase no encontrada');
        }

        $projStart = $project->planned_start_date?->toDateString();
        $projEnd   = $project->planned_end_date?->toDateString();

        $validator = Validator::make($request->all(), [
            'name'               => 'sometimes|string|max:120',
            'description'        => 'nullable|string',
            'order_index'        => 'nullable|integer|min:0',
            'responsible_id'     => 'sometimes|required|integer|exists:users,id',
            'technician_ids'     => 'nullable|array',
            'technician_ids.*'   => 'integer|exists:users,id',
            'planned_start_date' => array_filter(['nullable', 'date', $projStart ? "after_or_equal:{$projStart}" : null]),
            'planned_end_date'   => array_filter(['nullable', 'date', 'after_or_equal:planned_start_date', $projEnd ? "before_or_equal:{$projEnd}" : null]),
            'actual_start_date'  => 'nullable|date',
            'actual_end_date'    => 'nullable|date',
            'weight_percent'     => 'nullable|numeric|min:0|max:100',
            'progress_percent'   => 'nullable|numeric|min:0|max:100',
            'status_id'          => 'nullable|integer|exists:project_phase_statuses,id',
        ], [
            'responsible_id.required'           => 'El responsable principal es obligatorio',
            'planned_start_date.after_or_equal' => "La fecha de inicio no puede ser anterior al inicio del proyecto ({$projStart})",
            'planned_end_date.before_or_equal'  => "La fecha de fin no puede superar el fin del proyecto ({$projEnd})",
            'planned_end_date.after_or_equal'   => 'La fecha de fin debe ser igual o posterior a la de inicio',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Validar que el peso no supere el disponible (excluyendo esta fase)
        if ($request->has('weight_percent')) {
            $newWeight  = (float) $request->input('weight_percent', 0);
            $otherTotal = ProjectPhase::where('project_id', $projectId)->where('id', '!=', $phaseId)->sum('weight_percent');
            $available  = round(100 - $otherTotal, 2);
            if ($newWeight > $available + 0.01) {
                return ApiResponse::validation(['weight_percent' => ["El peso excede el disponible. Máximo disponible: {$available}%"]]);
            }
        }

        $user   = $request->user();
        $userId = $user->id;

        $canOverrideProgress = $project->leader_id === $userId
            || $user->hasRole('SUPER_ADMIN')
            || $user->hasRole('ADMIN');

        $newStart = $request->input('planned_start_date', $phase->planned_start_date?->toDateString());
        $newEnd   = $request->input('planned_end_date',   $phase->planned_end_date?->toDateString());
        $warnings = $this->detectOverlaps($projectId, $newStart, $newEnd, excludePhaseId: $phaseId);

        DB::beginTransaction();
        try {
            $fields = [
                'name', 'description', 'order_index', 'responsible_id',
                'planned_start_date', 'planned_end_date',
                'actual_start_date', 'actual_end_date',
                'weight_percent', 'status_id',
            ];
            if ($canOverrideProgress) {
                $fields[] = 'progress_percent';
            }

            // Capture old values before update for diff
            $trackedLabels = [
                'name'               => 'Nombre',
                'description'        => 'Descripción',
                'planned_start_date' => 'Fecha inicio planificada',
                'planned_end_date'   => 'Fecha fin planificada',
                'weight_percent'     => 'Peso (%)',
                'progress_percent'   => 'Avance (%)',
                'responsible_id'     => 'Responsable',
            ];
            $oldSnap = [];
            foreach (array_keys($trackedLabels) as $f) {
                $val = $phase->$f;
                $oldSnap[$f] = $val instanceof \Carbon\Carbon ? $val->toDateString() : $val;
            }
            $oldResponsibleName = $phase->responsible?->full_name ?? $oldSnap['responsible_id'];

            // Detect status change for history
            $oldStatusId = $phase->status_id;
            $newStatusId = $request->input('status_id', $oldStatusId);

            $phase->update($request->only($fields));
            $phase->refresh();

            if ($oldStatusId !== (int) $newStatusId && $newStatusId) {
                ProjectPhaseStatusHistory::create([
                    'phase_id'       => $phase->id,
                    'type'           => 'status_changed',
                    'from_status_id' => $oldStatusId,
                    'to_status_id'   => (int) $newStatusId,
                    'changed_by'     => $userId,
                    'changed_at'     => now(),
                ]);
            }

            // Log field-level edits
            $diff = [];
            foreach ($trackedLabels as $f => $label) {
                if ($f === 'responsible_id') continue; // handled separately below
                $new = $phase->$f;
                $newStr = $new instanceof \Carbon\Carbon ? $new->toDateString() : $new;
                if ((string) $oldSnap[$f] !== (string) $newStr) {
                    $diff[$label] = ['from' => $oldSnap[$f], 'to' => $newStr];
                }
            }
            if ((string) $oldSnap['responsible_id'] !== (string) $phase->responsible_id) {
                $newResponsibleName = $phase->responsible?->full_name ?? $phase->responsible_id;
                $diff['Responsable'] = ['from' => $oldResponsibleName, 'to' => $newResponsibleName];
            }
            if (!empty($diff)) {
                ProjectPhaseStatusHistory::create([
                    'phase_id'    => $phase->id,
                    'type'        => 'edited',
                    'changed_by'  => $userId,
                    'changes'     => $diff,
                    'changed_at'  => now(),
                ]);
            }

            if ($request->has('technician_ids')) {
                $syncData = collect($request->technician_ids ?? [])->mapWithKeys(fn($uid) => [
                    $uid => ['assigned_by' => $userId, 'assigned_at' => now()],
                ])->toArray();
                $phase->technicians()->sync($syncData);
            }

            $autoCalculate = DB::table('company_settings')
                ->where('company_id', $companyId)
                ->where('key', 'projects.auto_calculate_progress')
                ->value('value');

            if ($autoCalculate !== 'false') {
                $project->recalculateProgress();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar la fase', 500);
        }

        $meta = $warnings ? ['warnings' => $warnings] : [];

        return ApiResponse::success(
            new ProjectPhaseResource($phase->load(['status', 'responsible', 'technicians'])),
            'Fase actualizada exitosamente',
            200,
            $meta
        );
    }

    /** Quick status transition with validation and history logging */
    public function changeStatus(Request $request, int $projectId, int $phaseId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phase = ProjectPhase::where('project_id', $projectId)->with('status')->find($phaseId);
        if (!$phase) {
            return ApiResponse::notFound('Fase no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'status_code' => 'required|string|exists:project_phase_statuses,code',
            'notes'       => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $allowedTransitions = [
            'pending'     => ['in_progress'],
            'in_progress' => ['on_hold', 'completed'],
            'on_hold'     => ['in_progress'],
            'completed'   => [],
        ];

        $currentCode = $phase->status?->code ?? 'pending';
        if (!in_array($request->status_code, $allowedTransitions[$currentCode] ?? [])) {
            return ApiResponse::error(
                "No se puede cambiar de '{$currentCode}' a '{$request->status_code}'",
                422
            );
        }

        $newStatus = ProjectPhaseStatus::where('code', $request->status_code)->first();

        DB::beginTransaction();
        try {
            $fromStatusId = $phase->status_id;

            $updateData = ['status_id' => $newStatus->id];
            if ($request->status_code === 'in_progress' && !$phase->actual_start_date) {
                $updateData['actual_start_date'] = now()->toDateString();
            }
            if ($request->status_code === 'completed' && !$phase->actual_end_date) {
                $updateData['actual_end_date'] = now()->toDateString();
            }
            $phase->update($updateData);

            ProjectPhaseStatusHistory::create([
                'phase_id'       => $phase->id,
                'type'           => 'status_changed',
                'from_status_id' => $fromStatusId,
                'to_status_id'   => $newStatus->id,
                'changed_by'     => auth()->id(),
                'notes'          => $request->notes,
                'changed_at'     => now(),
            ]);

            $autoCalculate = DB::table('company_settings')
                ->where('company_id', $companyId)
                ->where('key', 'projects.auto_calculate_progress')
                ->value('value');
            if ($autoCalculate !== 'false') {
                $project->recalculateProgress();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al cambiar el estado', 500);
        }

        return ApiResponse::success(
            new ProjectPhaseResource($phase->load(['status', 'responsible', 'technicians'])),
            "Estado actualizado a '{$newStatus->name}'"
        );
    }

    /** Status change history for a phase */
    public function statusHistory(Request $request, int $projectId, int $phaseId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $history = ProjectPhaseStatusHistory::where('phase_id', $phaseId)
            ->with(['fromStatus', 'toStatus', 'changedBy'])
            ->orderByDesc('changed_at')
            ->get();

        return ApiResponse::success(
            $history->map(fn($h) => [
                'id'          => $h->id,
                'type'        => $h->type ?? 'status_changed',
                'from_status' => $h->fromStatus
                    ? ['code' => $h->fromStatus->code, 'name' => $h->fromStatus->name, 'color' => $h->fromStatus->color]
                    : null,
                'to_status'   => $h->toStatus
                    ? ['code' => $h->toStatus->code, 'name' => $h->toStatus->name, 'color' => $h->toStatus->color]
                    : null,
                'changed_by'  => $h->changedBy ? ['id' => $h->changedBy->id, 'name' => $h->changedBy->full_name] : null,
                'notes'       => $h->notes,
                'changes'     => $h->changes,
                'changed_at'  => $h->changed_at,
            ])->values(),
            'Historial de cambios obtenido'
        );
    }

    public function destroy(Request $request, int $projectId, int $phaseId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phase = ProjectPhase::where('project_id', $projectId)->find($phaseId);
        if (!$phase) {
            return ApiResponse::notFound('Fase no encontrada');
        }

        if ($phase->logs()->exists()) {
            return ApiResponse::error('No se puede eliminar una fase con bitácoras registradas', 422);
        }

        DB::beginTransaction();
        try {
            $phase->delete();
            $project->recalculateProgress();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al eliminar la fase', 500);
        }

        return ApiResponse::success(null, 'Fase eliminada exitosamente');
    }

    public function updateProgress(Request $request, int $projectId, int $phaseId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $user      = $request->user();

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $phase = ProjectPhase::where('project_id', $projectId)->find($phaseId);
        if (!$phase) {
            return ApiResponse::notFound('Fase no encontrada');
        }

        $canUpdate = $user->hasRole('SUPER_ADMIN')
            || $user->hasRole('ADMIN')
            || $user->hasPermission('PROJECTS.UPDATE_PROGRESS')
            || (int) $project->leader_id === $user->id;

        if (!$canUpdate) {
            return ApiResponse::error('No tienes permiso para actualizar el avance de la fase', 403);
        }

        $validator = Validator::make($request->all(), [
            'progress_percent' => 'required|numeric|min:0|max:100',
            'notes'            => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $newProgress = round((float) $request->progress_percent, 2);
        $oldProgress = (float) $phase->progress_percent;

        DB::beginTransaction();
        try {
            $phase->update(['progress_percent' => $newProgress]);

            ProjectPhaseStatusHistory::create([
                'phase_id'   => $phase->id,
                'type'       => 'progress_updated',
                'changed_by' => $user->id,
                'notes'      => $request->notes ?? null,
                'changes'    => ['from' => $oldProgress, 'to' => $newProgress],
                'changed_at' => now(),
            ]);

            $project->recalculateProgress();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar el avance', 500);
        }

        return ApiResponse::success(
            new ProjectPhaseResource($phase->fresh()->load(['status', 'responsible'])),
            'Avance de fase actualizado correctamente'
        );
    }

    private function detectOverlaps(int $projectId, ?string $startDate, ?string $endDate, ?int $excludePhaseId = null): array
    {
        if (!$startDate || !$endDate) return [];

        $query = ProjectPhase::where('project_id', $projectId)
            ->whereNotNull('planned_start_date')
            ->whereNotNull('planned_end_date')
            ->where('planned_start_date', '<=', $endDate)
            ->where('planned_end_date', '>=', $startDate);

        if ($excludePhaseId) {
            $query->where('id', '!=', $excludePhaseId);
        }

        return $query->get(['id', 'name', 'planned_start_date', 'planned_end_date'])
            ->map(fn($ph) =>
                "Se traslapa con \"{$ph->name}\" ({$ph->planned_start_date->format('d/m/Y')} – {$ph->planned_end_date->format('d/m/Y')})"
            )->values()->toArray();
    }
}
