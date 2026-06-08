<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\InspectionTemplate;
use App\Models\InspectionSection;
use App\Models\InspectionItem;
use App\Models\ProductionLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InspectionTemplateController extends Controller
{
    // ── CRUD de plantillas ────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $query = InspectionTemplate::byCompany($companyId)
            ->with(['category:id,name,code', 'productionLine:id,name', 'assets:id,name,code'])
            ->withCount('sections', 'inspections')
            ->orderBy('name');

        if ($request->filled('template_type')) {
            $query->where('template_type', $request->template_type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 0);
        $templates = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return ApiResponse::success($templates, 'Plantillas recuperadas exitosamente');
    }

    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $validator = Validator::make($request->all(), [
            'name'                => 'required|string|max:150',
            'description'         => 'nullable|string',
            'template_type'       => 'nullable|in:yellow_machinery,production_line',
            'production_line_id'  => 'nullable|integer|exists:production_lines,id',
            'category_id'         => 'nullable|integer|exists:asset_categories,id',
            'is_active'           => 'nullable|boolean',
            'requires_horometer'  => 'nullable|boolean',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        $templateType = $request->get('template_type', 'yellow_machinery');

        // Business rules
        $productionLineId = null;
        $categoryId       = $request->category_id;
        $requiresHorometer = $request->boolean('requires_horometer', false);

        if ($templateType === 'production_line') {
            if (empty($request->production_line_id)) {
                return ApiResponse::validation(['production_line_id' => ['La línea de producción es obligatoria para este tipo de plantilla.']]);
            }
            $productionLineId  = $request->production_line_id;
            $categoryId        = null;
            $requiresHorometer = false;
        }

        $template = InspectionTemplate::create([
            'company_id'          => $companyId,
            'category_id'         => $categoryId,
            'name'                => $request->name,
            'description'         => $request->description,
            'template_type'       => $templateType,
            'production_line_id'  => $productionLineId,
            'is_active'           => $request->get('is_active', true),
            'requires_horometer'  => $requiresHorometer,
        ]);

        return ApiResponse::success(
            $template->load(['category', 'productionLine:id,name']),
            'Plantilla creada exitosamente',
            201
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $template = InspectionTemplate::byCompany($companyId)
            ->with([
                'category',
                'productionLine:id,name',
                'sections' => fn($q) => $q->where('is_active', true)->orderBy('order_index'),
                'sections.items' => fn($q) => $q->where('is_active', true)->orderBy('order_index'),
                'sections.asset:id,name,code',
                'assets:id,name,code',
            ])
            ->find($id);
        if (!$template) return ApiResponse::notFound('Plantilla no encontrada');
        return ApiResponse::success($template);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $template = InspectionTemplate::byCompany($companyId)->find($id);
        if (!$template) return ApiResponse::notFound('Plantilla no encontrada');

        $validator = Validator::make($request->all(), [
            'name'               => 'sometimes|string|max:150',
            'description'        => 'nullable|string',
            'template_type'      => 'nullable|in:yellow_machinery,production_line',
            'production_line_id' => 'nullable|integer|exists:production_lines,id',
            'category_id'        => 'nullable|integer|exists:asset_categories,id',
            'is_active'          => 'nullable|boolean',
            'requires_horometer' => 'nullable|boolean',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        $data = $request->only(['name', 'description', 'category_id', 'is_active', 'requires_horometer', 'template_type', 'production_line_id']);

        // Apply business rules based on final template_type
        $templateType = $data['template_type'] ?? $template->template_type;
        if ($templateType === 'production_line') {
            $data['category_id']        = null;
            $data['requires_horometer'] = false;
        } else {
            $data['production_line_id'] = null;
        }

        $template->update($data);
        return ApiResponse::success(
            $template->fresh()->load(['category', 'productionLine:id,name']),
            'Plantilla actualizada exitosamente'
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $user      = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INSPECTIONS_DELETE_ADMIN', $companyId)) {
            return ApiResponse::error('No tienes permiso para eliminar plantillas de inspección', 403);
        }

        $template = InspectionTemplate::byCompany($companyId)->find($id);
        if (!$template) return ApiResponse::notFound('Plantilla no encontrada');

        $template->delete();
        return ApiResponse::success(null, 'Plantilla eliminada exitosamente');
    }

    // ── Gestión de secciones ─────────────────────────────────────────────────

    public function storeSections(Request $request, int $templateId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $template = InspectionTemplate::byCompany($companyId)->find($templateId);
        if (!$template) return ApiResponse::notFound('Plantilla no encontrada');

        $validator = Validator::make($request->all(), [
            'sections'                         => 'required|array|min:1',
            'sections.*.name'                  => 'required|string|max:150',
            'sections.*.order_index'           => 'required|integer|min:0',
            'sections.*.asset_id'              => 'nullable|integer|exists:assets,id',
            'sections.*.response_options'      => 'required|array|min:2',
            'sections.*.response_options.*'    => 'required|string|max:100',
            'sections.*.has_observation'       => 'nullable|boolean',
            'sections.*.items'                 => 'required|array|min:1',
            'sections.*.items.*.name'          => 'required|string|max:200',
            'sections.*.items.*.order_index'   => 'required|integer|min:0',
            'sections.*.items.*.is_required'   => 'nullable|boolean',
            'sections.*.items.*.non_conformant_value' => 'nullable|string|max:255',
            'sections.*.items.*.item_type'            => 'nullable|in:operative,lubrication',
            'sections.*.items.*.response_options'     => 'nullable|array',
            'sections.*.items.*.response_options.*'   => 'string|max:100',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        DB::beginTransaction();
        try {
            // IDs recibidos en el request (secciones e ítems que deben quedar activos)
            $incomingSectionIds = collect($request->sections)->pluck('id')->filter()->values();

            // Desactivar secciones que ya no están en el request
            $template->sections()
                ->whereNotIn('id', $incomingSectionIds)
                ->update(['is_active' => false]);

            foreach ($request->sections as $sectionData) {
                $sectionId = $sectionData['id'] ?? null;

                // Upsert de sección
                if ($sectionId) {
                    $section = $template->sections()->find($sectionId);
                }

                $sectionPayload = [
                    'name'             => $sectionData['name'],
                    'asset_id'         => $sectionData['asset_id'] ?? null,
                    'order_index'      => $sectionData['order_index'],
                    'response_options' => $sectionData['response_options'],
                    'has_observation'  => $sectionData['has_observation'] ?? true,
                    'is_active'        => true,
                ];

                if (!empty($section)) {
                    $section->update($sectionPayload);
                } else {
                    $section = $template->sections()->create($sectionPayload);
                }

                // IDs de ítems recibidos para esta sección
                $incomingItemIds = collect($sectionData['items'])->pluck('id')->filter()->values();

                // Desactivar ítems que ya no están en el request
                $section->items()
                    ->whereNotIn('id', $incomingItemIds)
                    ->update(['is_active' => false]);

                foreach ($sectionData['items'] as $itemData) {
                    $itemId = $itemData['id'] ?? null;
                    $itemPayload = [
                        'name'                 => $itemData['name'],
                        'item_type'            => $itemData['item_type'] ?? null,
                        'response_options'     => !empty($itemData['response_options']) ? $itemData['response_options'] : null,
                        'order_index'          => $itemData['order_index'],
                        'is_required'          => $itemData['is_required'] ?? true,
                        'is_active'            => true,
                        'non_conformant_value' => $itemData['non_conformant_value'] ?? null,
                    ];

                    if ($itemId) {
                        $item = $section->items()->find($itemId);
                    }

                    if (!empty($item)) {
                        $item->update($itemPayload);
                    } else {
                        $section->items()->create($itemPayload);
                    }

                    unset($item); // reset para el siguiente loop
                }

                unset($section); // reset para el siguiente loop
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al guardar secciones: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success(
            $template->fresh()->load([
                'sections' => fn($q) => $q->where('is_active', true)->orderBy('order_index'),
                'sections.items' => fn($q) => $q->where('is_active', true)->orderBy('order_index'),
                'sections.asset:id,name,code',
            ]),
            'Secciones guardadas exitosamente'
        );
    }

    // ── Gestión de activos asignados ─────────────────────────────────────────

    public function syncAssets(Request $request, int $templateId): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $template = InspectionTemplate::byCompany($companyId)->find($templateId);
        if (!$template) return ApiResponse::notFound('Plantilla no encontrada');

        $validator = Validator::make($request->all(), [
            'asset_ids'   => 'required|array',
            'asset_ids.*' => 'integer|exists:assets,id',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        // Verify assets belong to the company
        $validCount = Asset::byCompany($companyId)->whereIn('id', $request->asset_ids)->count();
        if ($validCount !== count($request->asset_ids)) {
            return ApiResponse::error('Uno o más activos no pertenecen a esta empresa', 422);
        }

        $template->assets()->sync($request->asset_ids);
        return ApiResponse::success(
            $template->assets()->select('assets.id', 'assets.name', 'assets.code')->get(),
            'Activos asignados exitosamente'
        );
    }

    // ── Plantilla por activo (para el formulario de inspección) ─────────────

    public function getByAsset(Request $request, int $assetId): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $asset = Asset::byCompany($companyId)->find($assetId);
        if (!$asset) return ApiResponse::notFound('Activo no encontrado');

        $template = InspectionTemplate::byCompany($companyId)
            ->active()
            ->whereHas('assets', fn($q) => $q->where('assets.id', $assetId))
            ->with(['sections' => fn($q) => $q->orderBy('order_index'),
                    'sections.items' => fn($q) => $q->where('is_active', true)->orderBy('order_index')])
            ->first();

        if (!$template) {
            return ApiResponse::success(null, 'No hay plantilla asignada a este activo');
        }
        return ApiResponse::success($template);
    }

    // ── Activos disponibles por categoría para asignar ───────────────────────

    public function assetsByCategory(Request $request): JsonResponse
    {
        $companyId  = $request->header('x-company-id');
        $categoryId = $request->query('category_id');

        $query = Asset::byCompany($companyId)->select('id', 'name', 'code', 'category_id');
        if ($categoryId) $query->where('category_id', $categoryId);

        return ApiResponse::success($query->orderBy('name')->get());
    }

    // ── Carga masiva de plantillas desde CSV o Excel ──────────────────────────
    public function bulkImport(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $validator = Validator::make($request->all(), [
            'file'               => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
            'template_type'      => 'nullable|in:yellow_machinery,production_line',
            'production_line_id' => 'nullable|integer|exists:production_lines,id',
            'requires_horometer' => 'nullable|boolean',
            'overwrite'          => 'nullable|boolean',
        ]);
        if ($validator->fails()) return ApiResponse::validation($validator->errors()->toArray());

        $templateType      = $request->get('template_type', 'yellow_machinery');
        $productionLineId  = $templateType === 'production_line' ? $request->production_line_id : null;
        $requiresHorometer = $templateType === 'yellow_machinery' && $request->boolean('requires_horometer', false);
        $overwrite         = $request->boolean('overwrite', false);

        if ($templateType === 'production_line' && empty($productionLineId)) {
            return ApiResponse::validation(['production_line_id' => ['Requerida para plantillas de línea de producción.']]);
        }

        try {
            $rawRows = $this->parseImportFile($request->file('file'));
        } catch (\Exception $e) {
            return ApiResponse::error('Error al leer el archivo: ' . $e->getMessage(), 422);
        }

        if (empty($rawRows)) {
            return ApiResponse::error('El archivo no contiene datos o está vacío', 422);
        }

        $sampleRow = reset($rawRows);
        if (!array_key_exists('plantilla', $sampleRow) || !array_key_exists('item', $sampleRow)) {
            return ApiResponse::error('El archivo debe contener las columnas: plantilla, seccion, item', 422);
        }

        // Group rows → template → section → items
        $groups    = [];
        $rowErrors = [];

        foreach ($rawRows as $lineNum => $row) {
            $plantilla = trim($row['plantilla'] ?? '');
            $seccion   = trim($row['seccion'] ?? '') ?: 'General';
            $item      = trim($row['item'] ?? '');

            if (empty($plantilla)) { $rowErrors[] = ['fila' => $lineNum + 2, 'razon' => "Campo 'plantilla' vacío"]; continue; }
            if (empty($item))      { $rowErrors[] = ['fila' => $lineNum + 2, 'plantilla' => $plantilla, 'razon' => "Campo 'item' vacío"]; continue; }

            // dispara_nc takes precedence; criterio_aceptacion is a legacy/alias fallback
            $ncRaw       = trim($row['dispara_nc'] ?? $row['criterio_nc'] ?? '');
            $rawType     = strtolower($row['tipo_item'] ?? '');
            $rawOptions  = trim($row['opciones'] ?? '');
            $activoCodigo = strtoupper(trim($row['activo_codigo'] ?? ''));

            $itemType = null;
            if (in_array($rawType, ['operativo', 'operative']))                          $itemType = 'operative';
            elseif (in_array($rawType, ['lubricación', 'lubricacion', 'lubrication']))   $itemType = 'lubrication';

            $options = [];
            if (!empty($rawOptions)) {
                $options = str_contains($rawOptions, '|') ? explode('|', $rawOptions) : explode(',', $rawOptions);
                $options = array_values(array_filter(array_map('trim', $options)));
            }

            // Guardar activo_codigo a nivel de sección (primer valor no vacío encontrado)
            if ($activoCodigo && empty($groups[$plantilla][$seccion]['activo_codigo'])) {
                $groups[$plantilla][$seccion]['activo_codigo'] = $activoCodigo;
            }

            $groups[$plantilla][$seccion]['items'][] = [
                'name'                 => $item,
                'item_type'            => $itemType,
                'non_conformant_value' => $ncRaw ?: null,
                'response_options'     => $options,
            ];
        }

        $created        = [];
        $omitted        = [];
        $assetWarnings  = [];
        $totalItems     = 0;

        // Pre-cargar activos válidos de la línea: ['CAR-001' => ['id' => 5, 'name' => 'Cargador CAT 950'], ...]
        $validLineAssetCodes = [];
        if ($templateType === 'production_line' && $productionLineId) {
            $validLineAssetCodes = Asset::where('company_id', $companyId)
                ->where('production_line_id', $productionLineId)
                ->where('is_active', true)
                ->get(['id', 'code', 'name'])
                ->keyBy('code')
                ->map(fn($a) => ['id' => $a->id, 'name' => $a->name])
                ->toArray();
        }

        DB::beginTransaction();
        try {
            foreach ($groups as $plantillaName => $sections) {
                $existing = InspectionTemplate::byCompany($companyId)->where('name', $plantillaName)->first();

                if ($existing && !$overwrite) {
                    $omitted[] = ['plantilla' => $plantillaName, 'razon' => 'Ya existe (overwrite=false)'];
                    continue;
                }

                if ($existing && $overwrite) {
                    $existing->sections()->each(function ($sec) {
                        $sec->items()->each(fn($it) => $it->responses()->delete());
                        $sec->items()->delete();
                    });
                    $existing->sections()->delete();
                    $existing->update([
                        'template_type'      => $templateType,
                        'production_line_id' => $productionLineId,
                        'requires_horometer' => $requiresHorometer,
                    ]);
                    $template = $existing;
                } else {
                    $template = InspectionTemplate::create([
                        'company_id'         => $companyId,
                        'name'               => $plantillaName,
                        'template_type'      => $templateType,
                        'production_line_id' => $productionLineId,
                        'is_active'          => true,
                        'requires_horometer' => $requiresHorometer,
                    ]);
                }

                $sectionIndex = 0;
                $itemsCreated = 0;

                foreach ($sections as $sectionName => $sectionData) {
                    $items           = $sectionData['items'] ?? [];
                    $activoCodigo    = $sectionData['activo_codigo'] ?? null;

                    $responseOptions = collect($items)
                        ->pluck('response_options')
                        ->first(fn($o) => !empty($o))
                        ?: ['Cumple', 'No cumple', 'N/A'];

                    // Resolver activo por código validando que pertenezca a la línea
                    $assetId          = null;
                    $finalSectionName = $sectionName;
                    if ($templateType === 'production_line' && $activoCodigo) {
                        if (array_key_exists($activoCodigo, $validLineAssetCodes)) {
                            $assetId          = $validLineAssetCodes[$activoCodigo]['id'];
                            $finalSectionName = $validLineAssetCodes[$activoCodigo]['name'];
                        } else {
                            $assetWarnings[] = [
                                'plantilla' => $plantillaName,
                                'seccion'   => $sectionName,
                                'codigo'    => $activoCodigo,
                                'razon'     => "El código '{$activoCodigo}' no pertenece a la línea de producción seleccionada o no está activo. La sección se creó sin activo asignado.",
                            ];
                        }
                    }

                    $section = $template->sections()->create([
                        'name'             => $finalSectionName,
                        'asset_id'         => $assetId,
                        'order_index'      => $sectionIndex++,
                        'response_options' => $responseOptions,
                        'has_observation'  => true,
                    ]);

                    foreach ($items as $itemIndex => $itemData) {
                        $section->items()->create([
                            'name'                 => $itemData['name'],
                            'item_type'            => $itemData['item_type'],
                            'order_index'          => $itemIndex,
                            'is_required'          => true,
                            'is_active'            => true,
                            'non_conformant_value' => $itemData['non_conformant_value'],
                        ]);
                        $itemsCreated++;
                    }
                }

                $created[] = ['nombre' => $plantillaName, 'secciones_count' => count($sections), 'items_count' => $itemsCreated];
                $totalItems += $itemsCreated;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al importar plantillas: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success([
            'plantillas_creadas'  => count($created),
            'items_creados'       => $totalItems,
            'omitidos'            => count($omitted) + count($rowErrors),
            'advertencias_activo' => count($assetWarnings),
            'detalle_creados'     => $created,
            'detalle_omitidos'    => array_merge($omitted, $rowErrors),
            'detalle_advertencias'=> $assetWarnings,
        ], 'Importación completada');
    }

    // ── Descarga plantilla Excel ───────────────────────────────────────────────
    public function downloadTemplate(Request $request)
    {
        $type   = $request->get('type', 'yellow_machinery');
        $isLine = $type === 'production_line';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet()->setTitle('Plantillas');

        $headerFill = [
            'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill'      => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $isLine ? '7C3AED' : 'D97706']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                            'vertical'   => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER],
            'borders'   => ['allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                                             'color' => ['rgb' => 'FFFFFF']]],
        ];
        $altFill = ['fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                               'startColor' => ['rgb' => $isLine ? 'F5F3FF' : 'FFFBEB']]];

        $headers = $isLine
            ? ['plantilla', 'seccion', 'activo_codigo', 'opciones', 'item', 'tipo_item', 'dispara_nc']
            : ['plantilla', 'seccion', 'opciones', 'item', 'tipo_item', 'dispara_nc'];
        $widths  = $isLine
            ? [32, 26, 20, 32, 48, 16, 28]
            : [32, 26, 32, 48, 16, 28];

        foreach ($headers as $col => $h) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
            $sheet->setCellValue("{$letter}1", $h);
            $sheet->getColumnDimension($letter)->setWidth($widths[$col]);
        }
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray($headerFill);
        $sheet->getRowDimension(1)->setRowHeight(20);
        $sheet->freezePane('A2');

        // Columna activo_codigo resaltada en violet claro (solo línea de producción)
        if ($isLine) {
            $sheet->getStyle('C1')->applyFromArray([
                'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                           'startColor' => ['rgb' => '5B21B6']],
            ]);
        }

        $examples = $isLine ? [
            // plantilla, seccion, activo_codigo, opciones, item, tipo_item, dispara_nc
            ['Inspección L1 Mañana', 'Cargador Frontal CAT 950',    'CAR-001', 'Cumple|No cumple|N/A',   'Verificar presión de llantas',          'Operativo',   'No cumple'],
            ['Inspección L1 Mañana', 'Cargador Frontal CAT 950',    'CAR-001', 'Cumple|No cumple|N/A',   'Revisar nivel de aceite hidráulico',    'Operativo',   'No cumple'],
            ['Inspección L1 Mañana', 'Montacargas Toyota 8FG25',    'MTC-002', 'Ejecutado|No ejecutado', 'Lubricar mástil',                       'Lubricación', 'No ejecutado'],
            ['Inspección L1 Mañana', 'Montacargas Toyota 8FG25',    'MTC-002', 'Ejecutado|No ejecutado', 'Engrasar rodamientos de ruedas',        'Lubricación', 'No ejecutado'],
            ['Inspección L2 Tarde',  'Retroexcavadora Komatsu PC200','PC2-003', 'Cumple|No cumple|N/A',  'Verificar temperatura motor (< 80°C)',  'Operativo',   'No cumple'],
            ['Inspección L2 Tarde',  'Retroexcavadora Komatsu PC200','PC2-003', 'Cumple|No cumple|N/A',  'Comprobar nivel de aceite motor',       'Operativo',   'No cumple'],
        ] : [
            // plantilla, seccion, opciones, item, tipo_item, dispara_nc
            ['Revisión Excavadora', 'Motor',      'Bien|Regular|Deficiente',    'Nivel aceite motor',               '', 'Deficiente'],
            ['Revisión Excavadora', 'Motor',      'Bien|Regular|Deficiente',    'Temperatura en operación',         '', 'Deficiente'],
            ['Revisión Excavadora', 'Hidráulico', 'OK|Falla menor|Falla mayor', 'Presión línea principal',          '', 'Falla mayor|Falla menor'],
            ['Revisión Excavadora', 'Hidráulico', 'OK|Falla menor|Falla mayor', 'Nivel aceite hidráulico',          '', 'Falla mayor'],
            ['Check Volqueta 777',  'Neumáticos', 'Bien|Desgaste|Pinchazo',     'Neumático delantero izquierdo',    '', 'Desgaste|Pinchazo'],
            ['Check Volqueta 777',  'Neumáticos', 'Bien|Desgaste|Pinchazo',     'Neumático delantero derecho',      '', 'Desgaste|Pinchazo'],
        ];

        foreach ($examples as $i => $row) {
            $rowNum = $i + 2;
            $sheet->fromArray($row, null, "A{$rowNum}");
            if ($i % 2 === 1) {
                $sheet->getStyle("A{$rowNum}:{$lastCol}{$rowNum}")->applyFromArray($altFill);
            }
        }

        // Instructions sheet
        $instr = $spreadsheet->createSheet()->setTitle('Instrucciones');
        $instrRows = [
            ['Campo', 'Descripción', 'Obligatorio'],
            ['plantilla',      'Nombre de la plantilla. Todas las filas con el mismo nombre se agrupan en una sola plantilla.',                     'Sí'],
            ['seccion',        'Nombre de la sección dentro de la plantilla.',                                                                       'Sí'],
            ['activo_codigo',  'Solo Línea de Producción: código exacto del activo que corresponde a esta sección (ej: CAR-001). Opcional.',        $isLine ? 'Recomendado' : 'N/A'],
            ['opciones',       'Opciones de respuesta de la sección, separadas por |. Ej: Cumple|No cumple|N/A',                                   'Sí'],
            ['item',           'Descripción del ítem o punto a inspeccionar.',                                                                       'Sí'],
            ['tipo_item',      'Solo para Línea de Producción: Operativo o Lubricación.',                                                            'No'],
            ['dispara_nc',     'Valor(es) que generan No Conformidad, separados por |. Deben coincidir exactamente con las opciones definidas.',     'No'],
        ];
        $instr->fromArray($instrRows, null, 'A1');
        $instr->getStyle('A1:C1')->applyFromArray($headerFill);
        $instr->getColumnDimension('A')->setWidth(20);
        $instr->getColumnDimension('B')->setWidth(80);
        $instr->getColumnDimension('C')->setWidth(16);

        $spreadsheet->setActiveSheetIndex(0);

        $filename = $isLine ? 'plantilla_linea_produccion.xlsx' : 'plantilla_maquinaria_amarilla.xlsx';
        ob_start();
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save('php://output');
        $content = ob_get_clean();
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response($content, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
        ]);
    }

    // ── Parser privado (CSV + Excel) ───────────────────────────────────────────
    private function parseImportFile($file): array
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (in_array($ext, ['xlsx', 'xls'])) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getRealPath());
            $data        = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if (empty($data) || empty($data[0])) return [];

            $headers = array_map(fn($h) => strtolower(trim((string) $h)), $data[0]);

            $rows = [];
            for ($i = 1; $i < count($data); $i++) {
                $row = [];
                foreach ($headers as $j => $h) {
                    $row[$h] = trim((string) ($data[$i][$j] ?? ''));
                }
                if (array_filter($row)) $rows[] = $row;
            }
            return $rows;
        }

        // CSV / TXT
        $content = file_get_contents($file->getRealPath());
        $first   = strtok($content, "\n");
        $sep     = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $lines   = array_filter(array_map('trim', explode("\n", $content)));
        if (empty($lines)) return [];

        $headers = array_map(
            fn($h) => strtolower(trim(str_replace("\xEF\xBB\xBF", '', $h))),
            explode($sep, array_shift($lines))
        );

        $rows = [];
        foreach ($lines as $line) {
            if (!trim($line)) continue;
            $cols = array_map('trim', explode($sep, $line));
            $row  = [];
            foreach ($headers as $j => $h) {
                $row[$h] = $cols[$j] ?? '';
            }
            if (array_filter($row)) $rows[] = $row;
        }
        return $rows;
    }
}
