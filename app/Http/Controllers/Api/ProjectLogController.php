<?php

namespace App\Http\Controllers\Api;

use App\Events\ProjectUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectLogResource;
use App\Models\Project;
use App\Models\ProjectLog;
use App\Models\ProjectLogStatus;
use App\Models\ProjectMember;
use App\Models\ProjectPhase;
use App\Http\Responses\ApiResponse;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectLogController extends Controller
{
    public function index(Request $request, int $projectId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $query = ProjectLog::where('project_id', $projectId)
            ->with(['status', 'user', 'loggedBy', 'phase', 'attachments.attachmentType']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('phase_id')) {
            $query->where('phase_id', $request->phase_id);
        }
        if ($request->filled('status_code')) {
            $query->whereHas('status', fn($q) => $q->where('code', $request->status_code));
        }
        if ($request->filled('date_from')) {
            $query->where('log_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('log_date', '<=', $request->date_to);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);
        $logs = $query->orderBy('log_date', 'desc')->paginate($perPage);

        $items = collect($logs->items())
            ->map(fn($l) => (new ProjectLogResource($l))->toArray($request))
            ->values();

        return ApiResponse::paginated(
            $items,
            [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
                'from'         => $logs->firstItem(),
                'to'           => $logs->lastItem(),
            ],
            'Bitácoras obtenidas exitosamente'
        );
    }

    public function store(Request $request, int $projectId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $authId    = auth()->id();

        $project = Project::byCompany($companyId)->with('status')->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        if ($project->status?->code !== 'in_progress') {
            return ApiResponse::error('Solo se puede registrar bitácora en proyectos En ejecución', 422);
        }

        $hasPhases = $project->phases()->exists();

        $validator = Validator::make($request->all(), [
            'user_id'              => 'required|integer|exists:users,id',
            'phase_id'             => $hasPhases
                                        ? 'required|integer|exists:project_phases,id'
                                        : 'nullable|integer|exists:project_phases,id',
            'log_date'             => 'required|date',
            'hours_worked'         => 'required|numeric|min:0.5|max:24',
            'activity_description' => 'required|string',
            'result_description'   => 'required|string',
            'findings'             => 'nullable|string',
            'deliverables'         => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $userId   = $request->user_id;
        $authUser = $request->user();

        // Verificar permiso para registrar a nombre de otro usuario
        if ($userId !== $authId) {
            $canLogForTeam = $authUser->hasRole('SUPER_ADMIN') ||
                             $authUser->hasRole('ADMIN') ||
                             $authUser->hasPermission('PROJECTS.LOG_TEAM') ||
                             (int) $project->leader_id === $authId;

            if (!$canLogForTeam) {
                return ApiResponse::error('No tienes permiso para registrar bitácora en nombre de otro usuario', 403);
            }
        }

        // Validar que el usuario está asignado al proyecto
        $member = ProjectMember::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();

        if (!$member) {
            return ApiResponse::error('El usuario no está asignado a este proyecto', 422);
        }

        // Validar máximo 24h por persona por día por proyecto
        $hoursToday = ProjectLog::where('project_id', $projectId)
            ->where('user_id', $userId)
            ->where('log_date', $request->log_date)
            ->sum('hours_worked');

        if (($hoursToday + $request->hours_worked) > 24) {
            return ApiResponse::error(
                "El usuario ya tiene {$hoursToday}h registradas para este proyecto en esa fecha. No puede superar 24h",
                422
            );
        }

        // Calcular costo de MO
        $laborCost = null;
        if ($member->hourly_rate) {
            $laborCostEnabled = DB::table('company_settings')
                ->where('company_id', $companyId)
                ->where('key', 'projects.labor_cost_enabled')
                ->value('value');

            if ($laborCostEnabled === 'true') {
                $laborCost = round($request->hours_worked * $member->hourly_rate, 2);
            }
        }

        $registeredStatus = ProjectLogStatus::where('code', 'registered')->first();

        $meta = [];

        DB::beginTransaction();
        try {
            $log = ProjectLog::create([
                'project_id'           => $projectId,
                'phase_id'             => $request->phase_id,
                'status_id'            => $registeredStatus->id,
                'user_id'              => $userId,
                'logged_by'            => $authId,
                'log_date'             => $request->log_date,
                'hours_worked'         => $request->hours_worked,
                'activity_description' => $request->activity_description,
                'result_description'   => $request->result_description,
                'findings'             => $request->findings,
                'deliverables'         => $request->deliverables,
                'labor_cost'           => $laborCost,
            ]);

            $project->recalculateCost();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al registrar la bitácora', 500);
        }

        // Notificar al líder que hay un registro nuevo pendiente de revisión
        event(new ProjectUpdated($project, 'log_submitted', [
            'log_id'   => $log->id,
            'log_date' => $log->log_date,
            'phase'    => $log->phase?->name,
            'user_id'  => $log->user_id,
        ]));

        return ApiResponse::success(
            new ProjectLogResource($log->load(['status', 'user', 'loggedBy', 'phase'])),
            'Bitácora registrada exitosamente',
            201,
            $meta
        );
    }

    public function update(Request $request, int $projectId, int $logId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $authUser  = auth()->user();
        $authId    = $authUser->id;

        $project = Project::byCompany($companyId)->with('status')->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        if ($project->status?->is_terminal) {
            return ApiResponse::error('No se puede editar la bitácora de un proyecto en estado terminal', 422);
        }

        $log = ProjectLog::where('project_id', $projectId)->with('status')->find($logId);
        if (!$log) {
            return ApiResponse::notFound('Bitácora no encontrada');
        }

        // Solo el dueño del log, o alguien con permiso de equipo / admin puede editar
        $canEditOwn  = (int) $log->user_id === $authId;
        $canEditTeam = $authUser->hasRole('SUPER_ADMIN') ||
                       $authUser->hasRole('ADMIN') ||
                       $authUser->hasPermission('PROJECTS.LOG_TEAM');

        if (!$canEditOwn && !$canEditTeam) {
            return ApiResponse::error('No tienes permiso para editar esta bitácora', 403);
        }

        if (in_array($log->status?->code, ['reviewed', 'validated'])) {
            return ApiResponse::error('No se puede editar una bitácora ya revisada o validada', 422);
        }

        $validator = Validator::make($request->all(), [
            'phase_id'             => 'nullable|integer|exists:project_phases,id',
            'hours_worked'         => 'sometimes|numeric|min:0.5|max:24',
            'activity_description' => 'sometimes|string',
            'result_description'   => 'sometimes|string',
            'findings'             => 'nullable|string',
            'deliverables'         => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Revalidar límite de 24h/día si se está cambiando hours_worked
        if ($request->has('hours_worked')) {
            $hoursOthers = ProjectLog::where('project_id', $projectId)
                ->where('user_id', $log->user_id)
                ->where('log_date', $log->log_date)
                ->where('id', '!=', $logId)
                ->sum('hours_worked');

            if (($hoursOthers + $request->hours_worked) > 24) {
                return ApiResponse::error(
                    "Ya existen {$hoursOthers}h registradas para este usuario en esa fecha. No puede superar 24h",
                    422
                );
            }
        }

        $oldPhaseId = $log->phase_id;
        $meta       = [];

        DB::beginTransaction();
        try {
            $log->update($request->only([
                'phase_id', 'hours_worked', 'activity_description',
                'result_description', 'findings', 'deliverables',
            ]));

            $project->recalculateCost();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar la bitácora', 500);
        }

        return ApiResponse::success(
            new ProjectLogResource($log->load(['status', 'user', 'loggedBy', 'phase'])),
            'Bitácora actualizada exitosamente',
            200,
            $meta
        );
    }

    public function review(Request $request, int $projectId, int $logId): JsonResponse
    {
        return $this->changeLogStatus($request, $projectId, $logId, 'reviewed', function ($log) {
            $log->update(['reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        });
    }

    public function validate(Request $request, int $projectId, int $logId): JsonResponse
    {
        return $this->changeLogStatus($request, $projectId, $logId, 'validated', function ($log) {
            $log->update(['validated_by' => auth()->id(), 'validated_at' => now()]);
        });
    }

    public function exportPdf(Request $request, int $projectId)
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)
            ->with(['type', 'status', 'leader', 'company'])
            ->find($projectId);

        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $query = ProjectLog::where('project_id', $projectId)
            ->with(['status', 'user', 'phase']);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('phase_id')) {
            $query->where('phase_id', $request->phase_id);
        }
        if ($request->filled('status_code')) {
            $query->whereHas('status', fn($q) => $q->where('code', $request->status_code));
        }
        if ($request->filled('date_from')) {
            $query->where('log_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('log_date', '<=', $request->date_to);
        }

        $logs = $query->orderBy('log_date', 'asc')->get();

        $totalHours    = $logs->sum('hours_worked');
        $totalLaborCost = $logs->sum('labor_cost');
        $uniquePersons = $logs->pluck('user_id')->unique()->count();

        $logoBase64 = $this->getLogoBase64();

        try {
            $pdf = Pdf::loadView('pdf.project-logs', [
                'project'       => $project,
                'logs'          => $logs,
                'totalHours'    => $totalHours,
                'totalLaborCost' => $totalLaborCost,
                'uniquePersons' => $uniquePersons,
                'logoBase64'    => $logoBase64,
                'filters'       => $request->only(['date_from', 'date_to', 'user_id', 'phase_id']),
            ])->setPaper('a4', 'landscape');

            $filename = "Bitacora_{$project->code}.pdf";
            return $pdf->download($filename);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al generar el PDF: ' . $e->getMessage(), 500);
        }
    }

    // -------------------------------------------------------------------------

    private function getLogoBase64(): ?string
    {
        $path = public_path('logo-recylo.png');
        if (!file_exists($path)) return null;
        $mime = mime_content_type($path);
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
    }

    // -------------------------------------------------------------------------

    private function changeLogStatus(Request $request, int $projectId, int $logId, string $targetCode, callable $after = null): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $project = Project::byCompany($companyId)->find($projectId);
        if (!$project) {
            return ApiResponse::notFound('Proyecto no encontrado');
        }

        $log = ProjectLog::where('project_id', $projectId)->with('status')->find($logId);
        if (!$log) {
            return ApiResponse::notFound('Bitácora no encontrada');
        }

        $newStatus = ProjectLogStatus::where('code', $targetCode)->first();
        if (!$newStatus) {
            return ApiResponse::error("Estado '{$targetCode}' no encontrado", 422);
        }

        DB::beginTransaction();
        try {
            $log->update(['status_id' => $newStatus->id]);

            if ($after) {
                $after($log);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al cambiar el estado de la bitácora', 500);
        }

        return ApiResponse::success(
            new ProjectLogResource($log->load(['status', 'user', 'loggedBy', 'phase'])),
            "Bitácora marcada como '{$newStatus->name}'"
        );
    }
}
