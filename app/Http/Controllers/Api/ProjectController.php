<?php

namespace App\Http\Controllers\Api;

use App\Events\ProjectUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectLogResource;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectStatus;
use App\Models\ProjectType;
use App\Models\ProjectMemberRole;
use App\Models\ProjectLogStatus;
use App\Models\UserCompany;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectController extends Controller
{
    // =========================================================================
    // INDEX
    // =========================================================================

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId    = auth()->id();

        $query = Project::byCompany($companyId)
            ->with([
                'type',
                'status',
                'leader:id,first_name,last_name,email',
            ])
            ->withCount(['members', 'logs']);

        // Filtros
        if ($request->filled('status_code')) {
            $query->whereHas('status', fn($q) => $q->where('code', $request->status_code));
        }
        if ($request->filled('type_code')) {
            $query->whereHas('type', fn($q) => $q->where('code', $request->type_code));
        }
        if ($request->filled('leader_id')) {
            $query->where('leader_id', $request->leader_id);
        }
        if ($request->filled('area_id')) {
            $query->where('area_id', $request->area_id);
        }
        if ($request->filled('date_from')) {
            $query->where('planned_start_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('planned_end_date', '<=', $request->date_to);
        }
        if ($request->boolean('overdue')) {
            $query->overdue();
        }
        if ($request->boolean('all')) {
            $user = auth()->user();
            $isSuperAdmin = $user->hasRole('SUPER_ADMIN') || $user->hasRole('ADMIN');

            if (!$isSuperAdmin) {
                // Ver todos: excluir borradores ajenos (draft solo visible para su líder/creador)
                $query->where(function ($q) use ($userId) {
                    $q->whereHas('status', fn($s) => $s->where('code', '!=', 'draft'))
                      ->orWhere('leader_id', $userId)
                      ->orWhere('created_by', $userId);
                });
            }
            // Super Admin / Admin: sin restricciones — ve absolutamente todo
        } else {
            // Solo los proyectos donde el usuario es miembro o líder
            $query->where(function ($q) use ($userId) {
                $q->where('leader_id', $userId)
                  ->orWhereHas('members', fn($m) => $m->where('user_id', $userId)->where('is_active', true));
            });
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $projects = $query->orderBy('planned_start_date', 'desc')->paginate($perPage);

        return ApiResponse::paginated(
            $projects,
            [
                'current_page' => $projects->currentPage(),
                'last_page'    => $projects->lastPage(),
                'per_page'     => $projects->perPage(),
                'total'        => $projects->total(),
                'from'         => $projects->firstItem(),
                'to'           => $projects->lastItem(),
            ],
            'Proyectos obtenidos exitosamente'
        );
    }

    // =========================================================================
    // STORE
    // =========================================================================

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $validator = Validator::make($request->all(), [
            'name'              => 'required|string|max:150',
            'project_type_id'   => 'required|integer|exists:project_types,id',
            'leader_id'         => 'required|integer|exists:users,id',
            'planned_start_date'=> 'required|date',
            'planned_end_date'  => 'required|date|after_or_equal:planned_start_date',
            'description'       => 'nullable|string',
            'objective'         => 'nullable|string',
            'justification'     => 'nullable|string',
            'area_id'           => 'nullable|integer|exists:production_lines,id',
            'budget'            => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Verificar budget requerido según company_setting
        $budgetRequired = DB::table('company_settings')
            ->where('company_id', $companyId)
            ->where('key', 'projects.budget_required')
            ->value('value');

        if ($budgetRequired === 'true' && !$request->filled('budget')) {
            return ApiResponse::validation(['budget' => ['El presupuesto es requerido para esta empresa']]);
        }

        // Determinar status inicial
        $requiresApproval = DB::table('company_settings')
            ->where('company_id', $companyId)
            ->where('key', 'projects.requires_approval')
            ->value('value');

        $initialStatusCode = ($requiresApproval === 'true') ? 'pending_approval' : 'draft';
        $status = ProjectStatus::where('code', 'draft')->first();

        // Generar código
        $type = ProjectType::find($request->project_type_id);
        $code = Project::generateCode($companyId, $type->code ?? 'other');

        DB::beginTransaction();
        try {
            $project = Project::create([
                'company_id'         => $companyId,
                'code'               => $code,
                'name'               => $request->name,
                'project_type_id'    => $request->project_type_id,
                'status_id'          => $status->id,
                'description'        => $request->description,
                'objective'          => $request->objective,
                'justification'      => $request->justification,
                'leader_id'          => $request->leader_id,
                'area_id'            => $request->area_id,
                'planned_start_date' => $request->planned_start_date,
                'planned_end_date'   => $request->planned_end_date,
                'budget'             => $request->budget,
                'created_by'         => auth()->id(),
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear el proyecto', 500);
        }

        return ApiResponse::success(
            new ProjectResource($project->load(['type', 'status', 'leader:id,first_name,last_name,email'])),
            'Proyecto creado exitosamente',
            201
        );
    }

    // =========================================================================
    // SHOW
    // =========================================================================

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)
            ->with([
                'type', 'status',
                'leader:id,first_name,last_name,email',
                'area:id,name',
                'createdBy:id,first_name,last_name',
                'approvedBy:id,first_name,last_name',
                'closedBy:id,first_name,last_name',
                'cancelledBy:id,first_name,last_name',
                'phases.status', 'phases.responsible:id,first_name,last_name', 'phases.technicians:id,first_name,last_name',
                'activeMembers.user:id,first_name,last_name,email', 'activeMembers.role',
                'workOrders.assignedTo:id,first_name,last_name',
            ])
            ->withCount(['workOrders'])
            ->find($id);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        return ApiResponse::success(new ProjectResource($project), 'Proyecto obtenido exitosamente');
    }

    // =========================================================================
    // UPDATE
    // =========================================================================

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($id);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        if ($project->status?->is_terminal) {
            return ApiResponse::error('No se puede editar un proyecto en estado ' . $project->status->name, 422);
        }

        $validator = Validator::make($request->all(), [
            'name'              => 'sometimes|string|max:150',
            'project_type_id'   => 'sometimes|integer|exists:project_types,id',
            'leader_id'         => 'sometimes|integer|exists:users,id',
            'planned_start_date'=> 'sometimes|date',
            'planned_end_date'  => 'sometimes|date|after_or_equal:planned_start_date',
            'description'       => 'nullable|string',
            'objective'         => 'nullable|string',
            'justification'     => 'nullable|string',
            'area_id'           => 'nullable|integer|exists:production_lines,id',
            'budget'            => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        DB::beginTransaction();
        try {
            $project->update(array_merge(
                $request->only([
                    'name', 'project_type_id', 'leader_id', 'area_id',
                    'planned_start_date', 'planned_end_date',
                    'description', 'objective', 'justification', 'budget',
                ]),
                ['updated_by' => auth()->id()]
            ));
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar el proyecto', 500);
        }

        return ApiResponse::success(
            new ProjectResource($project->load(['type', 'status', 'leader:id,first_name,last_name,email'])),
            'Proyecto actualizado exitosamente'
        );
    }

    // =========================================================================
    // DESTROY
    // =========================================================================

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($id);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        if ($project->status?->code !== 'draft') {
            return ApiResponse::error('Solo se pueden eliminar proyectos en estado Borrador', 422);
        }

        $project->delete();

        return ApiResponse::success(null, 'Proyecto eliminado exitosamente');
    }

    // =========================================================================
    // TRANSICIONES DE ESTADO
    // =========================================================================

    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'approved', function ($project) {
            $project->update(['approved_by' => auth()->id(), 'approved_at' => now()]);
        });
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $project   = Project::byCompany($companyId)->with('phases')->find($id);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        // Validar que los pesos de las fases sumen 100% (si hay fases con peso definido)
        $phases      = $project->phases;
        $totalWeight = $phases->sum('weight_percent');

        if ($phases->isNotEmpty() && $totalWeight > 0 && abs($totalWeight - 100) > 0.01) {
            return ApiResponse::error(
                "El peso total de las fases es {$totalWeight}%. Debe ser exactamente 100% antes de iniciar la ejecución.",
                422
            );
        }

        return $this->transition($request, $id, 'in_progress', function ($project) {
            $project->update(['actual_start_date' => now()->toDateString()]);
        });
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'paused');
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $project   = Project::byCompany($companyId)->with(['phases.status'])->find($id);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        // Todas las fases deben estar completadas
        $incompletePhases = $project->phases->filter(fn($p) => $p->status?->code !== 'completed');
        if ($incompletePhases->isNotEmpty()) {
            $names = $incompletePhases->pluck('name')->implode(', ');
            return ApiResponse::error(
                "No se puede finalizar el proyecto. Las siguientes fases no están completadas: {$names}",
                422
            );
        }

        return $this->transition($request, $id, 'finished', function ($project) {
            $project->update(['actual_end_date' => now()->toDateString()]);
        });
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'closure_notes'   => 'required|string|min:10',
            'lessons_learned' => 'nullable|string',
        ], [
            'closure_notes.required' => 'Las notas de cierre son requeridas',
            'closure_notes.min'      => 'Las notas de cierre deben tener al menos 10 caracteres',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $companyId = $request->header('x-company-id');
        $project   = Project::byCompany($companyId)->with(['phases.status'])->find($id);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        // Todas las fases deben estar completadas
        $incompletePhases = $project->phases->filter(fn($p) => $p->status?->code !== 'completed');
        if ($incompletePhases->isNotEmpty()) {
            $names = $incompletePhases->pluck('name')->implode(', ');
            return ApiResponse::error(
                "No se puede cerrar el proyecto. Las siguientes fases no están completadas: {$names}",
                422
            );
        }

        // No puede haber bitácoras pendientes de revisión
        $unreviewedCount = ProjectLog::where('project_id', $id)
            ->whereHas('status', fn($q) => $q->where('code', 'registered'))
            ->count();

        if ($unreviewedCount > 0) {
            return ApiResponse::error(
                "No se puede cerrar el proyecto. Existen {$unreviewedCount} " .
                ($unreviewedCount === 1 ? 'entrada de bitácora sin revisar.' : 'entradas de bitácora sin revisar.'),
                422
            );
        }

        return $this->transition($request, $id, 'closed', function ($project) use ($request) {
            $project->update([
                'closure_notes'   => $request->closure_notes,
                'lessons_learned' => $request->lessons_learned,
                'closed_by'       => auth()->id(),
                'closed_at'       => now(),
            ]);
        });
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        return $this->transition($request, $id, 'cancelled', function ($project) {
            $project->update(['cancelled_by' => auth()->id(), 'cancelled_at' => now()]);
        });
    }

    // =========================================================================
    // CATÁLOGOS (para formularios)
    // =========================================================================

    public function catalogs(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        // Usuarios elegibles como líderes: tienen cargo con can_lead_projects = true en esta empresa
        $leaderUserIds = UserCompany::where('company_id', $companyId)
            ->whereNotNull('job_position_id')
            ->whereHas('jobPosition', fn($q) => $q->where('can_lead_projects', true))
            ->pluck('user_id');

        $leaders = \App\Models\User::whereIn('id', $leaderUserIds)
            ->where('status', 'active')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn($u) => [
                'id'        => $u->id,
                'full_name' => trim($u->first_name . ' ' . $u->last_name),
            ]);

        return ApiResponse::success([
            'statuses'      => ProjectStatus::active()->get(['id', 'code', 'name', 'color', 'is_terminal']),
            'types'         => ProjectType::active()->get(['id', 'code', 'name', 'code_prefix', 'icon']),
            'member_roles'  => ProjectMemberRole::active()->get(['id', 'code', 'name']),
            'log_statuses'  => ProjectLogStatus::active()->get(['id', 'code', 'name', 'color']),
            'leaders'       => $leaders,
        ], 'Catálogos obtenidos exitosamente');
    }

    // =========================================================================
    // RESUMEN / INDICADORES
    // =========================================================================

    public function summary(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)
            ->with(['type', 'status', 'phases.responsible', 'phases.technicians'])
            ->find($id);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        // Recalcular siempre para que el resumen refleje el estado real
        $project->recalculateProgress();
        $project->recalculateCost();
        $project->refresh();

        $totalHours = ProjectLog::where('project_id', $id)->sum('hours_worked');
        $totalCost  = ProjectLog::where('project_id', $id)->sum('labor_cost');

        $hoursByMember = ProjectLog::where('project_id', $id)
            ->with('user:id,first_name,last_name')
            ->selectRaw('user_id, SUM(hours_worked) as total_hours, SUM(labor_cost) as total_cost')
            ->groupBy('user_id')
            ->get();

        return ApiResponse::success([
            'project'          => new ProjectResource($project),
            'total_hours'      => round($totalHours, 2),
            'total_labor_cost' => round($totalCost, 2),
            'actual_cost'      => round($project->actual_cost ?? 0, 2),
            'hours_by_member'  => $hoursByMember,
            'phases_progress'  => $project->phases->map(fn($p) => [
                'id'               => $p->id,
                'name'             => $p->name,
                'progress_percent' => $p->progress_percent,
                'weight_percent'   => $p->weight_percent,
                'responsible'      => $p->responsible ? [
                    'id'        => $p->responsible->id,
                    'full_name' => $p->responsible->full_name,
                ] : null,
                'technicians'      => $p->technicians->map(fn($u) => [
                    'id'        => $u->id,
                    'full_name' => $u->full_name,
                ])->values(),
            ]),
        ], 'Resumen del proyecto obtenido exitosamente');
    }

    // =========================================================================
    // HELPER PRIVADO: TRANSICIÓN DE ESTADO
    // =========================================================================

    private function transition(Request $request, int $id, string $targetStatusCode, callable $after = null): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->with('status')->find($id);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        if ($project->status?->is_terminal) {
            return ApiResponse::error('El proyecto está en un estado terminal y no puede cambiar', 422);
        }

        $newStatus = ProjectStatus::where('code', $targetStatusCode)->first();

        if (!$newStatus) {
            return ApiResponse::error("Estado '{$targetStatusCode}' no encontrado", 422);
        }

        DB::beginTransaction();
        try {
            $project->update(['status_id' => $newStatus->id, 'updated_by' => auth()->id()]);

            if ($after) {
                $after($project);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al cambiar el estado del proyecto', 500);
        }

        event(new ProjectUpdated($project->fresh(), 'status_changed', [
            'status_code' => $targetStatusCode,
            'status_name' => $newStatus->name,
        ]));

        return ApiResponse::success(
            new ProjectResource($project->load([
                'type', 'status',
                'leader:id,first_name,last_name,email',
                'area:id,name',
                'phases.status', 'phases.responsible:id,first_name,last_name', 'phases.technicians:id,first_name,last_name',
                'activeMembers.user:id,first_name,last_name,email', 'activeMembers.role',
                'workOrders.assignedTo:id,first_name,last_name',
            ])),
            "Proyecto actualizado a '{$newStatus->name}' exitosamente"
        );
    }
}
