<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asset;
use App\Models\AssetMeter;
use App\Models\AssetMeterReading;
use App\Models\Inspection;
use App\Models\InspectionItem;
use App\Models\InspectionResponse;
use App\Models\InspectionTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PublicInspectionController extends Controller
{
    /**
     * Activos de la empresa. Si se pasa template_id, filtra solo los activos
     * asignados a esa plantilla; de lo contrario, devuelve todos con alguna plantilla activa.
     * GET /public/inspection-assets?company_id=X[&template_id=Y]
     */
    public function assets(Request $request): JsonResponse
    {
        $companyId  = $request->query('company_id');
        $templateId = $request->query('template_id');

        if (!$companyId) {
            return ApiResponse::error('Se requiere company_id', 422);
        }

        $assets = Asset::where('company_id', $companyId)
            ->where('is_active', true)
            ->when($templateId, function ($q) use ($templateId) {
                $q->whereHas('inspectionTemplates', fn($tq) =>
                    $tq->where('inspection_templates.id', $templateId)->where('is_active', true)
                );
            }, function ($q) {
                $q->whereHas('inspectionTemplates', fn($tq) => $tq->where('is_active', true));
            })
            ->with(['category:id,name,icon', 'companySite:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn($a) => [
                'id'       => $a->id,
                'code'     => $a->code,
                'name'     => $a->name,
                'category' => $a->category?->name,
                'site'     => $a->companySite?->name,
            ]);

        return ApiResponse::success($assets);
    }

    /**
     * Todas las plantillas activas de la empresa, con secciones e ítems.
     * GET /public/inspection-templates?company_id=X
     */
    public function templates(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        if (!$companyId) {
            return ApiResponse::error('Se requiere company_id', 422);
        }

        $templates = InspectionTemplate::where('company_id', $companyId)
            ->where('is_active', true)
            ->with([
                'sections' => function ($q) {
                    $q->where('is_active', true)
                      ->orderBy('order_index')
                      ->with([
                          'items' => fn($qi) => $qi->where('is_active', true)->orderBy('order_index'),
                      ]);
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(fn($t) => [
                'id'                 => $t->id,
                'name'               => $t->name,
                'description'        => $t->description,
                'template_type'      => $t->template_type,
                'requires_horometer' => $t->requires_horometer,
                'sections'           => $t->sections->map(fn($s) => [
                    'id'              => $s->id,
                    'name'            => $s->name,
                    'response_options' => $s->response_options ?? ['CONFORME', 'NO CONFORME'],
                    'items'           => $s->items->map(fn($i) => [
                        'id'                   => $i->id,
                        'name'                 => $i->name,
                        'item_type'            => $i->item_type,
                        'is_required'          => $i->is_required,
                        'non_conformant_value' => $i->non_conformant_value,
                    ]),
                ]),
            ]);

        return ApiResponse::success($templates);
    }

    /**
     * Operadores (EMPLOYEE / OPERATOR) activos de la empresa.
     * GET /public/inspection-operators?company_id=X
     */
    public function operators(Request $request): JsonResponse
    {
        $companyId = $request->query('company_id');

        if (!$companyId) {
            return ApiResponse::error('Se requiere company_id', 422);
        }

        $operators = DB::table('users')
            ->join('user_companies', 'users.id', '=', 'user_companies.user_id')
            ->join('user_roles',     'users.id', '=', 'user_roles.user_id')
            ->join('roles',          'user_roles.role_id', '=', 'roles.id')
            ->where('user_companies.company_id', $companyId)
            ->where('user_companies.status', 'active')
            ->where('users.status', 'active')
            ->whereIn('roles.code', ['EMPLOYEE', 'OPERATOR'])
            ->select(
                'users.id',
                DB::raw("CONCAT(users.first_name, ' ', users.last_name) AS name"),
                'users.email'
            )
            ->distinct()
            ->orderBy('name')
            ->get();

        return ApiResponse::success($operators);
    }

    /**
     * Crea, responde y completa la inspección en una sola transacción.
     * POST /public/inspections
     */
    public function store(Request $request): JsonResponse
    {
        // Fetch template first to determine type (production_line vs yellow_machinery)
        $template = InspectionTemplate::where('id', $request->template_id)
            ->where('company_id', $request->company_id)
            ->where('is_active', true)
            ->with(['sections' => fn($q) => $q->where('is_active', true)->with([
                'items' => fn($qi) => $qi->where('is_active', true),
            ])])
            ->first();

        if (!$template) {
            return ApiResponse::notFound('Plantilla no encontrada');
        }

        $isProductionLine = $template->template_type === 'production_line';

        $validator = Validator::make($request->all(), [
            'company_id'       => 'required|integer|exists:companies,id',
            'template_id'      => 'required|integer|exists:inspection_templates,id',
            'asset_id'         => $isProductionLine ? 'nullable|integer|exists:assets,id' : 'required|integer|exists:assets,id',
            'operator_id'      => 'required|integer|exists:users,id',
            'shift_id'         => 'nullable|integer|exists:inspection_shifts,id',
            'inspection_date'  => 'required|date',
            'notes'            => 'nullable|string|max:1000',
            'horometer_value'  => 'nullable|numeric|min:0',
            'responses'        => 'required|array|min:1',
            'responses.*.item_id'        => 'required|integer|exists:inspection_items,id',
            'responses.*.response_value' => 'nullable|string|max:100',
            'responses.*.observation'    => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $asset = null;
        if ($request->filled('asset_id')) {
            $asset = Asset::where('id', $request->asset_id)
                ->where('company_id', $request->company_id)
                ->first();
            if (!$asset) {
                return ApiResponse::notFound('Activo no encontrado');
            }
        }

        // Verificar que todos los ítems activos de la plantilla están respondidos
        $allItemIds = collect();
        foreach ($template->sections as $section) {
            foreach ($section->items as $item) {
                $allItemIds->push($item->id);
            }
        }
        $respondedItemIds = collect($request->responses)->pluck('item_id');
        $unanswered = $allItemIds->diff($respondedItemIds);

        if ($unanswered->isNotEmpty()) {
            return ApiResponse::error(
                'Faltan ' . $unanswered->count() . ' ítem(s) sin responder.',
                422
            );
        }

        // Validar horómetro si la plantilla lo requiere
        if ($template->requires_horometer && !$request->filled('horometer_value')) {
            return ApiResponse::error('La lectura del horómetro es requerida para esta plantilla.', 422);
        }

        DB::beginTransaction();
        try {
            // Crear la inspección
            $inspection = Inspection::create([
                'company_id'      => $request->company_id,
                'template_id'     => $request->template_id,
                'asset_id'        => $asset?->id ?? null,
                'operator_id'     => $request->operator_id,
                'shift_id'        => $request->shift_id,
                'inspection_date' => $request->inspection_date,
                'status'          => 'draft',
                'has_findings'    => false,
                'notes'           => $request->notes,
                'created_by'      => $request->operator_id, // operador es el creador
            ]);

            // Guardar respuestas y detectar hallazgos
            $hasFindings = false;
            foreach ($request->responses as $r) {
                $item = InspectionItem::find($r['item_id']);
                $ncValues = $item?->non_conformant_value !== null
                    ? array_map('trim', explode('|', $item->non_conformant_value))
                    : ['NO CONFORME'];
                $isNonConformant = $item !== null
                    && ($r['response_value'] ?? null) !== null
                    && in_array(trim($r['response_value']), $ncValues);

                if ($isNonConformant) {
                    $hasFindings = true;
                }

                InspectionResponse::create([
                    'inspection_id'     => $inspection->id,
                    'item_id'           => $r['item_id'],
                    'response_value'    => $r['response_value'] ?? null,
                    'is_non_conformant' => $isNonConformant,
                    'observation'       => $r['observation'] ?? null,
                ]);
            }

            // Registrar horómetro si aplica
            if ($template->requires_horometer && $request->filled('horometer_value')) {
                $hoursMeter = AssetMeter::withTrashed()
                    ->where('asset_id', $asset->id)
                    ->where('meter_type', 'hours')
                    ->first();

                if (!$hoursMeter) {
                    $hoursMeter = AssetMeter::create([
                        'asset_id'        => $asset->id,
                        'meter_type'      => 'hours',
                        'current_reading' => 0,
                        'unit'            => 'h',
                        'is_active'       => true,
                        'notes'           => 'Creado automáticamente desde inspección preoperacional pública',
                    ]);
                } elseif ($hoursMeter->trashed()) {
                    $hoursMeter->restore();
                    $hoursMeter->update(['is_active' => true]);
                }

                if ((float) $request->horometer_value < $hoursMeter->current_reading) {
                    DB::rollBack();
                    return ApiResponse::error(
                        "El valor del horómetro ({$request->horometer_value}) no puede ser menor que la lectura actual ({$hoursMeter->current_reading}).",
                        422
                    );
                }

                $hoursMeter->recordReading(
                    (float) $request->horometer_value,
                    [
                        'reading_date'   => now(),
                        'reading_source' => AssetMeterReading::SOURCE_INSPECTION,
                        'inspection_id'  => $inspection->id,
                        'recorded_by'    => $request->operator_id,
                        'notes'          => "Registrado en inspección preoperacional pública #{$inspection->id}",
                    ]
                );
            }

            // Completar la inspección
            $inspection->update([
                'status'       => 'completed',
                'has_findings' => $hasFindings,
                'completed_at' => now(),
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al guardar la inspección: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success([
            'inspection_id' => $inspection->id,
            'has_findings'  => $hasFindings,
            'asset'         => $asset ? ['name' => $asset->name, 'code' => $asset->code] : null,
            'template'      => ['name' => $template->name],
        ], 'Inspección completada exitosamente', 201);
    }
}
