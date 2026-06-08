<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\CompanySite;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CompanySiteController extends Controller
{
    /**
     * Listar sedes de una empresa con filtros y paginación
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
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'is_active' => 'boolean',
            'is_headquarters' => 'boolean',
            'site_type_id' => 'integer|exists:site_types,id',
            'sort_by' => 'string|in:name,created_at,is_headquarters',
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
        $query = CompanySite::forCompany($companyId)
            ->with(['siteType', 'municipality.departmentGeo']);

        // Aplicar filtros opcionales
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_headquarters')) {
            $query->where('is_headquarters', $request->boolean('is_headquarters'));
        }

        if ($request->filled('site_type_id')) {
            $query->where('site_type_id', $request->site_type_id);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $sites = $query->paginate($perPage);

        // Transformar datos para respuesta
        $transformedSites = $sites->getCollection()->map(function ($site) {
            return [
                'id' => $site->id,
                'name' => $site->name,
                'site_type' => $site->siteType ? [
                    'id' => $site->siteType->id,
                    'code' => $site->siteType->code,
                    'name' => $site->siteType->name
                ] : null,
                'municipality' => $site->municipality ? [
                    'id' => $site->municipality->id,
                    'name' => $site->municipality->name,
                    'department' => $site->municipality->departmentGeo ? [
                        'id' => $site->municipality->departmentGeo->id,
                        'name' => $site->municipality->departmentGeo->name
                    ] : null
                ] : null,
                'address' => [
                    'line_1' => $site->address_line_1,
                    'line_2' => $site->address_line_2,
                    'postal_code' => $site->postal_code,
                    'full_address' => $site->full_address
                ],
                'coordinates' => [
                    'latitude' => $site->latitude,
                    'longitude' => $site->longitude,
                    'has_coordinates' => $site->has_coordinates
                ],
                'is_headquarters' => $site->is_headquarters,
                'is_active' => $site->is_active,
                'created_at' => $site->created_at,
                'updated_at' => $site->updated_at
            ];
        });

        return ApiResponse::paginated(
            $transformedSites,
            [
                'current_page' => $sites->currentPage(),
                'last_page' => $sites->lastPage(),
                'per_page' => $sites->perPage(),
                'total' => $sites->total(),
                'from' => $sites->firstItem(),
                'to' => $sites->lastItem()
            ],
            'Sedes recuperadas exitosamente'
        );
    }

    /**
     * Mostrar una sede específica
     * 
     * @param int $companyId
     * @param int $siteId
     * @return JsonResponse
     */
    public function show(int $companyId, int $siteId): JsonResponse
    {
        // Verificar que la empresa exista
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Buscar sede con todas sus relaciones
        $site = CompanySite::with([
            'siteType',
            'municipality.departmentGeo.country'
        ])
        ->where('company_id', $companyId)
        ->find($siteId);

        if (!$site) {
            return ApiResponse::notFound('Sede no encontrada');
        }

        // Formatear respuesta detallada
        $data = [
            'id' => $site->id,
            'company_id' => $site->company_id,
            'name' => $site->name,
            'display_name' => $site->display_name,
            'site_type' => $site->siteType ? [
                'id' => $site->siteType->id,
                'code' => $site->siteType->code,
                'name' => $site->siteType->name,
                'description' => $site->siteType->description
            ] : null,
            'municipality' => $site->municipality ? [
                'id' => $site->municipality->id,
                'name' => $site->municipality->name,
                'department' => $site->municipality->departmentGeo ? [
                    'id' => $site->municipality->departmentGeo->id,
                    'name' => $site->municipality->departmentGeo->name,
                    'country' => $site->municipality->departmentGeo->country ? [
                        'id' => $site->municipality->departmentGeo->country->id,
                        'name' => $site->municipality->departmentGeo->country->name,
                        'code' => $site->municipality->departmentGeo->country->code
                    ] : null
                ] : null
            ] : null,
            'address' => [
                'line_1' => $site->address_line_1,
                'line_2' => $site->address_line_2,
                'postal_code' => $site->postal_code,
                'full_address' => $site->full_address
            ],
            'coordinates' => [
                'latitude' => $site->latitude,
                'longitude' => $site->longitude,
                'has_coordinates' => $site->has_coordinates
            ],
            'is_headquarters' => $site->is_headquarters,
            'is_active' => $site->is_active,
            'created_at' => $site->created_at,
            'updated_at' => $site->updated_at
        ];

        return ApiResponse::success($data, 'Sede recuperada exitosamente');
    }

    /**
     * Crear nueva sede
     * 
     * @param Request $request
     * @param int $companyId
     * @return JsonResponse
     */
    public function store(Request $request, int $companyId): JsonResponse
    {
        // Verificar que la empresa exista
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Validar datos de entrada
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:190',
            'site_type_id' => 'required|integer|exists:site_types,id',
            'municipality_id' => 'required|integer|exists:municipalities,id',
            'address_line_1' => 'required|string|max:190',
            'address_line_2' => 'nullable|string|max:190',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_headquarters' => 'boolean',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Lógica de negocio: Solo puede haber una sede principal por empresa
            if ($request->boolean('is_headquarters')) {
                CompanySite::where('company_id', $companyId)
                    ->where('is_headquarters', true)
                    ->update(['is_headquarters' => false]);
            }

            // Preparar datos para creación
            $siteData = $request->only([
                'name', 'site_type_id', 'municipality_id',
                'address_line_1', 'address_line_2', 'postal_code',
                'latitude', 'longitude', 'is_headquarters', 'is_active'
            ]);
            $siteData['company_id'] = $companyId;

            // Valores por defecto si no se envían
            $siteData['is_headquarters'] = $siteData['is_headquarters'] ?? false;
            $siteData['is_active'] = $siteData['is_active'] ?? true;

            // Crear sede
            $site = CompanySite::create($siteData);

            // Cargar relaciones para respuesta
            $site->load(['siteType', 'municipality.departmentGeo']);

            DB::commit();

            // Formatear respuesta
            $response = [
                'id' => $site->id,
                'name' => $site->name,
                'site_type' => $site->siteType ? [
                    'id' => $site->siteType->id,
                    'code' => $site->siteType->code,
                    'name' => $site->siteType->name
                ] : null,
                'municipality' => $site->municipality ? [
                    'id' => $site->municipality->id,
                    'name' => $site->municipality->name,
                    'department' => $site->municipality->departmentGeo ? [
                        'id' => $site->municipality->departmentGeo->id,
                        'name' => $site->municipality->departmentGeo->name
                    ] : null
                ] : null,
                'address' => [
                    'line_1' => $site->address_line_1,
                    'line_2' => $site->address_line_2,
                    'postal_code' => $site->postal_code,
                    'full_address' => $site->full_address
                ],
                'coordinates' => [
                    'latitude' => $site->latitude,
                    'longitude' => $site->longitude
                ],
                'is_headquarters' => $site->is_headquarters,
                'is_active' => $site->is_active,
                'created_at' => $site->created_at
            ];

            return ApiResponse::success($response, 'Sede creada correctamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al crear la sede: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Actualizar sede existente
     * 
     * @param Request $request
     * @param int $companyId
     * @param int $siteId
     * @return JsonResponse
     */
    public function update(Request $request, int $companyId, int $siteId): JsonResponse
    {
        // Verificar que la empresa exista
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Buscar sede
        $site = CompanySite::where('company_id', $companyId)->find($siteId);
        if (!$site) {
            return ApiResponse::notFound('Sede no encontrada');
        }

        // Validación (todos los campos son opcionales en actualización)
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:190',
            'site_type_id' => 'integer|exists:site_types,id',
            'municipality_id' => 'integer|exists:municipalities,id',
            'address_line_1' => 'string|max:190',
            'address_line_2' => 'nullable|string|max:190',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_headquarters' => 'boolean',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Si se marca como sede principal, desmarcar las demás
            if ($request->has('is_headquarters') && $request->boolean('is_headquarters')) {
                CompanySite::where('company_id', $companyId)
                    ->where('id', '!=', $siteId)
                    ->where('is_headquarters', true)
                    ->update(['is_headquarters' => false]);
            }

            // Actualizar solo los campos enviados
            $updateData = array_filter($request->only([
                'name', 'site_type_id', 'municipality_id',
                'address_line_1', 'address_line_2', 'postal_code',
                'latitude', 'longitude', 'is_headquarters', 'is_active'
            ]), function ($value) {
                return !is_null($value);
            });

            $site->update($updateData);

            DB::commit();

            // Recargar relaciones
            $site->load(['siteType', 'municipality.departmentGeo']);

            // Formatear respuesta
            $response = [
                'id' => $site->id,
                'name' => $site->name,
                'site_type' => $site->siteType ? [
                    'id' => $site->siteType->id,
                    'code' => $site->siteType->code,
                    'name' => $site->siteType->name
                ] : null,
                'municipality' => $site->municipality ? [
                    'id' => $site->municipality->id,
                    'name' => $site->municipality->name,
                    'department' => $site->municipality->departmentGeo ? [
                        'id' => $site->municipality->departmentGeo->id,
                        'name' => $site->municipality->departmentGeo->name
                    ] : null
                ] : null,
                'address' => [
                    'line_1' => $site->address_line_1,
                    'line_2' => $site->address_line_2,
                    'postal_code' => $site->postal_code,
                    'full_address' => $site->full_address
                ],
                'coordinates' => [
                    'latitude' => $site->latitude,
                    'longitude' => $site->longitude
                ],
                'is_headquarters' => $site->is_headquarters,
                'is_active' => $site->is_active,
                'updated_at' => $site->updated_at
            ];

            return ApiResponse::success($response, 'Sede actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al actualizar la sede: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Eliminar sede (soft delete)
     * 
     * @param int $companyId
     * @param int $siteId
     * @return JsonResponse
     */
    public function destroy(int $companyId, int $siteId): JsonResponse
    {
        // Verificar que la empresa exista
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Buscar sede
        $site = CompanySite::where('company_id', $companyId)->find($siteId);
        if (!$site) {
            return ApiResponse::notFound('Sede no encontrada');
        }

        // Validación de negocio: No se puede eliminar la sede principal
        if ($site->is_headquarters) {
            return ApiResponse::error(
                'No se puede eliminar la sede principal. Primero asigne otra sede como principal.',
                400
            );
        }

        try {
            // Soft delete
            $site->delete();

            return ApiResponse::success(null, 'Sede eliminada exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al eliminar la sede: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Establecer una sede como principal (headquarters)
     * 
     * @param int $companyId
     * @param int $siteId
     * @return JsonResponse
     */
    public function setHeadquarters(int $companyId, int $siteId): JsonResponse
    {
        // Verificar que la empresa exista
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Buscar sede
        $site = CompanySite::where('company_id', $companyId)->find($siteId);
        if (!$site) {
            return ApiResponse::notFound('Sede no encontrada');
        }

        // Verificar que la sede esté activa
        if (!$site->is_active) {
            return ApiResponse::error(
                'No se puede establecer una sede inactiva como principal',
                400
            );
        }

        try {
            DB::beginTransaction();

            // Desmarcar todas las sedes como principales
            CompanySite::where('company_id', $companyId)
                ->where('is_headquarters', true)
                ->update(['is_headquarters' => false]);

            // Marcar esta sede como principal
            $site->update(['is_headquarters' => true]);

            DB::commit();

            $site->load(['siteType', 'municipality.departmentGeo']);

            $response = [
                'id' => $site->id,
                'name' => $site->name,
                'site_type' => $site->siteType ? [
                    'id' => $site->siteType->id,
                    'code' => $site->siteType->code,
                    'name' => $site->siteType->name
                ] : null,
                'municipality' => $site->municipality ? [
                    'id' => $site->municipality->id,
                    'name' => $site->municipality->name,
                    'department' => $site->municipality->departmentGeo ? [
                        'id' => $site->municipality->departmentGeo->id,
                        'name' => $site->municipality->departmentGeo->name
                    ] : null
                ] : null,
                'is_headquarters' => true,
                'is_active' => $site->is_active
            ];

            return ApiResponse::success($response, 'Sede establecida como principal exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al establecer sede principal: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Activar/desactivar una sede
     * 
     * @param int $companyId
     * @param int $siteId
     * @return JsonResponse
     */
    public function toggleActive(int $companyId, int $siteId): JsonResponse
    {
        // Verificar que la empresa exista
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Buscar sede
        $site = CompanySite::where('company_id', $companyId)->find($siteId);
        if (!$site) {
            return ApiResponse::notFound('Sede no encontrada');
        }

        // No se puede desactivar la sede principal
        if ($site->is_headquarters && $site->is_active) {
            return ApiResponse::error(
                'No se puede desactivar la sede principal. Primero asigne otra sede como principal.',
                400
            );
        }

        try {
            $site->update(['is_active' => !$site->is_active]);

            $response = [
                'id' => $site->id,
                'name' => $site->name,
                'is_active' => $site->is_active
            ];

            $message = $site->is_active 
                ? 'Sede activada exitosamente' 
                : 'Sede desactivada exitosamente';

            return ApiResponse::success($response, $message);

        } catch (\Exception $e) {
            return ApiResponse::error(
                'Error al cambiar estado de la sede: ' . $e->getMessage(),
                500
            );
        }
    }
}
