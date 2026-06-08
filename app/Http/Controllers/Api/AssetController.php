<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asset;
use App\Models\AssetSpecification;
use App\Models\AssetNote;
use App\Models\AssetNotification;
use App\Models\AssetSparePart;
use App\Models\AssetAttachment;
use App\Models\AssetMeasurement;
use App\Models\WorkOrder;
use App\Models\User;
use App\Models\Company;
use App\Models\CompanySite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Exports\AssetExport;
use App\Services\QRCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetController extends Controller
{
    protected $qrCodeService;

    public function __construct(QRCodeService $qrCodeService)
    {
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Listar activos de una empresa con filtros y paginación
     * 
     * @param Request $request
     * @param int $companyId
     * @return JsonResponse
     */
    public function index(Request $request, int $companyId): JsonResponse
    {
        // Validar parámetros de consulta
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:2000',
            'search' => 'string|max:255',
            'is_active' => 'boolean',
            'company_site_id' => 'integer|exists:company_sites,id',
            'category_id' => 'integer|exists:asset_categories,id',
            'status_id' => 'integer|exists:asset_statuses,id',
            'priority_id' => 'integer|exists:asset_priorities,id',
            'production_line_id' => 'nullable|integer|exists:production_lines,id',
            'parent_id' => 'nullable|integer|exists:assets,id',
            'view_mode' => 'string|in:tree,flat',
            'sort_by' => 'string|in:name,code,created_at,location_path',
            'sort_order' => 'string|in:asc,desc'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Verificar que la empresa exista
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Construir query con relaciones
        $query = Asset::byCompany($companyId)
            ->with(['category', 'status', 'priority', 'companySite', 'productionLine', 'system', 'parent', 'specifications']);

        // Aplicar filtros opcionales
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('location_path', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('company_site_id')) {
            $query->where('company_site_id', $request->company_site_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('priority_id')) {
            $query->where('priority_id', $request->priority_id);
        }

        if ($request->filled('production_line_id')) {
            $query->where('production_line_id', $request->production_line_id);
        }

        // Vista jerárquica (árbol) o plana
        $viewMode = $request->get('view_mode', 'flat');
        if ($viewMode === 'tree') {
            // Obtener solo activos raíz para vista de árbol
            if (!$request->filled('parent_id')) {
                $query->rootAssets();
            }
            // Cargar children recursivamente con sus relaciones
            $query->with(['children' => function ($q) {
                $q->with('category', 'status', 'priority', 'companySite', 'productionLine', 'system')
                  ->with(['children' => function ($q2) {
                      $q2->with('category', 'status', 'priority', 'companySite', 'productionLine', 'system')
                         ->with(['children' => function ($q3) {
                             $q3->with('category', 'status', 'priority', 'companySite', 'productionLine', 'system')
                                ->with(['children' => function ($q4) {
                                    $q4->with('category', 'status', 'priority', 'companySite', 'productionLine', 'system')
                                       ->with('children.category', 'children.status', 'children.priority', 'children.companySite', 'children.productionLine', 'children.system');
                                }]);
                         }]);
                  }]);
            }]);
        } else {
            // Vista plana: filtrar por parent_id si se especifica
            if ($request->has('parent_id')) {
                if (is_null($request->parent_id)) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $request->parent_id);
                }
            }
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'location_path');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $assets = $query->paginate($perPage);

        // Transformar datos
        $transformedAssets = $assets->getCollection()->map(function ($asset) {
            return $this->transformAsset($asset);
        });

        return ApiResponse::success([
            'data' => $transformedAssets,
            'pagination' => [
                'total' => $assets->total(),
                'per_page' => $assets->perPage(),
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'from' => $assets->firstItem(),
                'to' => $assets->lastItem()
            ]
        ], 'Activos recuperados exitosamente');
    }

    /**
     * Búsqueda de activos (autenticado) - Para formularios de creación de solicitudes/órdenes
     * 
     * @param Request $request
     * @param int $companyId
     * @return JsonResponse
     */
    public function search(Request $request, int $companyId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|min:2|max:255',
            'company_site_id' => 'nullable|integer|exists:company_sites,id',
            'category_id' => 'nullable|integer|exists:asset_categories,id',
            'per_page' => 'integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Errores de validación', 422, $validator->errors());
        }

        $query = Asset::where('company_id', $companyId)
            ->where('is_active', true)
            ->with([
                'category:id,code,name,icon,color',
                'companySite:id,name',
                'status:id,code,name',
            ]);

        // Búsqueda por código o nombre
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%")
              ->orWhere('location_path', 'like', "%{$search}%");
        });

        // Filtros opcionales
        if ($request->filled('company_site_id')) {
            $query->where('company_site_id', $request->company_site_id);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Ordenamiento
        $query->orderBy('code', 'asc');

        // Paginación
        $perPage = $request->get('per_page', 20);
        $assets = $query->paginate($perPage);

        return ApiResponse::success([
            'data' => $assets->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'code' => $asset->code,
                    'name' => $asset->name,
                    'location_path' => $asset->location_path,
                    'category' => $asset->category ? [
                        'id' => $asset->category->id,
                        'code' => $asset->category->code,
                        'name' => $asset->category->name,
                        'icon' => $asset->category->icon,
                        'color' => $asset->category->color,
                    ] : null,
                    'site' => $asset->companySite ? [
                        'id' => $asset->companySite->id,
                        'name' => $asset->companySite->name,
                    ] : null,
                    'status' => $asset->status ? [
                        'id' => $asset->status->id,
                        'code' => $asset->status->code,
                        'name' => $asset->status->name,
                    ] : null,
                ];
            }),
            'pagination' => [
                'total' => $assets->total(),
                'per_page' => $assets->perPage(),
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'from' => $assets->firstItem(),
                'to' => $assets->lastItem(),
            ],
        ], 'Búsqueda de activos completada exitosamente');
    }

    /**
     * Obtener detalle de un activo específico
     * 
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function show(int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)
            ->with([
                'category', 'status', 'priority', 'companySite', 'productionLine', 'system', 'currency',
                'parent', 'children', 'specifications', 'users', 'maintenanceTypes',
                'manufacturer', 'supplier',
                'createdBy', 'updatedBy'
            ])
            ->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        return ApiResponse::success($this->transformAssetDetail($asset), 'Activo recuperado exitosamente');
    }

    /**
     * Crear un nuevo activo
     * 
     * @param Request $request
     * @param int $companyId
     * @return JsonResponse
     */
    public function store(Request $request, int $companyId): JsonResponse
    {
        // Validación
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'company_site_id' => 'nullable|integer|exists:company_sites,id',
            'production_line_id' => 'nullable|integer|exists:production_lines,id',
            'system_id'         => 'nullable|integer|exists:asset_systems,id',
            'parent_id' => 'nullable|integer|exists:assets,id',
            'category_id' => 'required|integer|exists:asset_categories,id',
            'status_id' => 'required|integer|exists:asset_statuses,id',
            'priority_id' => 'nullable|integer|exists:asset_priorities,id',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'manufacturer_id' => 'nullable|integer|exists:asset_vendors,id',
            'supplier_id' => 'nullable|integer|exists:asset_vendors,id',
            'capacity' => 'nullable|numeric',
            'capacity_unit' => 'nullable|string|max:50',
            'manufacturing_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'materials_used' => 'nullable|array',
            'location_details' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'purchase_cost' => 'nullable|numeric',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'purchase_date' => 'nullable|date',
            'installation_date' => 'nullable|date',
            'end_of_life_date' => 'nullable|date|after_or_equal:installation_date',
            'cost_center' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:5120', // Max 5MB
            'is_active' => 'nullable|boolean',
            'specifications' => 'nullable|array',
            'specifications.*.spec_key' => 'required|string|max:100',
            'specifications.*.spec_value' => 'required|string|max:255',
            'specifications.*.spec_unit' => 'nullable|string|max:50',
            'specifications.*.spec_type' => 'nullable|string|in:text,number,date,boolean',
            'specifications.*.display_order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Verificar que el código sea único para la empresa
        $existingCode = Asset::byCompany($companyId)
            ->where('code', $request->code)
            ->exists();

        if ($existingCode) {
            return ApiResponse::error('El código del activo ya existe para esta empresa', 422);
        }

        // Validar que parent_id no cree un ciclo
        // HU-A3: si tiene padre, heredar company_site_id, production_line_id, category_id del padre
        $inheritedSiteId       = $request->company_site_id;
        $inheritedProductionId = $request->production_line_id;
        $inheritedCategoryId   = $request->category_id;

        if ($request->filled('parent_id')) {
            $parent = Asset::byCompany($companyId)->find($request->parent_id);
            if (!$parent) {
                return ApiResponse::error('El activo padre no existe en esta empresa', 422);
            }
            // Los hijos heredan obligatoriamente del padre (HU-A3)
            $inheritedSiteId       = $parent->company_site_id;
            $inheritedProductionId = $parent->production_line_id;
            $inheritedCategoryId   = $parent->category_id;
        }

        // Validar coordenadas (ambas o ninguna)
        if (($request->filled('latitude') && !$request->filled('longitude')) ||
            (!$request->filled('latitude') && $request->filled('longitude'))) {
            return ApiResponse::error('Debe proporcionar latitud y longitud juntas', 422);
        }

        DB::beginTransaction();
        try {
            // Procesar imagen si se subió
            $imagePath = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('assets', $filename, 'public');
                // URL absoluta para que funcione con frontend en otro puerto
                $imagePath = config('app.url') . Storage::url($path);
            }

            // Crear activo
            $asset = Asset::create([
                'company_id'         => $companyId,
                'code'               => $request->code,
                'name'               => $request->name,
                'description'        => $request->description,
                'company_site_id'    => $inheritedSiteId,
                'production_line_id' => $inheritedProductionId,
                'system_id'          => $request->system_id,
                'parent_id'          => $request->parent_id,
                'category_id'        => $inheritedCategoryId,
                'status_id'          => $request->status_id,
                'priority_id'        => $request->priority_id,
                'brand'              => $request->brand,
                'model'              => $request->model,
                'serial_number'      => $request->serial_number,
                'manufacturer_id'    => $request->manufacturer_id,
                'supplier_id'        => $request->supplier_id,
                'capacity'           => $request->capacity,
                'capacity_unit'      => $request->capacity_unit,
                'manufacturing_year' => $request->manufacturing_year,
                'materials_used'     => $request->materials_used,
                'location_details'   => $request->location_details,
                'latitude'           => $request->latitude,
                'longitude'          => $request->longitude,
                'purchase_cost'      => $request->purchase_cost,
                'currency_id'        => $request->currency_id,
                'purchase_date'      => $request->purchase_date,
                'installation_date'  => $request->installation_date,
                'end_of_life_date'   => $request->end_of_life_date,
                'cost_center'        => $request->cost_center,
                'image_path'         => $imagePath,
                'is_active'          => $request->get('is_active', true),
                'created_by'         => Auth::id(),
                'updated_by'         => Auth::id()
            ]);

            // Crear especificaciones si se proporcionaron
            if ($request->filled('specifications')) {
                foreach ($request->specifications as $spec) {
                    $asset->specifications()->create([
                        'spec_key' => $spec['spec_key'],
                        'spec_value' => $spec['spec_value'],
                        'spec_unit' => $spec['spec_unit'] ?? null,
                        'spec_type' => $spec['spec_type'] ?? 'text',
                        'display_order' => $spec['display_order'] ?? 0
                    ]);
                }
            }

            // Generar código QR automáticamente
            $qrData = [
                'asset_id' => $asset->id,
                'code' => $asset->code,
                'name' => $asset->name,
                'company_id' => $companyId,
                'url' => config('app.frontend_url', config('app.url')) . '/assets/' . $asset->id
            ];
            $qrUrl = $this->qrCodeService->generateAndUpload($qrData, "asset_{$asset->id}_{$asset->code}");
            if ($qrUrl) {
                $asset->qr_code = $qrUrl;
                $asset->saveQuietly(); // Guardar sin disparar eventos
            }

            DB::commit();

            // Recargar con relaciones
            $asset->load([
                'category', 'status', 'priority', 'companySite', 'productionLine', 'system',
                'manufacturer', 'supplier', 'specifications', 'maintenanceTypes',
                'parent', 'children', 'users', 'createdBy', 'updatedBy',
            ]);

            return ApiResponse::success(
                $this->transformAssetDetail($asset),
                'Activo creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear el activo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar un activo existente
     * 
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function update(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        // Validación
        $validator = Validator::make($request->all(), [
            'code' => 'string|max:50',
            'name' => 'string|max:200',
            'description' => 'nullable|string',
            'company_site_id' => 'nullable|integer|exists:company_sites,id',
            'production_line_id' => 'nullable|integer|exists:production_lines,id',
            'system_id'         => 'nullable|integer|exists:asset_systems,id',
            'parent_id' => 'nullable|integer|exists:assets,id',
            'category_id' => 'integer|exists:asset_categories,id',
            'status_id' => 'integer|exists:asset_statuses,id',
            'priority_id' => 'nullable|integer|exists:asset_priorities,id',
            'brand' => 'nullable|string|max:100',
            'model' => 'nullable|string|max:100',
            'serial_number' => 'nullable|string|max:100',
            'manufacturer_id' => 'nullable|integer|exists:asset_vendors,id',
            'supplier_id' => 'nullable|integer|exists:asset_vendors,id',
            'capacity' => 'nullable|numeric',
            'capacity_unit' => 'nullable|string|max:50',
            'manufacturing_year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'materials_used' => 'nullable|array',
            'location_details' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'purchase_cost' => 'nullable|numeric',
            'currency_id' => 'nullable|integer|exists:currencies,id',
            'purchase_date' => 'nullable|date',
            'installation_date' => 'nullable|date',
            'end_of_life_date' => 'nullable|date|after_or_equal:installation_date',
            'cost_center' => 'nullable|string|max:100',
            'image' => 'nullable|image|max:5120', // Max 5MB
            'remove_image' => 'nullable|in:0,1',
            'is_active' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Validar código único (excepto el activo actual)
        if ($request->filled('code') && $request->code !== $asset->code) {
            $existingCode = Asset::byCompany($companyId)
                ->where('code', $request->code)
                ->where('id', '!=', $assetId)
                ->exists();

            if ($existingCode) {
                return ApiResponse::error('El código del activo ya existe para esta empresa', 422);
            }
        }

        // Validar que parent_id no cree un ciclo
        if ($request->filled('parent_id') && $request->parent_id !== $asset->parent_id) {
            if ($request->parent_id == $assetId) {
                return ApiResponse::error('Un activo no puede ser padre de sí mismo', 422);
            }

            // Verificar que no esté intentando hacer un ciclo
            $parent = Asset::find($request->parent_id);
            if ($parent && $this->wouldCreateCycle($assetId, $request->parent_id)) {
                return ApiResponse::error('No se puede crear un ciclo en la jerarquía de activos', 422);
            }
        }

        DB::beginTransaction();
        try {
            // Procesar imagen
            $imagePath = $asset->image_path;
            
            // Si se solicita eliminar la imagen
            if ($request->has('remove_image') && $request->remove_image == '1') {
                // Eliminar si existe
                if ($imagePath) {
                    $pathParts = explode('/storage/', $imagePath);
                    if (count($pathParts) > 1) {
                        Storage::disk('public')->delete($pathParts[1]);
                    }
                }
                $imagePath = null;
            }
            
            // Si se subió una nueva imagen
            if ($request->hasFile('image')) {
                // Eliminar imagen anterior si existía
                if ($imagePath) {
                    $pathParts = explode('/storage/', $imagePath);
                    if (count($pathParts) > 1) {
                        Storage::disk('public')->delete($pathParts[1]);
                    }
                }
                
                // Subir nueva imagen
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs('assets', $filename, 'public');
                // URL absoluta para que funcione con frontend en otro puerto
                $imagePath = config('app.url') . Storage::url($path);
            }

            // HU-A3: si tiene padre, los campos de jerarquía son inmutables (heredados del padre)
            // Capturar valores originales ANTES del update (getOriginal() se resetea tras save)
            $originalSiteId = $asset->company_site_id;
            $originalLineId = $asset->production_line_id;
            $originalCatId  = $asset->category_id;

            $newParentId = $request->has('parent_id') ? $request->parent_id : $asset->parent_id;
            $newSiteId   = $request->get('company_site_id', $asset->company_site_id);
            $newLineId   = $request->get('production_line_id', $asset->production_line_id);
            $newCatId    = $request->get('category_id', $asset->category_id);

            if ($newParentId) {
                $parent = Asset::byCompany($companyId)->find($newParentId);
                if ($parent) {
                    $newSiteId = $parent->company_site_id;
                    $newLineId = $parent->production_line_id;
                    $newCatId  = $parent->category_id;
                }
            }

            $asset->update([
                'code'               => $request->get('code', $asset->code),
                'name'               => $request->get('name', $asset->name),
                'description'        => $request->get('description', $asset->description),
                'company_site_id'    => $newSiteId,
                'production_line_id' => $newLineId,
                'system_id'          => $request->has('system_id') ? $request->system_id : $asset->system_id,
                'parent_id'          => $newParentId,
                'category_id'        => $newCatId,
                'status_id'          => $request->get('status_id', $asset->status_id),
                'priority_id'        => $request->get('priority_id', $asset->priority_id),
                'brand'              => $request->get('brand', $asset->brand),
                'model'              => $request->get('model', $asset->model),
                'serial_number'      => $request->get('serial_number', $asset->serial_number),
                'manufacturer_id'    => $request->get('manufacturer_id', $asset->manufacturer_id),
                'supplier_id'        => $request->get('supplier_id', $asset->supplier_id),
                'capacity'           => $request->get('capacity', $asset->capacity),
                'capacity_unit'      => $request->get('capacity_unit', $asset->capacity_unit),
                'manufacturing_year' => $request->get('manufacturing_year', $asset->manufacturing_year),
                'materials_used'     => $request->get('materials_used', $asset->materials_used),
                'location_details'   => $request->get('location_details', $asset->location_details),
                'latitude'           => $request->has('latitude') ? $request->latitude : $asset->latitude,
                'longitude'          => $request->has('longitude') ? $request->longitude : $asset->longitude,
                'purchase_cost'      => $request->get('purchase_cost', $asset->purchase_cost),
                'currency_id'        => $request->get('currency_id', $asset->currency_id),
                'purchase_date'      => $request->get('purchase_date', $asset->purchase_date),
                'installation_date'  => $request->get('installation_date', $asset->installation_date),
                'end_of_life_date'   => $request->get('end_of_life_date', $asset->end_of_life_date),
                'cost_center'        => $request->get('cost_center', $asset->cost_center),
                'image_path'         => $imagePath,
                'is_active'          => $request->get('is_active', $asset->is_active),
                'updated_by'         => Auth::id()
            ]);

            // HU-A3: si es un activo raíz, propagar sede/línea/categoría
            // a TODOS los descendientes (hijos, nietos, etc.)
            if (!$newParentId) {
                $this->cascadeHierarchyUpdate($asset->id, $newSiteId, $newLineId, $newCatId);
            }

            // Regenerar QR si cambió el código o nombre
            if ($request->filled('code') || $request->filled('name')) {
                $qrData = [
                    'asset_id' => $asset->id,
                    'code' => $asset->code,
                    'name' => $asset->name,
                    'company_id' => $companyId,
                    'url' => config('app.frontend_url', config('app.url')) . '/assets/' . $asset->id
                ];
                $qrUrl = $this->qrCodeService->regenerate(
                    $asset->qr_code,
                    $qrData,
                    "asset_{$asset->id}_{$asset->code}"
                );
                if ($qrUrl) {
                    $asset->qr_code = $qrUrl;
                    $asset->saveQuietly();
                }
            }

            DB::commit();

            // Recargar con relaciones
            $asset->load([
                'category', 'status', 'priority', 'companySite', 'productionLine', 'system',
                'manufacturer', 'supplier', 'specifications', 'maintenanceTypes',
                'parent', 'children', 'users', 'createdBy', 'updatedBy',
            ]);

            return ApiResponse::success(
                $this->transformAssetDetail($asset),
                'Activo actualizado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar el activo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar un activo (soft delete)
     * 
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function destroy(int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        // Verificar que no tenga hijos activos
        $hasChildren = Asset::where('parent_id', $assetId)->exists();
        if ($hasChildren) {
            return ApiResponse::error('No se puede eliminar un activo que tiene activos hijos', 422);
        }

        try {
            $asset->delete();
            return ApiResponse::success(null, 'Activo eliminado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar el activo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Regenerar código QR de un activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function generateQR(int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        try {
            // URL del frontend que muestra el activo
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
            $publicUrl = $frontendUrl . '/activos/' . $asset->code;

            $qrData = [
                'asset_id' => $asset->id,
                'code' => $asset->code,
                'name' => $asset->name,
                'company_id' => $companyId,
                'url' => $publicUrl
            ];

            $qrUrl = $this->qrCodeService->regenerate(
                $asset->qr_code,
                $qrData,
                "asset_{$asset->id}_{$asset->code}"
            );

            if ($qrUrl) {
                $asset->qr_code = $qrUrl;
                $asset->saveQuietly();

                return ApiResponse::success([
                    'qr_code' => $qrUrl,
                    'asset_id' => $asset->id,
                    'asset_code' => $asset->code,
                    'public_url' => $publicUrl
                ], 'Código QR generado exitosamente');
            }

            return ApiResponse::error('Error al generar el código QR', 500);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al generar el código QR: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generar PDF con la hoja de vida completa del activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @return \Illuminate\Http\Response
     */
    public function generateAssetProfilePDF(int $companyId, int $assetId)
    {
        $asset = Asset::byCompany($companyId)
            ->with([
                'company',
                'category',
                'status',
                'priority',
                'companySite',
                'specifications'
            ])
            ->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        try {
            // Si no tiene QR, generarlo
            if (!$asset->qr_code) {
                $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
                $publicUrl = $frontendUrl . '/activos/' . $asset->code;
                $qrUrl = $this->qrCodeService->generateAndUpload($publicUrl, "asset_{$asset->id}_{$asset->code}");
                if ($qrUrl) {
                    $asset->qr_code = $qrUrl;
                    $asset->saveQuietly();
                }
            }

            // Convertir QR a base64 para el PDF
            $qrBase64 = null;
            if ($asset->qr_code && file_exists(public_path(str_replace(url('/'), '', $asset->qr_code)))) {
                $qrPath = public_path(str_replace(url('/'), '', $asset->qr_code));
                $qrBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($qrPath));
            }

            $pdf = PDF::loadView('pdf.asset-profile', [
                'asset' => $asset,
                'qrBase64' => $qrBase64
            ]);
            $filename = "Hoja_Vida_{$asset->code}_{date('Ymd')}.pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al generar el PDF: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generar PDF con etiqueta QR para imprimir y pegar en el activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @return \Illuminate\Http\Response
     */
    public function generateQRLabelPDF(int $companyId, int $assetId)
    {
        $asset = Asset::byCompany($companyId)
            ->with(['company', 'category', 'companySite'])
            ->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        try {
            // Generar QR si no existe
            if (!$asset->qr_code) {
                $publicUrl = config('app.url') . '/api/public/asset-view/' . $asset->code;
                
                $qrData = [
                    'asset_id' => $asset->id,
                    'code' => $asset->code,
                    'name' => $asset->name,
                    'company_id' => $companyId,
                    'url' => $publicUrl
                ];

                $qrUrl = $this->qrCodeService->regenerate(
                    null,
                    $qrData,
                    "asset_{$asset->id}_{$asset->code}"
                );

                if ($qrUrl) {
                    $asset->qr_code = $qrUrl;
                    $asset->saveQuietly();
                }
            }

            // Convertir QR a data URI para embeber en PDF
            $qrCodeDataUri = $asset->qr_code;
            if (filter_var($asset->qr_code, FILTER_VALIDATE_URL)) {
                // Si es URL, intentar convertir a data URI
                try {
                    $qrImageContent = file_get_contents($asset->qr_code);
                    $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($qrImageContent);
                } catch (\Exception $e) {
                    // Si falla, usar la URL directamente
                    $qrCodeDataUri = $asset->qr_code;
                }
            }

            $pdf = PDF::loadView('pdf.asset-qr-label', [
                'asset' => $asset,
                'qrCodeDataUri' => $qrCodeDataUri
            ]);
            
            $pdf->setPaper([0, 0, 283.46, 283.46]); // 10cm x 10cm
            $filename = "QR_Label_{$asset->code}.pdf";

            return $pdf->download($filename);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al generar la etiqueta QR: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Vista pública HTML del activo (desde QR Code)
     * Muestra información y botón para crear solicitud
     * 
     * @param string $assetCode
     * @return \Illuminate\View\View
     */
    public function publicAssetView(string $assetCode)
    {
        $asset = Asset::where('code', $assetCode)
            ->with([
                'company',
                'category',
                'status',
                'priority',
                'companySite'
            ])
            ->first();

        if (!$asset) {
            abort(404, 'Activo no encontrado');
        }

        return view('public.asset-info', ['asset' => $asset]);
    }

    /**
     * Listar activos públicamente (para búsqueda cuando QR no es legible)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function publicAssetsList(Request $request): JsonResponse
    {
        $query = Asset::where('is_active', true)
            ->with([
                'category:id,code,name,icon,color',
                'companySite:id,name',
                'productionLine:id,name',
            ]);

        // Filtro por búsqueda (código o nombre) - REQUERIDO
        if (!$request->filled('search')) {
            return response()->json([
                'success' => false,
                'message' => 'El parámetro search es requerido',
            ], 400);
        }

        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%");
        });

        // Filtro opcional por sitio/sede
        if ($request->filled('site_id')) {
            $query->where('company_site_id', $request->site_id);
        }

        // Ordenamiento
        $query->orderBy('name', 'asc');

        // Limitar resultados para endpoint público
        $perPage = min($request->get('per_page', 20), 50); // Máximo 50 resultados
        $assets = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $assets->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'code' => $asset->code,
                    'name' => $asset->name,
                    'location_path' => $asset->location_path,
                    'category' => $asset->category ? [
                        'id' => $asset->category->id,
                        'code' => $asset->category->code,
                        'name' => $asset->category->name,
                        'icon' => $asset->category->icon,
                        'color' => $asset->category->color,
                    ] : null,
                    'site' => $asset->companySite ? [
                        'id' => $asset->companySite->id,
                        'name' => $asset->companySite->name,
                    ] : null,
                    'production_line' => $asset->productionLine ? [
                        'id'   => $asset->productionLine->id,
                        'name' => $asset->productionLine->name,
                    ] : null,
                ];
            }),
            'pagination' => [
                'total' => $assets->total(),
                'per_page' => $assets->perPage(),
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'from' => $assets->firstItem(),
                'to' => $assets->lastItem(),
            ],
            'message' => 'Activos recuperados exitosamente',
        ]);
    }

    /**
     * Exportar activos a CSV
     * Columnas: CODIGO, EQUIPO, DESCRIPCION, SISTEMA, CATEGORIA, AREA
     */
    public function exportCsv(Request $request, int $companyId)
    {
        $rows = \Illuminate\Support\Facades\DB::table('assets as a')
            ->select(
                'a.code as Codigo',
                'a.name as Equipo',
                'a.description as Descripcion',
                't.name as Sistema',
                'ac.name as Categoria',
                'pl.name as Area'
            )
            ->leftJoin('production_lines as pl', 'pl.id', '=', 'a.production_line_id')
            ->leftJoin('asset_systems as t', 't.id', '=', 'a.system_id')
            ->leftJoin('asset_categories as ac', 'ac.id', '=', 'a.category_id')
            ->where('a.company_id', $companyId)
            ->whereNull('a.deleted_at')
            ->orderBy('a.code')
            ->get();

        $filename = 'activos_' . date('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['CÓDIGO', 'EQUIPO', 'DESCRIPCIÓN', 'SISTEMA', 'CATEGORÍA', 'ÁREA'], ';');
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->Codigo,
                    $row->Equipo,
                    $row->Descripcion ?? '',
                    $row->Sistema     ?? '',
                    $row->Categoria   ?? '',
                    $row->Area        ?? '',
                ], ';');
            }
            fclose($out);
        }, 200, $headers);
    }

    /**
     * Transformar activo para respuesta (lista)
     */
    private function transformAsset($asset): array
    {
        $data = [
            'id' => $asset->id,
            'code' => $asset->code,
            'name' => $asset->name,
            'location_path' => $asset->location_path,
            'category' => $asset->category ? [
                'id' => $asset->category->id,
                'code' => $asset->category->code,
                'name' => $asset->category->name,
                'icon' => $asset->category->icon,
                'color' => $asset->category->color
            ] : null,
            'status' => $asset->status ? [
                'id' => $asset->status->id,
                'code' => $asset->status->code,
                'name' => $asset->status->name,
                'color' => $asset->status->color
            ] : null,
            'priority' => $asset->priority ? [
                'id' => $asset->priority->id,
                'code' => $asset->priority->code,
                'name' => $asset->priority->name,
                'level' => $asset->priority->level
            ] : null,
            'company_site_id' => $asset->company_site_id,
            'site' => $asset->companySite ? [
                'id' => $asset->companySite->id,
                'name' => $asset->companySite->name
            ] : null,
            'category_id' => $asset->category_id,
            'production_line_id' => $asset->production_line_id,
            'production_line' => $asset->productionLine ? [
                'id'   => $asset->productionLine->id,
                'code' => $asset->productionLine->code,
                'name' => $asset->productionLine->name
            ] : null,
            'parent_id' => $asset->parent_id,
            'has_children' => $asset->children->isNotEmpty(),
            'is_active' => $asset->is_active,
            'created_at' => $asset->created_at->toIso8601String()
        ];

        // Si los children están cargados, incluirlos recursivamente
        if ($asset->relationLoaded('children') && $asset->children->isNotEmpty()) {
            $data['children'] = $asset->children->map(function ($child) {
                return $this->transformAsset($child);
            })->toArray();
        }

        return $data;
    }

    /**
     * HU-A3: Propagar company_site_id, production_line_id y category_id
     * a todos los descendientes de un activo raíz cuando cambian estos valores.
     */
    private function cascadeHierarchyUpdate(int $parentId, ?int $siteId, ?int $lineId, ?int $catId): void
    {
        // Obtener IDs de hijos directos
        $childIds = Asset::where('parent_id', $parentId)->pluck('id');

        if ($childIds->isEmpty()) return;

        // UPDATE directo via query builder — sin Eloquent, sin observers, garantizado
        Asset::whereIn('id', $childIds)->update([
            'company_site_id'    => $siteId,
            'production_line_id' => $lineId,
            'category_id'        => $catId,
            'updated_at'         => now(),
        ]);

        // Recursivo: procesar cada hijo para actualizar sus propios hijos
        foreach ($childIds as $childId) {
            $this->cascadeHierarchyUpdate($childId, $siteId, $lineId, $catId);
        }
    }

    /**
     * Transformar activo para respuesta detallada
     */
    private function transformAssetDetail($asset): array
    {
        $data = [
            'id' => $asset->id,
            'code' => $asset->code,
            'name' => $asset->name,
            'description' => $asset->description,
            'location_path' => $asset->location_path,
            'location_details' => $asset->location_details,
            'category' => $asset->category ? [
                'id' => $asset->category->id,
                'code' => $asset->category->code,
                'name' => $asset->category->name,
                'icon' => $asset->category->icon,
                'color' => $asset->category->color
            ] : null,
            'status' => $asset->status ? [
                'id' => $asset->status->id,
                'code' => $asset->status->code,
                'name' => $asset->status->name,
                'color' => $asset->status->color,
                'is_operational' => $asset->status->is_operational
            ] : null,
            'priority' => $asset->priority ? [
                'id' => $asset->priority->id,
                'code' => $asset->priority->code,
                'name' => $asset->priority->name,
                'level' => $asset->priority->level,
                'color' => $asset->priority->color
            ] : null,
            'site' => $asset->companySite ? [
                'id' => $asset->companySite->id,
                'name' => $asset->companySite->name
            ] : null,
            'production_line' => $asset->productionLine ? [
                'id'   => $asset->productionLine->id,
                'code' => $asset->productionLine->code,
                'name' => $asset->productionLine->name
            ] : null,
            'production_line_id' => $asset->production_line_id,
            'system' => $asset->system ? [
                'id'   => $asset->system->id,
                'name' => $asset->system->name,
            ] : null,
            'system_id' => $asset->system_id,
            'parent' => $asset->parent ? [
                'id' => $asset->parent->id,
                'code' => $asset->parent->code,
                'name' => $asset->parent->name
            ] : null,
            'technical_data' => [
                'brand'              => $asset->brand,
                'model'              => $asset->model,
                'serial_number'      => $asset->serial_number,
                'manufacturer'       => $asset->manufacturer ? [
                    'id'   => $asset->manufacturer->id,
                    'code' => $asset->manufacturer->code,
                    'name' => $asset->manufacturer->name,
                ] : null,
                'supplier'           => $asset->supplier ? [
                    'id'   => $asset->supplier->id,
                    'code' => $asset->supplier->code,
                    'name' => $asset->supplier->name,
                ] : null,
                'capacity'           => $asset->capacity,
                'capacity_unit'      => $asset->capacity_unit,
                'manufacturing_year' => $asset->manufacturing_year,
                'installation_date'  => $asset->installation_date ? $asset->installation_date->format('Y-m-d') : null,
                'end_of_life_date'   => $asset->end_of_life_date ? $asset->end_of_life_date->format('Y-m-d') : null,
                'materials_used'     => $asset->materials_used,
            ],
            'maintenance_types' => $asset->relationLoaded('maintenanceTypes')
                ? $asset->maintenanceTypes->map(function ($t) {
                    return [
                        'id'          => $t->id,
                        'code'        => $t->code,
                        'name'        => $t->name,
                        'order_index' => $t->pivot->order_index,
                    ];
                })
                : [],
            'specifications' => $asset->specifications->map(function ($spec) {
                return [
                    'id' => $spec->id,
                    'key' => $spec->spec_key,
                    'value' => $spec->spec_value,
                    'unit' => $spec->spec_unit,
                    'type' => $spec->spec_type
                ];
            }),
            'location' => [
                'latitude' => $asset->latitude,
                'longitude' => $asset->longitude,
                'has_coordinates' => $asset->has_coordinates
            ],
            'financial' => [
                'purchase_cost' => $asset->purchase_cost,
                'currency' => $asset->currency ? [
                    'id' => $asset->currency->id,
                    'code' => $asset->currency->code,
                    'symbol' => $asset->currency->symbol
                ] : null,
                'purchase_date' => $asset->purchase_date ? $asset->purchase_date->format('Y-m-d') : null,
                'cost_center' => $asset->cost_center
            ],
            'assigned_users' => $asset->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->pivot->role,
                    'assigned_at' => $user->pivot->assigned_at
                ];
            }),
            'qr_code' => $asset->qr_code,
            'image_path' => $asset->image_path,
            'is_active' => $asset->is_active,
            'parent_id' => $asset->parent_id,
            'has_children' => $asset->children->isNotEmpty(),
            'created_by' => $asset->createdBy ? [
                'id' => $asset->createdBy->id,
                'name' => $asset->createdBy->full_name
            ] : null,
            'updated_by' => $asset->updatedBy ? [
                'id' => $asset->updatedBy->id,
                'name' => $asset->updatedBy->full_name
            ] : null,
            'created_at' => $asset->created_at->toIso8601String(),
            'updated_at' => $asset->updated_at->toIso8601String()
        ];

        // Incluir children si están cargados (solo 1 nivel en detalle)
        if ($asset->relationLoaded('children') && $asset->children->isNotEmpty()) {
            $data['children'] = $asset->children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'code' => $child->code,
                    'name' => $child->name,
                    'location_path' => $child->location_path,
                    'category' => $child->category ? [
                        'id' => $child->category->id,
                        'code' => $child->category->code,
                        'name' => $child->category->name,
                        'icon' => $child->category->icon,
                        'color' => $child->category->color
                    ] : null,
                    'status' => $child->status ? [
                        'id' => $child->status->id,
                        'code' => $child->status->code,
                        'name' => $child->status->name,
                        'color' => $child->status->color
                    ] : null,
                    'priority' => $child->priority ? [
                        'id' => $child->priority->id,
                        'code' => $child->priority->code,
                        'name' => $child->priority->name,
                        'level' => $child->priority->level
                    ] : null,
                    'is_active' => $child->is_active,
                    'has_children' => $child->children()->exists()
                ];
            })->toArray();
        }

        return $data;
    }

    /**
     * Verificar si crear un ciclo en la jerarquía
     */
    private function wouldCreateCycle($assetId, $parentId): bool
    {
        $currentParentId = $parentId;
        $maxDepth = 50;
        $depth = 0;

        while ($currentParentId && $depth < $maxDepth) {
            if ($currentParentId == $assetId) {
                return true; // Se encontró un ciclo
            }

            $parent = Asset::find($currentParentId);
            $currentParentId = $parent ? $parent->parent_id : null;
            $depth++;
        }

        return false;
    }

    /**
     * ===============================================
     * GESTIÓN DE ESPECIFICACIONES TÉCNICAS
     * ===============================================
     */

    /**
     * Agregar especificación técnica a un activo
     */
    public function addSpecification(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'spec_key' => 'required|string|max:100',
            'spec_value' => 'required|string|max:255',
            'spec_unit' => 'nullable|string|max:50',
            'spec_type' => 'nullable|string|in:text,number,date,boolean',
            'display_order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            $specification = $asset->specifications()->create([
                'spec_key' => $request->spec_key,
                'spec_value' => $request->spec_value,
                'spec_unit' => $request->spec_unit,
                'spec_type' => $request->spec_type ?? 'text',
                'display_order' => $request->display_order ?? 0
            ]);

            return ApiResponse::success([
                'id' => $specification->id,
                'key' => $specification->spec_key,
                'value' => $specification->spec_value,
                'unit' => $specification->spec_unit,
                'type' => $specification->spec_type,
                'display_order' => $specification->display_order
            ], 'Especificación agregada exitosamente', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al agregar especificación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar especificación técnica
     */
    public function updateSpecification(Request $request, int $companyId, int $assetId, int $specId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $specification = AssetSpecification::where('asset_id', $assetId)->find($specId);

        if (!$specification) {
            return ApiResponse::notFound('Especificación no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'spec_key' => 'string|max:100',
            'spec_value' => 'string|max:255',
            'spec_unit' => 'nullable|string|max:50',
            'spec_type' => 'nullable|string|in:text,number,date,boolean',
            'display_order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            $specification->update($request->only(['spec_key', 'spec_value', 'spec_unit', 'spec_type', 'display_order']));

            return ApiResponse::success([
                'id' => $specification->id,
                'key' => $specification->spec_key,
                'value' => $specification->spec_value,
                'unit' => $specification->spec_unit,
                'type' => $specification->spec_type,
                'display_order' => $specification->display_order
            ], 'Especificación actualizada exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar especificación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar especificación técnica
     */
    public function deleteSpecification(int $companyId, int $assetId, int $specId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $specification = AssetSpecification::where('asset_id', $assetId)->find($specId);

        if (!$specification) {
            return ApiResponse::notFound('Especificación no encontrada');
        }

        try {
            $specification->delete();
            return ApiResponse::success(null, 'Especificación eliminada exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar especificación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * ===============================================
     * GESTIÓN DE USUARIOS ASIGNADOS
     * ===============================================
     */

    /**
     * Listar usuarios asignados a un activo
     */
    public function getAssignedUsers(int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)
            ->with(['users' => function ($query) {
                $query->withPivot('role', 'assigned_at', 'assigned_by');
            }])
            ->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $users = $asset->users->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'assigned_at' => $user->pivot->assigned_at,
                'assigned_by' => $user->pivot->assigned_by
            ];
        });

        return ApiResponse::success($users, 'Usuarios asignados recuperados exitosamente');
    }

    /**
     * Asignar usuario a un activo
     */
    public function assignUser(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|string|in:responsible,operator,supervisor,maintainer'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Verificar que el usuario pertenezca a la misma empresa
        $user = User::find($request->user_id);
        $userCompany = DB::table('user_companies')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->first();

        if (!$userCompany) {
            return ApiResponse::error('El usuario no pertenece a esta empresa', 422);
        }

        // Verificar si ya está asignado con ese rol
        $existingAssignment = DB::table('asset_users')
            ->where('asset_id', $assetId)
            ->where('user_id', $request->user_id)
            ->where('role', $request->role)
            ->exists();

        if ($existingAssignment) {
            return ApiResponse::error('El usuario ya está asignado con ese rol', 422);
        }

        try {
            $asset->users()->attach($request->user_id, [
                'role' => $request->role,
                'assigned_at' => now(),
                'assigned_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return ApiResponse::success([
                'user_id' => $user->id,
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $request->role
            ], 'Usuario asignado exitosamente', 201);
        } catch (\Exception $e) {
            return ApiResponse::error('Error al asignar usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remover asignación de usuario
     */
    public function removeUser(int $companyId, int $assetId, int $userId): JsonResponse
    {
        $asset = Asset::byCompany($companyId)->find($assetId);

        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $user = User::find($userId);
        if (!$user) {
            return ApiResponse::notFound('Usuario no encontrado');
        }

        // Verificar que el usuario esté asignado
        $assignment = DB::table('asset_users')
            ->where('asset_id', $assetId)
            ->where('user_id', $userId)
            ->exists();

        if (!$assignment) {
            return ApiResponse::error('El usuario no está asignado a este activo', 422);
        }

        try {
            $asset->users()->detach($userId);
            return ApiResponse::success(null, 'Usuario removido exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al remover usuario: ' . $e->getMessage(), 500);
        }
    }

    // ======================
    // ESTADÍSTICAS Y REPORTES
    // ======================

    /**
     * Obtener estadísticas generales de activos
     */
    public function stats(Request $request, $companyId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::error('Empresa no encontrada', 404);
        }

        try {
            // Total de activos
            $totalAssets = Asset::byCompany($companyId)->count();

            // Activos por estado
            $assetsByStatus = Asset::byCompany($companyId)
                ->select('status_id', DB::raw('count(*) as count'))
                ->with('status:id,code,name,color')
                ->groupBy('status_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'status' => [
                            'id' => $item->status->id ?? null,
                            'code' => $item->status->code ?? null,
                            'name' => $item->status->name ?? null,
                            'color' => $item->status->color ?? null
                        ],
                        'count' => $item->count
                    ];
                });

            // Activos activos vs inactivos
            $activeCount = Asset::byCompany($companyId)->where('is_active', true)->count();
            $inactiveCount = Asset::byCompany($companyId)->where('is_active', false)->count();

            // Activos críticos (prioridad 4)
            $criticalCount = Asset::byCompany($companyId)
                ->whereHas('priority', function ($query) {
                    $query->where('level', 4);
                })
                ->count();

            // Valor total por moneda
            $totalValueByCurrency = Asset::byCompany($companyId)
                ->whereNotNull('purchase_cost')
                ->whereNotNull('currency_id')
                ->select('currency_id', DB::raw('SUM(purchase_cost) as total_value'))
                ->with('currency:id,code,symbol')
                ->groupBy('currency_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'currency' => [
                            'id' => $item->currency->id ?? null,
                            'code' => $item->currency->code ?? null,
                            'symbol' => $item->currency->symbol ?? null
                        ],
                        'total_value' => (float) $item->total_value
                    ];
                });

            // Activos con coordenadas GPS
            $assetsWithCoordinates = Asset::byCompany($companyId)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->count();

            // Activos raíz (sin padre)
            $rootAssetsCount = Asset::byCompany($companyId)
                ->whereNull('parent_id')
                ->count();

            return ApiResponse::success([
                'total_assets' => $totalAssets,
                'active_count' => $activeCount,
                'inactive_count' => $inactiveCount,
                'critical_count' => $criticalCount,
                'root_assets_count' => $rootAssetsCount,
                'assets_with_coordinates' => $assetsWithCoordinates,
                'assets_by_status' => $assetsByStatus,
                'total_value_by_currency' => $totalValueByCurrency
            ], 'Estadísticas recuperadas exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener estadísticas: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener distribución de activos por categoría
     */
    public function getAssetsByCategory(Request $request, $companyId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::error('Empresa no encontrada', 404);
        }

        try {
            $distribution = Asset::byCompany($companyId)
                ->select('category_id', DB::raw('count(*) as count'))
                ->with('category:id,code,name,icon,color')
                ->groupBy('category_id')
                ->get()
                ->map(function ($item) use ($companyId) {
                    $total = Asset::byCompany($companyId)->count();
                    $percentage = $total > 0 ? round(($item->count / $total) * 100, 2) : 0;

                    return [
                        'category' => [
                            'id' => $item->category->id ?? null,
                            'code' => $item->category->code ?? null,
                            'name' => $item->category->name ?? null,
                            'icon' => $item->category->icon ?? null,
                            'color' => $item->category->color ?? null
                        ],
                        'count' => $item->count,
                        'percentage' => $percentage
                    ];
                });

            return ApiResponse::success(
                $distribution,
                'Distribución por categoría recuperada exitosamente'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener distribución: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener distribución de activos por estado
     */
    public function getAssetsByStatus(Request $request, $companyId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::error('Empresa no encontrada', 404);
        }

        try {
            $distribution = Asset::byCompany($companyId)
                ->select('status_id', DB::raw('count(*) as count'))
                ->with('status:id,code,name,color,is_operational')
                ->groupBy('status_id')
                ->get()
                ->map(function ($item) use ($companyId) {
                    $total = Asset::byCompany($companyId)->count();
                    $percentage = $total > 0 ? round(($item->count / $total) * 100, 2) : 0;

                    return [
                        'status' => [
                            'id' => $item->status->id ?? null,
                            'code' => $item->status->code ?? null,
                            'name' => $item->status->name ?? null,
                            'color' => $item->status->color ?? null,
                            'is_operational' => $item->status->is_operational ?? false
                        ],
                        'count' => $item->count,
                        'percentage' => $percentage
                    ];
                });

            // Resumen operacional
            $operationalCount = Asset::byCompany($companyId)
                ->whereHas('status', function ($query) {
                    $query->where('is_operational', true);
                })
                ->count();

            $nonOperationalCount = Asset::byCompany($companyId)
                ->whereHas('status', function ($query) {
                    $query->where('is_operational', false);
                })
                ->count();

            return ApiResponse::success([
                'distribution' => $distribution,
                'summary' => [
                    'operational_count' => $operationalCount,
                    'non_operational_count' => $nonOperationalCount
                ]
            ], 'Distribución por estado recuperada exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener distribución: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener distribución de activos por sede
     */
    public function getAssetsBySite(Request $request, $companyId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::error('Empresa no encontrada', 404);
        }

        try {
            $distribution = Asset::byCompany($companyId)
                ->select('company_site_id', DB::raw('count(*) as count'))
                ->with('companySite:id,name')
                ->groupBy('company_site_id')
                ->get()
                ->map(function ($item) use ($companyId) {
                    $total = Asset::byCompany($companyId)->count();
                    $percentage = $total > 0 ? round(($item->count / $total) * 100, 2) : 0;

                    return [
                        'site' => [
                            'id' => $item->companySite->id ?? null,
                            'name' => $item->companySite->name ?? 'Sin sede'
                        ],
                        'count' => $item->count,
                        'percentage' => $percentage
                    ];
                });

            return ApiResponse::success(
                $distribution,
                'Distribución por sede recuperada exitosamente'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Error al obtener distribución: ' . $e->getMessage(), 500);
        }
    }

    // ======================
    // ACCIONES ESPECIALES
    // ======================

    /**
     * Alternar estado activo/inactivo de un activo
     */
    public function toggleActive(Request $request, $companyId, $assetId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::error('Empresa no encontrada', 404);
        }

        $asset = Asset::byCompany($companyId)->find($assetId);
        if (!$asset) {
            return ApiResponse::error('Activo no encontrado', 404);
        }

        try {
            $asset->is_active = !$asset->is_active;
            $asset->updated_by = Auth::id();
            $asset->save();

            $status = $asset->is_active ? 'activado' : 'desactivado';
            return ApiResponse::success(
                $this->transformAsset($asset),
                "Activo {$status} exitosamente"
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Error al cambiar estado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Mover un activo (cambiar sede o padre)
     */
    public function moveAsset(Request $request, $companyId, $assetId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::error('Empresa no encontrada', 404);
        }

        $asset = Asset::byCompany($companyId)->find($assetId);
        if (!$asset) {
            return ApiResponse::error('Activo no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'company_site_id' => 'nullable|integer|exists:company_sites,id',
            'parent_id' => 'nullable|integer|exists:assets,id'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Validar que no se asigne a sí mismo como padre
        if ($request->has('parent_id') && $request->parent_id == $assetId) {
            return ApiResponse::error('Un activo no puede ser padre de sí mismo', 422);
        }

        // Validar que el nuevo padre pertenezca a la misma empresa
        if ($request->has('parent_id') && $request->parent_id) {
            $newParent = Asset::byCompany($companyId)->find($request->parent_id);
            if (!$newParent) {
                return ApiResponse::error('El activo padre no pertenece a esta empresa', 422);
            }

            // Verificar que no cree un ciclo
            if ($this->wouldCreateCycle($assetId, $request->parent_id)) {
                return ApiResponse::error('No se puede mover el activo: se crearía un ciclo en la jerarquía', 422);
            }
        }

        // Validar que la sede pertenezca a la misma empresa
        if ($request->has('company_site_id') && $request->company_site_id) {
            $site = CompanySite::where('id', $request->company_site_id)
                ->where('company_id', $companyId)
                ->first();
            if (!$site) {
                return ApiResponse::error('La sede no pertenece a esta empresa', 422);
            }
        }

        try {
            if ($request->has('company_site_id')) {
                $asset->company_site_id = $request->company_site_id;
            }

            if ($request->has('parent_id')) {
                $asset->parent_id = $request->parent_id;
            }

            $asset->updated_by = Auth::id();
            $asset->save(); // El Observer actualizará location_path automáticamente

            return ApiResponse::success(
                $this->transformAssetDetail($asset),
                'Activo movido exitosamente'
            );
        } catch (\Exception $e) {
            return ApiResponse::error('Error al mover activo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Duplicar un activo
     */
    public function duplicateAsset(Request $request, $companyId, $assetId)
    {
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::error('Empresa no encontrada', 404);
        }

        $asset = Asset::byCompany($companyId)->find($assetId);
        if (!$asset) {
            return ApiResponse::error('Activo no encontrado', 404);
        }

        $validator = Validator::make($request->all(), [
            'new_code' => 'required|string|max:50',
            'new_name' => 'nullable|string|max:200',
            'duplicate_children' => 'nullable|boolean',
            'duplicate_specifications' => 'nullable|boolean',
            'duplicate_users' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Validar que el nuevo código sea único
        $existingAsset = Asset::byCompany($companyId)
            ->where('code', $request->new_code)
            ->first();

        if ($existingAsset) {
            return ApiResponse::error('Ya existe un activo con ese código', 422);
        }

        DB::beginTransaction();

        try {
            // Crear copia del activo
            $newAsset = $asset->replicate();
            $newAsset->code = $request->new_code;
            $newAsset->name = $request->new_name ?? $asset->name . ' (Copia)';
            $newAsset->qr_code = null; // El QR será único
            $newAsset->created_by = Auth::id();
            $newAsset->updated_by = Auth::id();
            $newAsset->save();

            // Duplicar especificaciones si se solicita
            if ($request->duplicate_specifications ?? true) {
                $specifications = $asset->specifications;
                foreach ($specifications as $spec) {
                    $newSpec = $spec->replicate();
                    $newSpec->asset_id = $newAsset->id;
                    $newSpec->save();
                }
            }

            // Duplicar usuarios asignados si se solicita
            if ($request->duplicate_users ?? false) {
                $users = DB::table('asset_users')
                    ->where('asset_id', $assetId)
                    ->get();

                foreach ($users as $user) {
                    DB::table('asset_users')->insert([
                        'asset_id' => $newAsset->id,
                        'user_id' => $user->user_id,
                        'role' => $user->role,
                        'assigned_at' => now(),
                        'assigned_by' => Auth::id(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }

            // Duplicar hijos recursivamente si se solicita
            if ($request->duplicate_children ?? false) {
                $this->duplicateChildren($asset->id, $newAsset->id, $companyId);
            }

            DB::commit();

            return ApiResponse::success(
                $this->transformAssetDetail($newAsset),
                'Activo duplicado exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al duplicar activo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Método auxiliar para duplicar hijos recursivamente
     */
    private function duplicateChildren($originalParentId, $newParentId, $companyId)
    {
        $children = Asset::byCompany($companyId)
            ->where('parent_id', $originalParentId)
            ->get();

        foreach ($children as $child) {
            // Generar código único para el hijo
            $baseCode = $child->code . '-COPY';
            $newCode = $baseCode;
            $counter = 1;
            while (Asset::byCompany($companyId)->where('code', $newCode)->exists()) {
                $newCode = $baseCode . '-' . $counter;
                $counter++;
            }

            // Crear copia del hijo
            $newChild = $child->replicate();
            $newChild->code = $newCode;
            $newChild->name = $child->name . ' (Copia)';
            $newChild->parent_id = $newParentId;
            $newChild->qr_code = null;
            $newChild->created_by = Auth::id();
            $newChild->updated_by = Auth::id();
            $newChild->save();

            // Duplicar especificaciones del hijo
            $specs = $child->specifications;
            foreach ($specs as $spec) {
                $newSpec = $spec->replicate();
                $newSpec->asset_id = $newChild->id;
                $newSpec->save();
            }

            // Llamar recursivamente para los nietos
            $this->duplicateChildren($child->id, $newChild->id, $companyId);
        }
    }

    /**
     * Exportar activo a PDF (alias de generateAssetProfilePDF)
     * 
     * @param int $companyId
     * @param int $assetId
     * @return \Illuminate\Http\Response
     */
    public function exportPdf(int $companyId, int $assetId)
    {
        return $this->generateAssetProfilePDF($companyId, $assetId);
    }

    // ==================== ASSET NOTES (NOTAS DEL ACTIVO) ====================

    /**
     * Obtener todas las notas de un activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function getNotes(int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        $notes = $asset->notes()
            ->with('createdBy:id,first_name,last_name,email')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($note) {
                return [
                    'id' => $note->id,
                    'text' => $note->text,
                    'created_by' => [
                        'id' => $note->createdBy->id,
                        'name' => $note->createdBy->first_name . ' ' . $note->createdBy->last_name,
                        'email' => $note->createdBy->email,
                    ],
                    'created_at' => $note->created_at->toISOString(),
                ];
            });

        return ApiResponse::success($notes, 'Notas del activo obtenidas exitosamente');
    }

    /**
     * Crear una nota para un activo
     * 
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function createNote(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Errores de validación', 422, $validator->errors());
        }

        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        $note = $asset->notes()->create([
            'text' => $request->text,
            'created_by' => Auth::id(),
        ]);

        $note->load('createdBy:id,first_name,last_name,email');

        return ApiResponse::success([
            'id' => $note->id,
            'text' => $note->text,
            'created_by' => [
                'id' => $note->createdBy->id,
                'name' => $note->createdBy->first_name . ' ' . $note->createdBy->last_name,
                'email' => $note->createdBy->email,
            ],
            'created_at' => $note->created_at->toISOString(),
        ], 'Nota creada exitosamente', 201);
    }

    /**
     * Eliminar una nota de un activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @param int $noteId
     * @return JsonResponse
     */
    public function deleteNote(int $companyId, int $assetId, int $noteId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        $note = $asset->notes()->findOrFail($noteId);
        
        $note->delete();

        return ApiResponse::success(null, 'Nota eliminada exitosamente');
    }

    // ==================== ASSET NOTIFICATIONS (NOTIFICACIONES) ====================

    /**
     * Obtener todas las notificaciones configuradas para un activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function getNotifications(int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        $notifications = $asset->notifications()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'email' => $notification->email,
                    'notify_on_create' => $notification->notify_on_create,
                    'notify_on_open' => $notification->notify_on_open,
                    'notify_on_close' => $notification->notify_on_close,
                    'created_at' => $notification->created_at->toISOString(),
                ];
            });

        return ApiResponse::success($notifications, 'Notificaciones del activo obtenidas exitosamente');
    }

    /**
     * Crear una configuración de notificación para un activo
     * 
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function createNotification(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'notify_on_create' => 'boolean',
            'notify_on_open' => 'boolean',
            'notify_on_close' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Errores de validación', 422, $validator->errors());
        }

        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        // Verificar si ya existe una notificación para este email
        $existing = $asset->notifications()->where('email', $request->email)->first();
        if ($existing) {
            return ApiResponse::error('Ya existe una configuración de notificación para este email', 409);
        }

        $notification = $asset->notifications()->create([
            'email' => $request->email,
            'notify_on_create' => $request->input('notify_on_create', true),
            'notify_on_open' => $request->input('notify_on_open', false),
            'notify_on_close' => $request->input('notify_on_close', true),
        ]);

        return ApiResponse::success([
            'id' => $notification->id,
            'email' => $notification->email,
            'notify_on_create' => $notification->notify_on_create,
            'notify_on_open' => $notification->notify_on_open,
            'notify_on_close' => $notification->notify_on_close,
            'created_at' => $notification->created_at->toISOString(),
        ], 'Notificación creada exitosamente', 201);
    }

    /**
     * Actualizar una configuración de notificación
     * 
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @param int $notificationId
     * @return JsonResponse
     */
    public function updateNotification(Request $request, int $companyId, int $assetId, int $notificationId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|email|max:255',
            'notify_on_create' => 'sometimes|boolean',
            'notify_on_open' => 'sometimes|boolean',
            'notify_on_close' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Errores de validación', 422, $validator->errors());
        }

        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        $notification = $asset->notifications()->findOrFail($notificationId);
        
        // Si se cambia el email, verificar que no exista otro con ese email
        if ($request->has('email') && $request->email !== $notification->email) {
            $existing = $asset->notifications()
                ->where('email', $request->email)
                ->where('id', '!=', $notificationId)
                ->first();
            if ($existing) {
                return ApiResponse::error('Ya existe una configuración de notificación para este email', 409);
            }
        }

        $notification->update($request->only([
            'email',
            'notify_on_create',
            'notify_on_open',
            'notify_on_close',
        ]));

        return ApiResponse::success([
            'id' => $notification->id,
            'email' => $notification->email,
            'notify_on_create' => $notification->notify_on_create,
            'notify_on_open' => $notification->notify_on_open,
            'notify_on_close' => $notification->notify_on_close,
            'created_at' => $notification->created_at->toISOString(),
            'updated_at' => $notification->updated_at->toISOString(),
        ], 'Notificación actualizada exitosamente');
    }

    /**
     * Eliminar una configuración de notificación
     * 
     * @param int $companyId
     * @param int $assetId
     * @param int $notificationId
     * @return JsonResponse
     */
    public function deleteNotification(int $companyId, int $assetId, int $notificationId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        $notification = $asset->notifications()->findOrFail($notificationId);
        
        $notification->delete();

        return ApiResponse::success(null, 'Notificación eliminada exitosamente');
    }

    // ==================== ASSET SPARE PARTS (REPUESTOS ASOCIADOS) ====================

    /**
     * Obtener todos los repuestos asociados a un activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function getSpareParts(int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        $spareParts = $asset->spareParts()
            ->with(['material:id,code,name,description,unit_of_measure', 'createdBy:id,first_name,last_name'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($sparePart) {
                return [
                    'id' => $sparePart->id,
                    'material' => [
                        'id' => $sparePart->material->id,
                        'code' => $sparePart->material->code,
                        'name' => $sparePart->material->name,
                        'description' => $sparePart->material->description,
                        'unit_of_measure' => $sparePart->material->unit_of_measure,
                    ],
                    'created_by' => [
                        'id' => $sparePart->createdBy->id,
                        'name' => $sparePart->createdBy->first_name . ' ' . $sparePart->createdBy->last_name,
                    ],
                    'created_at' => $sparePart->created_at->toISOString(),
                ];
            });

        return ApiResponse::success($spareParts, 'Repuestos del activo obtenidos exitosamente');
    }

    /**
     * Asociar un repuesto a un activo
     * 
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function createSparePart(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'material_id' => 'required|integer|exists:materials,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Errores de validación', 422, $validator->errors());
        }

        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        // Verificar si ya existe esta asociación
        $existing = $asset->spareParts()->where('material_id', $request->material_id)->first();
        if ($existing) {
            return ApiResponse::error('Este repuesto ya está asociado al activo', 409);
        }

        $sparePart = $asset->spareParts()->create([
            'material_id' => $request->material_id,
            'created_by' => Auth::id(),
        ]);

        $sparePart->load(['material:id,code,name,description,unit_of_measure', 'createdBy:id,first_name,last_name']);

        return ApiResponse::success([
            'id' => $sparePart->id,
            'material' => [
                'id' => $sparePart->material->id,
                'code' => $sparePart->material->code,
                'name' => $sparePart->material->name,
                'description' => $sparePart->material->description,
                'unit_of_measure' => $sparePart->material->unit_of_measure,
            ],
            'created_by' => [
                'id' => $sparePart->createdBy->id,
                'name' => $sparePart->createdBy->first_name . ' ' . $sparePart->createdBy->last_name,
            ],
            'created_at' => $sparePart->created_at->toISOString(),
        ], 'Repuesto asociado exitosamente', 201);
    }

    /**
     * Desasociar un repuesto de un activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @param int $sparePartId
     * @return JsonResponse
     */
    public function deleteSparePart(int $companyId, int $assetId, int $sparePartId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        $sparePart = $asset->spareParts()->findOrFail($sparePartId);
        
        $sparePart->delete();

        return ApiResponse::success(null, 'Repuesto desasociado exitosamente');
    }

    // ==================== ASSET ATTACHMENTS (ARCHIVOS ADJUNTOS) ====================

    /**
     * Obtener todos los archivos adjuntos de un activo
     * 
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function getAttachments(int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        $attachments = $asset->attachments()
            ->with('uploadedBy:id,first_name,last_name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($attachment) use ($asset) {
                return [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                    'description' => $attachment->description,
                    'uploaded_by' => [
                        'id' => $attachment->uploadedBy->id,
                        'name' => $attachment->uploadedBy->first_name . ' ' . $attachment->uploadedBy->last_name,
                    ],
                    'created_at' => $attachment->created_at->toISOString(),
                    'download_url' => route('api.assets.attachments.download', [
                        'companyId' => $asset->company_id,
                        'assetId' => $asset->id,
                        'attachmentId' => $attachment->id
                    ]),
                ];
            });

        return ApiResponse::success($attachments, 'Archivos adjuntos obtenidos exitosamente');
    }

    /**
     * Subir un archivo adjunto para un activo
     * 
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function uploadAttachment(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 10MB max
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Errores de validación', 422, $validator->errors());
        }

        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        $file = $request->file('file');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs("assets/{$assetId}/attachments", $fileName, 'private');

        $attachment = $asset->attachments()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'description' => $request->description,
            'uploaded_by' => Auth::id(),
        ]);

        $attachment->load('uploadedBy:id,first_name,last_name');

        return ApiResponse::success([
            'id' => $attachment->id,
            'file_name' => $attachment->file_name,
            'file_type' => $attachment->file_type,
            'file_size' => $attachment->file_size,
            'description' => $attachment->description,
            'uploaded_by' => [
                'id' => $attachment->uploadedBy->id,
                'name' => $attachment->uploadedBy->first_name . ' ' . $attachment->uploadedBy->last_name,
            ],
            'created_at' => $attachment->created_at->toISOString(),
            'download_url' => route('api.assets.attachments.download', [
                'companyId' => $companyId,
                'assetId' => $assetId,
                'attachmentId' => $attachment->id
            ]),
        ], 'Archivo subido exitosamente', 201);
    }

    /**
     * Descargar un archivo adjunto
     * 
     * @param int $companyId
     * @param int $assetId
     * @param int $attachmentId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function downloadAttachment(int $companyId, int $assetId, int $attachmentId)
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        $attachment = $asset->attachments()->findOrFail($attachmentId);
        
        if (!Storage::disk('private')->exists($attachment->file_path)) {
            abort(404, 'Archivo no encontrado');
        }

        return Storage::disk('private')->download($attachment->file_path, $attachment->file_name);
    }

    /**
     * Eliminar un archivo adjunto
     * 
     * @param int $companyId
     * @param int $assetId
     * @param int $attachmentId
     * @return JsonResponse
     */
    public function deleteAttachment(int $companyId, int $assetId, int $attachmentId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        $attachment = $asset->attachments()->findOrFail($attachmentId);
        
        // Eliminar archivo físico
        if (Storage::disk('private')->exists($attachment->file_path)) {
            Storage::disk('private')->delete($attachment->file_path);
        }

        $attachment->delete();

        return ApiResponse::success(null, 'Archivo eliminado exitosamente');
    }

    // ==================== ASSET COSTS (COSTOS HISTÓRICOS) ====================

    /**
     * Obtener histórico de costos de un activo basado en las órdenes de trabajo
     * 
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function getCostsHistory(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $asset = Asset::where('company_id', $companyId)->findOrFail($assetId);
        
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => 'nullable|string|in:month,year,quarter',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Errores de validación', 422, $validator->errors());
        }

        // Obtener órdenes de trabajo completadas del activo
        $query = DB::table('work_orders')
            ->where('asset_id', $assetId)
            ->where('company_id', $companyId)
            ->whereIn('status', ['completed', 'closed']);

        if ($request->filled('start_date')) {
            $query->where('completed_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('completed_at', '<=', $request->end_date);
        }

        // Obtener costos agregados
        $costs = $query->select(
            DB::raw('COUNT(*) as total_work_orders'),
            DB::raw('SUM(actual_labor_cost) as total_labor_cost'),
            DB::raw('SUM(actual_material_cost) as total_materials_cost'),
            DB::raw('SUM(actual_other_cost) as total_external_cost'),
            DB::raw('SUM(actual_labor_cost + actual_material_cost + actual_other_cost) as total_cost')
        )->first();

        // Si se solicita agrupación
        $groupedCosts = [];
        if ($request->filled('group_by')) {
            $groupBy = $request->group_by;
            
            $dateFormat = match($groupBy) {
                'month' => '%Y-%m',
                'year' => '%Y',
                'quarter' => "CONCAT(YEAR(completed_at), '-Q', QUARTER(completed_at))",
                default => '%Y-%m'
            };

            $groupedQuery = DB::table('work_orders')
                ->where('asset_id', $assetId)
                ->where('company_id', $companyId)
                ->whereIn('status', ['completed', 'closed'])
                ->whereNotNull('completed_at');

            if ($request->filled('start_date')) {
                $groupedQuery->where('completed_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $groupedQuery->where('completed_at', '<=', $request->end_date);
            }

            if ($groupBy === 'quarter') {
                $groupedCosts = $groupedQuery->select(
                    DB::raw("CONCAT(YEAR(completed_at), '-Q', QUARTER(completed_at)) as period"),
                    DB::raw('COUNT(*) as work_orders_count'),
                    DB::raw('SUM(actual_labor_cost) as labor_cost'),
                    DB::raw('SUM(actual_material_cost) as materials_cost'),
                    DB::raw('SUM(actual_other_cost) as external_cost'),
                    DB::raw('SUM(actual_labor_cost + actual_material_cost + actual_other_cost) as total_cost')
                )
                ->groupBy('period')
                ->orderBy('period', 'desc')
                ->get();
            } else {
                $groupedCosts = $groupedQuery->select(
                    DB::raw("DATE_FORMAT(completed_at, '{$dateFormat}') as period"),
                    DB::raw('COUNT(*) as work_orders_count'),
                    DB::raw('SUM(actual_labor_cost) as labor_cost'),
                    DB::raw('SUM(actual_material_cost) as materials_cost'),
                    DB::raw('SUM(actual_other_cost) as external_cost'),
                    DB::raw('SUM(actual_labor_cost + actual_material_cost + actual_other_cost) as total_cost')
                )
                ->groupBy('period')
                ->orderBy('period', 'desc')
                ->get();
            }
        }

        return ApiResponse::success([
            'asset' => [
                'id' => $asset->id,
                'code' => $asset->code,
                'name' => $asset->name,
            ],
            'summary' => [
                'total_work_orders' => (int) $costs->total_work_orders,
                'total_labor_cost' => (float) ($costs->total_labor_cost ?? 0),
                'total_materials_cost' => (float) ($costs->total_materials_cost ?? 0),
                'total_external_cost' => (float) ($costs->total_external_cost ?? 0),
                'total_cost' => (float) ($costs->total_cost ?? 0),
            ],
            'grouped_costs' => $groupedCosts,
        ], 'Costos históricos obtenidos exitosamente');
    }

    /**
     * Obtener mediciones de un activo
     *
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function getMeasurements(Request $request, int $companyId, int $assetId): JsonResponse
    {
        // Verificar que el activo existe y pertenece a la empresa
        $asset = Asset::where('id', $assetId)
            ->where('company_id', $companyId)
            ->first();

        if (!$asset) {
            return ApiResponse::error('Activo no encontrado', 404);
        }

        // Construir query de mediciones
        $query = AssetMeasurement::where('asset_id', $assetId)
            ->with('measuredBy:id,name,lastname');

        // Filtro por tipo de medición
        if ($request->filled('measurement_type')) {
            $query->where('measurement_type', $request->measurement_type);
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por rango de fechas
        if ($request->filled('start_date')) {
            $query->where('measured_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->where('measured_at', '<=', $request->end_date);
        }

        // Ordenar por fecha de medición (más reciente primero)
        $measurements = $query->orderBy('measured_at', 'desc')
            ->get()
            ->map(function ($measurement) {
                return [
                    'id' => $measurement->id,
                    'measurement_type' => $measurement->measurement_type,
                    'value' => (float) $measurement->value,
                    'unit' => $measurement->unit,
                    'min_threshold' => $measurement->min_threshold ? (float) $measurement->min_threshold : null,
                    'max_threshold' => $measurement->max_threshold ? (float) $measurement->max_threshold : null,
                    'status' => $measurement->status,
                    'notes' => $measurement->notes,
                    'measured_at' => $measurement->measured_at ? $measurement->measured_at->toIso8601String() : null,
                    'measured_by' => $measurement->measuredBy ? [
                        'id' => $measurement->measuredBy->id,
                        'name' => $measurement->measuredBy->name . ' ' . $measurement->measuredBy->lastname,
                    ] : null,
                ];
            });

        return ApiResponse::success([
            'measurements' => $measurements,
            'count' => $measurements->count(),
        ], 'Mediciones obtenidas exitosamente');
    }

    /**
     * Crear nueva medición para un activo
     *
     * @param Request $request
     * @param int $companyId
     * @param int $assetId
     * @return JsonResponse
     */
    public function createMeasurement(Request $request, int $companyId, int $assetId): JsonResponse
    {
        // Verificar que el activo existe y pertenece a la empresa
        $asset = Asset::where('id', $assetId)
            ->where('company_id', $companyId)
            ->first();

        if (!$asset) {
            return ApiResponse::error('Activo no encontrado', 404);
        }

        // Validar datos
        $validator = Validator::make($request->all(), [
            'measurement_type' => 'required|string|max:100',
            'value' => 'required|numeric',
            'unit' => 'required|string|max:50',
            'min_threshold' => 'nullable|numeric',
            'max_threshold' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'measured_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Errores de validación', 422, $validator->errors());
        }

        // Crear medición
        $measurement = AssetMeasurement::create([
            'asset_id' => $assetId,
            'measurement_type' => $request->measurement_type,
            'value' => $request->value,
            'unit' => $request->unit,
            'min_threshold' => $request->min_threshold,
            'max_threshold' => $request->max_threshold,
            'notes' => $request->notes,
            'measured_at' => $request->measured_at ?? now(),
            'measured_by' => auth()->id(),
        ]);

        // Recargar con relaciones
        $measurement->load('measuredBy:id,name,lastname');

        return ApiResponse::success([
            'id' => $measurement->id,
            'measurement_type' => $measurement->measurement_type,
            'value' => (float) $measurement->value,
            'unit' => $measurement->unit,
            'min_threshold' => $measurement->min_threshold ? (float) $measurement->min_threshold : null,
            'max_threshold' => $measurement->max_threshold ? (float) $measurement->max_threshold : null,
            'status' => $measurement->status,
            'notes' => $measurement->notes,
            'measured_at' => $measurement->measured_at ? $measurement->measured_at->toIso8601String() : null,
            'measured_by' => $measurement->measuredBy ? [
                'id' => $measurement->measuredBy->id,
                'name' => $measurement->measuredBy->name . ' ' . $measurement->measuredBy->lastname,
            ] : null,
        ], 'Medición creada exitosamente', 201);
    }

    /**
     * Eliminar medición de un activo
     *
     * @param int $companyId
     * @param int $assetId
     * @param int $measurementId
     * @return JsonResponse
     */
    public function deleteMeasurement(int $companyId, int $assetId, int $measurementId): JsonResponse
    {
        // Verificar que el activo existe y pertenece a la empresa
        $asset = Asset::where('id', $assetId)
            ->where('company_id', $companyId)
            ->first();

        if (!$asset) {
            return ApiResponse::error('Activo no encontrado', 404);
        }

        // Buscar medición
        $measurement = AssetMeasurement::where('id', $measurementId)
            ->where('asset_id', $assetId)
            ->first();

        if (!$measurement) {
            return ApiResponse::error('Medición no encontrada', 404);
        }

        // Eliminar medición
        $measurement->delete();

        return ApiResponse::success(null, 'Medición eliminada exitosamente');
    }

    /**
     * Obtener historial de actividad del activo
     * 
     * GET /api/companies/{companyId}/assets/{assetId}/activity-log
     * 
     * Query params:
     * - activity_type (opcional): filtrar por tipo de actividad
     * - start_date (opcional): fecha inicio (YYYY-MM-DD)
     * - end_date (opcional): fecha fin (YYYY-MM-DD)
     * - page (opcional): página actual
     * - per_page (opcional): registros por página (default: 15)
     */
    public function getActivityLog(Request $request, int $companyId, int $assetId): JsonResponse
    {
        // Verificar que el activo existe y pertenece a la empresa
        $asset = Asset::where('id', $assetId)
            ->where('company_id', $companyId)
            ->first();

        if (!$asset) {
            return ApiResponse::error('Activo no encontrado', 404);
        }

        // Construir query
        $query = $asset->activityLog()->with([
            'workOrder:id,code,title,priority,status',
            'workRequest:id,code,title,priority,status',
            'maintenancePlan:id,name',
            'performedBy:id,first_name,last_name,email'
        ]);

        // Filtro por tipo de actividad
        if ($request->has('activity_type')) {
            $query->where('activity_type', $request->activity_type);
        }

        // Filtro por rango de fechas
        if ($request->has('start_date')) {
            $query->whereDate('performed_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->whereDate('performed_at', '<=', $request->end_date);
        }

        // Paginación
        $perPage = $request->input('per_page', 15);
        $activities = $query->paginate($perPage);

        return ApiResponse::success($activities, 'Historial de actividad obtenido exitosamente');
    }

    /**
     * Obtener órdenes de trabajo de un activo
     * 
     * GET /api/companies/{companyId}/assets/{assetId}/work-orders
     * 
     * Query params:
     * - status (opcional): filtrar por estado
     * - priority (opcional): filtrar por prioridad
     * - work_order_type (opcional): filtrar por tipo de OT
     * - search (opcional): buscar por código o título
     * - page (opcional): página actual
     * - per_page (opcional): registros por página (default: 15)
     */
    public function getWorkOrders(Request $request, int $companyId, int $assetId): JsonResponse
    {
        // Verificar que el activo existe y pertenece a la empresa
        $asset = Asset::where('id', $assetId)
            ->where('company_id', $companyId)
            ->first();

        if (!$asset) {
            return ApiResponse::error('Activo no encontrado', 404);
        }

        // Construir query de órdenes de trabajo del activo
        $query = WorkOrder::where('asset_id', $assetId)
            ->where('company_id', $companyId)
            ->with([
                'assignedTo:id,first_name,last_name,email',
                'assignedBy:id,first_name,last_name,email',
                'workRequest:id,code,title',
                'completedBy:id,first_name,last_name,email',
                'createdBy:id,first_name,last_name,email'
            ]);

        // Filtro por búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por prioridad
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filtro por tipo de OT
        if ($request->filled('work_order_type')) {
            $query->where('work_order_type', $request->work_order_type);
        }

        // Ordenar por fecha de creación (más reciente primero)
        $query->orderBy('created_at', 'desc');

        // Paginación
        $perPage = $request->input('per_page', 15);
        $workOrders = $query->paginate($perPage);

        return ApiResponse::success($workOrders, 'Órdenes de trabajo del activo obtenidas exitosamente');
    }

    // =========================================================================
    // CARGUE MASIVO
    // =========================================================================

    /**
     * Importación masiva de activos desde archivo CSV.
     *
     * Columnas esperadas (encabezado, coma o punto y coma):
     *   codigo, equipo, descripcion, sistema, categoria, area, criticidad
     *
     * Resolución de relaciones por nombre (dentro de la empresa):
     *   sistema    → asset_systems.name
     *   categoria  → asset_categories.name
     *   area       → production_lines.name
     *   criticidad → asset_priorities.name
     *
     * El estado se asigna al primer AssetStatus activo encontrado ("Operativo").
     */
    public function bulkImport(Request $request, int $companyId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Estado por defecto: primer estado activo
        $defaultStatus = \App\Models\AssetStatus::where('is_active', true)->first();
        if (!$defaultStatus) {
            return ApiResponse::error('No hay estados de activo configurados', 422);
        }

        $rows = $this->parseCsvFile($request->file('file')->getRealPath());

        if (empty($rows)) {
            return ApiResponse::error('El archivo no contiene filas válidas', 422);
        }

        // Helper: normaliza cadena quitando tildes y pasando a minúsculas
        $norm = function ($str) {
            $str = mb_strtolower(trim($str), 'UTF-8');
            $from = ['á','é','í','ó','ú','ü','ñ','à','è','ì','ò','ù','â','ê','î','ô','û'];
            $to   = ['a','e','i','o','u','u','n','a','e','i','o','u','a','e','i','o','u'];
            return str_replace($from, $to, $str);
        };

        // Cache de relaciones para evitar N+1 en el loop
        $categories   = \App\Models\AssetCategory::where('is_active', true)->get()->keyBy(function ($r) use ($norm) { return $norm($r->name); });
        $priorities   = \App\Models\AssetPriority::where('is_active', true)->get()->keyBy(function ($r) use ($norm) { return $norm($r->name); });
        $systems      = \App\Models\AssetSystem::where('company_id', $companyId)->where('is_active', true)->get()->keyBy(function ($r) use ($norm) { return $norm($r->name); });
        $lines        = \App\Models\ProductionLine::where('company_id', $companyId)->where('is_active', true)->get()->keyBy(function ($r) use ($norm) { return $norm($r->name); });

        $created = [];
        $skipped = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2;

                $code        = trim($row['codigo'] ?? $row['code'] ?? $row[0] ?? '');
                $name        = trim($row['equipo'] ?? $row['nombre'] ?? $row['name'] ?? $row[1] ?? '');
                $description = trim($row['descripcion'] ?? $row['description'] ?? $row[2] ?? '');
                $systemName  = $norm($row['sistema']    ?? $row['system']    ?? $row[3] ?? '');
                $catName     = $norm($row['categoria']  ?? $row['category']  ?? $row[4] ?? '');
                $areaName    = $norm($row['area']       ?? $row['linea']     ?? $row[5] ?? '');
                $critName    = $norm($row['criticidad'] ?? $row['prioridad'] ?? $row[6] ?? '');

                if (empty($code)) {
                    $skipped[] = ['fila' => $rowNum, 'razon' => 'Código vacío'];
                    continue;
                }
                if (empty($name)) {
                    $skipped[] = ['fila' => $rowNum, 'razon' => 'Nombre de equipo vacío'];
                    continue;
                }
                if (empty($catName)) {
                    $skipped[] = ['fila' => $rowNum, 'razon' => 'Categoría vacía'];
                    continue;
                }
                if (empty($systemName)) {
                    $skipped[] = ['fila' => $rowNum, 'razon' => 'Sistema vacío'];
                    continue;
                }
                if (empty($areaName)) {
                    $skipped[] = ['fila' => $rowNum, 'razon' => 'Área vacía'];
                    continue;
                }
                if (empty($critName)) {
                    $skipped[] = ['fila' => $rowNum, 'razon' => 'Criticidad vacía'];
                    continue;
                }

                // Verificar duplicado de código en la empresa
                if (Asset::where('company_id', $companyId)->where('code', $code)->exists()) {
                    $skipped[] = ['fila' => $rowNum, 'razon' => "El código '{$code}' ya existe"];
                    continue;
                }

                // Resolver categoría (requerida)
                $category = $categories[$catName] ?? $categories->first(function ($c) use ($catName, $norm) { return strpos($norm($c->name), $catName) !== false; });
                if (!$category) {
                    $catDisplay = isset($row['categoria']) ? $row['categoria'] : '';
                    $skipped[] = ['fila' => $rowNum, 'razon' => "Categoría '{$catDisplay}' no encontrada"];
                    continue;
                }

                // Resolver sistema (requerido)
                $system = $systems[$systemName] ?? $systems->first(function ($s) use ($systemName, $norm) { return strpos($norm($s->name), $systemName) !== false; });
                if (!$system) {
                    $sysDisplay = isset($row['sistema']) ? $row['sistema'] : '';
                    $skipped[] = ['fila' => $rowNum, 'razon' => "Sistema '{$sysDisplay}' no encontrado"];
                    continue;
                }

                // Resolver área (requerida)
                $line = $lines[$areaName] ?? $lines->first(function ($l) use ($areaName, $norm) { return strpos($norm($l->name), $areaName) !== false; });
                if (!$line) {
                    $areaDisplay = isset($row['area']) ? $row['area'] : '';
                    $skipped[] = ['fila' => $rowNum, 'razon' => "Área '{$areaDisplay}' no encontrada"];
                    continue;
                }

                // Resolver criticidad (requerida)
                $prio = $priorities[$critName] ?? $priorities->first(function ($p) use ($critName, $norm) { return strpos($norm($p->name), $critName) !== false; });
                if (!$prio) {
                    $critDisplay = isset($row['criticidad']) ? $row['criticidad'] : '';
                    $skipped[] = ['fila' => $rowNum, 'razon' => "Criticidad '{$critDisplay}' no encontrada"];
                    continue;
                }

                // si no existe una planta enviada entonces se preselecciona la primera por defecto

                $asset = Asset::create([
                    'company_site_id'     => 1,
                    'company_id'          => $companyId,
                    'code'                => $code,
                    'name'                => $name,
                    'description'         => $description ?: null,
                    'category_id'         => $category->id,
                    'status_id'           => $defaultStatus->id,
                    'priority_id'         => $prio ? $prio->id : null,
                    'system_id'           => $system ? $system->id : null,
                    'production_line_id'  => $line ? $line->id : null,
                    'is_active'           => true,
                    'created_by'          => auth()->id(),
                ]);

                $created[] = [
                    'fila'       => $rowNum,
                    'codigo'     => $asset->code,
                    'equipo'     => $asset->name,
                    'categoria'  => $category->name,
                    'sistema'    => $system ? $system->name : null,
                    'area'       => $line ? $line->name : null,
                    'criticidad' => $prio ? $prio->name : null,
                ];
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error durante la importación: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success([
            'creados'          => count($created),
            'omitidos'         => count($skipped),
            'detalle_creados'  => $created,
            'detalle_omitidos' => $skipped,
        ], count($created) . ' activo(s) creado(s) correctamente.');
    }

    /**
     * Parsea un archivo CSV y devuelve array de filas asociativas.
     * Soporta separador coma (,) y punto y coma (;).
     */
    private function parseCsvFile(string $path): array
    {
        $rows    = [];
        $headers = null;
        $handle  = fopen($path, 'r');

        if ($handle === false) return [];

        // Detectar y descartar BOM UTF-8
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            if (count($line) === 1) {
                $line = str_getcsv($line[0], ';');
            }
            $line = array_map('trim', $line);

            if ($headers === null) {
                $headers = array_map('strtolower', $line);
                continue;
            }
            if (count(array_filter($line)) === 0) continue;

            $rows[] = array_combine(
                array_slice($headers, 0, count($line)),
                array_slice($line,    0, count($headers))
            ) + array_values($line);
        }

        fclose($handle);
        return $rows;
    }

    public function exportExcel(Request $request, int $companyId): StreamedResponse
    {
        $export = new AssetExport(
            companyId: $companyId,
            status:    $request->query('status'),
            isActive:  $request->has('is_active') ? filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN) : null,
        );

        return $export->download('activos_' . now()->format('Y-m-d') . '.xlsx');
    }
}
