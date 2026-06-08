<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AssetMeter;
use App\Models\AssetMeterReading;
use App\Models\Inspection;
use App\Models\InspectionResponse;
use App\Models\InspectionResponsePhoto;
use App\Models\InspectionTemplate;
use App\Models\WorkRequest;
use App\Services\AssetMeterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InspectionController extends Controller
{
    // ── Listado ───────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $query = Inspection::byCompany($companyId)
            ->with([
                'asset:id,name,code',
                'operator:id,first_name,last_name',
                'shift:id,name',
                'template:id,name',
            ]);

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('asset_id')) $query->where('asset_id', $request->asset_id);
        if ($request->filled('operator_id')) $query->where('operator_id', $request->operator_id);
        if ($request->filled('has_findings')) $query->where('has_findings', $request->boolean('has_findings'));
        if ($request->filled('date_from')) $query->whereDate('inspection_date', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('inspection_date', '<=', $request->date_to);

        $query->orderBy('inspection_date', 'desc')->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 15);
        $inspections = $query->paginate($perPage);

        return response()->json([
            'data' => $inspections->items(),
            'meta' => [
                'total'        => $inspections->total(),
                'per_page'     => $inspections->perPage(),
                'current_page' => $inspections->currentPage(),
                'total_pages'  => $inspections->lastPage(),
            ],
        ]);
    }

    // ── Crear (borrador) ──────────────────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId    = $request->user()->id;

        // Look up template first to determine type (production_line vs yellow_machinery)
        $template = InspectionTemplate::byCompany($companyId)->find($request->template_id);
        if (!$template) return ApiResponse::notFound('Plantilla no encontrada');
        $isProductionLine = $template->template_type === 'production_line';

        $validator = Validator::make($request->all(), [
            'template_id'     => 'required|integer|exists:inspection_templates,id',
            'asset_id'        => $isProductionLine ? 'nullable|integer|exists:assets,id' : 'required|integer|exists:assets,id',
            'operator_id'     => 'required|integer|exists:users,id',
            'shift_id'        => 'nullable|integer|exists:inspection_shifts,id',
            'inspection_date' => 'required|date',
            'notes'           => 'nullable|string',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        $inspection = Inspection::create([
            'company_id'      => $companyId,
            'template_id'     => $request->template_id,
            'asset_id'        => $request->asset_id ?? null,
            'operator_id'     => $request->operator_id,
            'shift_id'        => $request->shift_id,
            'inspection_date' => $request->inspection_date,
            'status'          => 'draft',
            'has_findings'    => false,
            'notes'           => $request->notes,
            'created_by'      => $userId,
        ]);

        return ApiResponse::success(
            $inspection->load(['template.sections.items', 'asset', 'operator', 'shift']),
            'Inspección creada exitosamente',
            201
        );
    }

    // ── Detalle ───────────────────────────────────────────────────────────────

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $inspection = Inspection::byCompany($companyId)
            ->with([
                'template.sections.items',
                'asset:id,name,code',
                'operator:id,first_name,last_name',
                'shift:id,name,start_time,end_time',
                'workRequests:id,code,status,asset_id',
                'responses.item',
                'responses.photos',
            ])
            ->find($id);

        if (!$inspection) return ApiResponse::notFound('Inspección no encontrada');
        return ApiResponse::success($inspection);
    }

    // ── Editar cabecera (solo en borrador) ───────────────────────────────────

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $inspection = Inspection::byCompany($companyId)->where('status', 'draft')->find($id);
        if (!$inspection) return ApiResponse::notFound('Inspección no encontrada o ya completada');

        // Resolver plantilla activa para determinar si es production_line
        $templateId       = $request->input('template_id', $inspection->template_id);
        $template         = \App\Models\InspectionTemplate::find($templateId);
        $isProductionLine = $template?->template_type === 'production_line';

        $validator = Validator::make($request->all(), [
            'template_id'     => 'sometimes|integer|exists:inspection_templates,id',
            'asset_id'        => $isProductionLine ? 'nullable|integer|exists:assets,id' : 'sometimes|nullable|integer|exists:assets,id',
            'operator_id'     => 'sometimes|integer|exists:users,id',
            'shift_id'        => 'nullable|integer|exists:inspection_shifts,id',
            'inspection_date' => 'sometimes|date',
            'notes'           => 'nullable|string',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        // Normalizar asset_id: string vacío → null
        $updateData = $request->only(['template_id', 'asset_id', 'operator_id', 'shift_id', 'inspection_date', 'notes']);
        if (array_key_exists('asset_id', $updateData) && $updateData['asset_id'] === '') {
            $updateData['asset_id'] = null;
        }

        $inspection->update($updateData);

        return ApiResponse::success(
            $inspection->load(['template.sections.items', 'asset', 'operator', 'shift', 'responses.item', 'responses.photos']),
            'Inspección actualizada exitosamente'
        );
    }

    // ── Eliminar inspección ───────────────────────────────────────────────────

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $user      = $request->user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INSPECTIONS_DELETE_ADMIN', $companyId)) {
            return ApiResponse::error('No tienes permiso para eliminar inspecciones', 403);
        }

        $inspection = Inspection::byCompany($companyId)->find($id);
        if (!$inspection) return ApiResponse::notFound('Inspección no encontrada');

        $inspection->delete();
        return ApiResponse::success(null, 'Inspección eliminada exitosamente');
    }

    // ── Guardar respuestas (upsert parcial — borrador) ────────────────────────

    public function saveResponses(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $inspection = Inspection::byCompany($companyId)->where('status', 'draft')->find($id);
        if (!$inspection) return ApiResponse::notFound('Inspección no encontrada o ya completada');

        $validator = Validator::make($request->all(), [
            'responses'                    => 'nullable|array',
            'responses.*.item_id'          => 'required|integer|exists:inspection_items,id',
            'responses.*.response_value'   => 'nullable|string|max:100',
            'responses.*.observation'      => 'nullable|string',
            'responses.*.change_made'      => 'nullable|boolean',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        DB::beginTransaction();
        try {
            foreach ($request->responses ?? [] as $r) {
                $item = \App\Models\InspectionItem::find($r['item_id']);
                $ncValues = $item?->non_conformant_value !== null
                    ? array_map('trim', explode('|', $item->non_conformant_value))
                    : [];
                $isNonConformant = $item !== null
                    && ($r['response_value'] ?? null) !== null
                    && count($ncValues) > 0
                    && in_array(trim($r['response_value']), $ncValues);

                InspectionResponse::updateOrCreate(
                    ['inspection_id' => $id, 'item_id' => $r['item_id']],
                    [
                        'response_value'    => $r['response_value'] ?? null,
                        'is_non_conformant' => $isNonConformant,
                        'observation'       => $r['observation'] ?? null,
                        'change_made'       => $item?->item_type === 'lubrication' ? ($r['change_made'] ?? null) : null,
                    ]
                );
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al guardar respuestas', 500);
        }

        return ApiResponse::success(null, 'Respuestas guardadas exitosamente');
    }

    // ── Subir foto a una respuesta ────────────────────────────────────────────

    public function uploadPhoto(Request $request, int $inspectionId, int $responseId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $inspection = Inspection::byCompany($companyId)->find($inspectionId);
        if (!$inspection) return ApiResponse::notFound('Inspección no encontrada');

        $response = InspectionResponse::where('inspection_id', $inspectionId)->find($responseId);
        if (!$response) return ApiResponse::notFound('Respuesta no encontrada');

        if ($response->photos()->count() >= 3) {
            return ApiResponse::error('Máximo 3 fotos por ítem', 422);
        }

        $validator = Validator::make($request->all(), [
            'photo' => 'required|file|image|max:10240',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        $file = $request->file('photo');
        $path = $file->storeAs(
            "inspections/{$inspectionId}/responses/{$responseId}",
            Str::uuid() . '.' . $file->getClientOriginalExtension(),
            'public'
        );

        $photo = InspectionResponsePhoto::create([
            'response_id'   => $responseId,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'size'          => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
        ]);

        return ApiResponse::success($photo, 'Foto subida exitosamente', 201);
    }

    public function deletePhoto(Request $request, int $inspectionId, int $responseId, int $photoId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $inspection = Inspection::byCompany($companyId)->find($inspectionId);
        if (!$inspection) return ApiResponse::notFound('Inspección no encontrada');

        $photo = InspectionResponsePhoto::whereHas('response', fn($q) => $q->where('inspection_id', $inspectionId))
            ->find($photoId);
        if (!$photo) return ApiResponse::notFound('Foto no encontrada');

        Storage::disk('public')->delete($photo->file_path);
        $photo->delete();

        return ApiResponse::success(null, 'Foto eliminada exitosamente');
    }

    // ── Completar inspección ──────────────────────────────────────────────────

    public function complete(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId    = $request->user()->id;

        $inspection = Inspection::byCompany($companyId)
            ->with(['responses', 'template.sections.items', 'asset'])
            ->where('status', 'draft')
            ->find($id);

        if (!$inspection) return ApiResponse::notFound('Inspección no encontrada o ya completada');

        // Validar que todos los ítems del checklist tengan respuesta
        $allItemIds = collect();
        foreach ($inspection->template->sections as $section) {
            foreach ($section->items as $item) {
                $allItemIds->push($item->id);
            }
        }
        $respondedItemIds = $inspection->responses->pluck('item_id');
        $unanswered = $allItemIds->diff($respondedItemIds);
        if ($unanswered->isNotEmpty()) {
            return ApiResponse::error(
                'Faltan ' . $unanswered->count() . ' ítem(s) del checklist sin responder. Completa todos antes de finalizar.',
                422
            );
        }

        // Validate horometer if required by template
        $template = $inspection->template;
        $hoursMeter = null;
        if ($template && $template->requires_horometer) {
            if (!$request->filled('horometer_value')) {
                return ApiResponse::error('La lectura del horómetro es requerida para completar esta inspección', 422);
            }
            $request->validate([
                'horometer_value' => 'required|numeric|min:0',
            ]);

            $hoursMeter = AssetMeter::withTrashed()
                ->where('asset_id', $inspection->asset_id)
                ->where('meter_type', 'hours')
                ->first();

            if ($hoursMeter && $request->horometer_value < $hoursMeter->current_reading) {
                return ApiResponse::error(
                    "El valor del horómetro ({$request->horometer_value}) no puede ser menor que la lectura actual ({$hoursMeter->current_reading})",
                    422
                );
            }
        }

        $hasFindings = $inspection->responses->where('is_non_conformant', true)->isNotEmpty();

        DB::beginTransaction();
        try {
            $inspection->update([
                'status'       => 'completed',
                'has_findings' => $hasFindings,
                'completed_at' => now(),
            ]);

            // Register horometer reading — auto-create meter if it doesn't exist
            if ($template?->requires_horometer && $request->filled('horometer_value')) {
                if (!$hoursMeter) {
                    $hoursMeter = AssetMeter::create([
                        'asset_id'        => $inspection->asset_id,
                        'meter_type'      => 'hours',
                        'current_reading' => 0,
                        'unit'            => 'h',
                        'is_active'       => true,
                        'notes'           => 'Creado automáticamente desde inspección preoperacional',
                    ]);
                } elseif ($hoursMeter->trashed()) {
                    $hoursMeter->restore();
                    $hoursMeter->update(['is_active' => true]);
                }

                $hoursMeter->recordReading(
                    (float) $request->horometer_value,
                    [
                        'reading_date'    => now(),
                        'reading_source'  => AssetMeterReading::SOURCE_INSPECTION,
                        'inspection_id'   => $inspection->id,
                        'recorded_by'     => $userId,
                        'notes'           => "Registrado en inspección preoperacional #{$inspection->id}",
                    ]
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al completar la inspección: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success([
            'inspection'   => $inspection->fresh()->load(['asset', 'operator', 'shift']),
            'has_findings' => $hasFindings,
            'findings'     => $hasFindings ? $this->buildFindingsSummary($inspection) : [],
        ], 'Inspección completada exitosamente');
    }

    // ── Generar solicitud de mantenimiento desde hallazgos ────────────────────

    public function generateWorkRequest(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId    = $request->user()->id;

        $inspection = Inspection::byCompany($companyId)
            ->with([
                'responses.item.section.asset',
                'responses.photos',
                'asset',
                'template:id,template_type,name',
            ])
            ->where('status', 'completed')
            ->find($id);

        if (!$inspection) return ApiResponse::notFound('Inspección no encontrada o no completada');
        if ($inspection->work_request_id) return ApiResponse::error('Ya existe una solicitud generada para esta inspección', 422);
        if (!$inspection->has_findings)   return ApiResponse::error('La inspección no tiene hallazgos (No conformes)', 422);

        $isProductionLine = $inspection->template?->template_type === 'production_line';

        DB::beginTransaction();
        try {
            if ($isProductionLine) {
                // Una solicitud por cada activo de sección que tenga ítems NC
                $ncResponses = $inspection->responses->where('is_non_conformant', true);
                $grouped = $ncResponses->groupBy(fn($r) => $r->item->section->asset_id ?? 0);

                $workRequests = [];
                foreach ($grouped as $sectionAssetId => $groupResponses) {
                    $sectionAsset = $sectionAssetId
                        ? $groupResponses->first()->item->section->asset
                        : null;

                    $assetLabel = $sectionAsset
                        ? "{$sectionAsset->code} - {$sectionAsset->name}"
                        : 'Sin activo asignado';

                    $description = "Hallazgos en inspección de línea de producción ({$assetLabel}):\n\n";
                    foreach ($groupResponses as $r) {
                        $description .= "• [{$r->item->section->name}] {$r->item->name}: {$r->response_value}";
                        if ($r->observation) $description .= " — {$r->observation}";
                        $description .= "\n";
                    }

                    $wr = WorkRequest::create([
                        'company_id'   => $companyId,
                        'asset_id'     => $sectionAssetId ?: null,
                        'code'         => WorkRequest::generateCode($companyId),
                        'title'        => "Hallazgos inspección - {$assetLabel}",
                        'description'  => $description,
                        'request_type' => 'inspection',
                        'priority'     => 'high',
                        'status'       => 'pending',
                        'requester_id' => $inspection->operator_id ?? $userId,
                        'created_by'   => $userId,
                        'updated_by'   => $userId,
                    ]);

                    // Registrar en pivote con la sección que originó la SR
                    $sectionId = $sectionAssetId
                        ? $groupResponses->first()->item->section->id
                        : null;
                    $inspection->workRequests()->attach($wr->id, ['section_id' => $sectionId]);

                    $workRequests[] = $wr;
                }

                // Marcar la inspección como procesada usando la primera SR creada
                $inspection->update(['work_request_id' => $workRequests[0]->id]);
                DB::commit();

                return ApiResponse::success([
                    'work_requests'  => $workRequests,
                    'findings_count' => $ncResponses->count(),
                ], count($workRequests) . ' solicitud(es) de mantenimiento generada(s) exitosamente', 201);

            } else {
                // Maquinaria amarilla: una sola SR para el activo de la inspección
                $findings    = $this->buildFindingsSummary($inspection);
                $description = "Hallazgos detectados en inspección preoperacional del activo {$inspection->asset->code} - {$inspection->asset->name}:\n\n";
                foreach ($findings as $f) {
                    $description .= "• [{$f['section']}] {$f['item']}: {$f['response_value']}";
                    if ($f['observation']) $description .= " — {$f['observation']}";
                    $description .= "\n";
                }

                $workRequest = WorkRequest::create([
                    'company_id'   => $companyId,
                    'asset_id'     => $inspection->asset_id,
                    'code'         => WorkRequest::generateCode($companyId),
                    'title'        => "Hallazgos inspección preoperacional - {$inspection->asset->name}",
                    'description'  => $description,
                    'request_type' => 'inspection',
                    'priority'     => 'high',
                    'status'       => 'pending',
                    'requester_id' => $inspection->operator_id ?? $userId,
                    'created_by'   => $userId,
                    'updated_by'   => $userId,
                ]);

                $inspection->workRequests()->attach($workRequest->id, ['section_id' => null]);
                $inspection->update(['work_request_id' => $workRequest->id]);
                DB::commit();

                return ApiResponse::success([
                    'work_request'   => $workRequest,
                    'findings_count' => count($findings),
                ], 'Solicitud de mantenimiento generada exitosamente', 201);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al generar la solicitud: ' . $e->getMessage(), 500);
        }
    }

    // ── Helper privado ────────────────────────────────────────────────────────

    private function buildFindingsSummary(Inspection $inspection): array
    {
        return $inspection->responses
            ->where('is_non_conformant', true)
            ->map(fn($r) => [
                'section'        => $r->item->section->name ?? '',
                'item'           => $r->item->name ?? '',
                'response_value' => $r->response_value,
                'observation'    => $r->observation,
                'photos'         => $r->photos->map(fn($p) => $p->url)->values(),
            ])
            ->values()
            ->toArray();
    }
}
