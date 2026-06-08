<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssetMeterRequest;
use App\Http\Requests\UpdateAssetMeterRequest;
use App\Http\Requests\RecordMeterReadingRequest;
use App\Http\Resources\AssetMeterResource;
use App\Http\Resources\AssetMeterCollection;
use App\Http\Resources\AssetMeterReadingResource;
use App\Http\Resources\AssetMeterReadingCollection;
use App\Http\Responses\ApiResponse;
use App\Models\AssetMeter;
use App\Models\AssetMeterReading;
use App\Models\Company;
use App\Services\AssetMeterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AssetMeterController extends Controller
{
    protected $assetMeterService;

    public function __construct(AssetMeterService $assetMeterService)
    {
        $this->assetMeterService = $assetMeterService;
    }

    /**
     * Listar medidores con filtros y paginación
     * 
     * GET /api/asset-meters
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = AssetMeter::whereHas('asset', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->with(['asset.category', 'lastReadingUser']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('asset', function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        if ($request->filled('meter_type')) {
            $query->ofType($request->meter_type);
        }

        if ($request->boolean('only_active')) {
            $query->active();
        }

        if ($request->boolean('with_recent_readings')) {
            $days = $request->get('recent_days', 30);
            $query->withRecentReadings($days);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $meters = $query->paginate($perPage);

        return ApiResponse::success(
            new AssetMeterCollection($meters),
            'Medidores obtenidos exitosamente'
        );
    }

    /**
     * Mostrar medidor específico con historial de lecturas
     * 
     * GET /api/asset-meters/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $meter = AssetMeter::with([
            'asset.category',
            'lastReadingUser',
            'readings' => fn($q) => $q->latest()->limit(10),
            'readings.recordedBy',
            'maintenancePlans' => fn($q) => $q->active()
        ])
        ->whereHas('asset', fn($q) => $q->where('company_id', $companyId))
        ->find($id);

        if (!$meter) {
            return ApiResponse::notFound('Medidor no encontrado');
        }

        return ApiResponse::success(
            new AssetMeterResource($meter),
            'Medidor obtenido exitosamente'
        );
    }

    /**
     * Crear nuevo medidor
     * 
     * POST /api/asset-meters
     */
    public function store(StoreAssetMeterRequest $request): JsonResponse
    {
        try {
            $meter = $this->assetMeterService->createMeter(
                $request->asset_id,
                $request->validated()
            );

            Log::info('Medidor creado exitosamente', [
                'meter_id' => $meter->id,
                'asset_id' => $meter->asset_id,
                'meter_type' => $meter->meter_type,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::created(
                new AssetMeterResource($meter->load(['asset.category', 'lastReadingUser'])),
                'Medidor creado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al crear medidor: ' . $e->getMessage(), [
                'asset_id' => $request->asset_id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al crear medidor: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Actualizar medidor existente
     * 
     * PUT /api/asset-meters/{id}
     */
    public function update(UpdateAssetMeterRequest $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $meter = AssetMeter::whereHas('asset', fn($q) => $q->where('company_id', $companyId))->find($id);
        
        if (!$meter) {
            return ApiResponse::notFound('Medidor no encontrado');
        }

        try {
            $meter = $this->assetMeterService->updateMeter(
                $id,
                $request->validated()
            );

            Log::info('Medidor actualizado exitosamente', [
                'meter_id' => $meter->id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                new AssetMeterResource($meter->load(['asset.category', 'lastReadingUser'])),
                'Medidor actualizado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al actualizar medidor: ' . $e->getMessage(), [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al actualizar medidor: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Registrar nueva lectura en un medidor
     * 
     * POST /api/asset-meters/{id}/readings
     */
    public function recordReading(RecordMeterReadingRequest $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $meter = AssetMeter::whereHas('asset', fn($q) => $q->where('company_id', $companyId))->find($id);
        
        if (!$meter) {
            return ApiResponse::notFound('Medidor no encontrado');
        }

        try {
            $reading = $this->assetMeterService->recordReading(
                $id,
                $request->validated()
            );

            Log::info('Lectura registrada exitosamente', [
                'reading_id' => $reading->id,
                'meter_id' => $id,
                'reading_value' => $reading->reading_value,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::created(
                new AssetMeterReadingResource($reading->load([
                    'assetMeter',
                    'recordedBy',
                    'workOrder',
                    'maintenancePlan'
                ])),
                'Lectura registrada exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al registrar lectura: ' . $e->getMessage(), [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al registrar lectura: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Obtener historial de lecturas de un medidor
     * 
     * GET /api/asset-meters/{id}/readings
     */
    public function getReadings(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $meter = AssetMeter::whereHas('asset', fn($q) => $q->where('company_id', $companyId))->find($id);
        
        if (!$meter) {
            return ApiResponse::notFound('Medidor no encontrado');
        }

        $filters = $request->only([
            'start_date',
            'end_date',
            'reading_source'
        ]);

        $readings = $this->assetMeterService->getReadings($id, $filters);

        return ApiResponse::success(
            new AssetMeterReadingCollection($readings),
            'Lecturas obtenidas exitosamente'
        );
    }

    /**
     * Obtener estadísticas de un medidor
     * 
     * GET /api/asset-meters/{id}/statistics
     */
    public function getStatistics(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $meter = AssetMeter::whereHas('asset', fn($q) => $q->where('company_id', $companyId))->find($id);
        
        if (!$meter) {
            return ApiResponse::notFound('Medidor no encontrado');
        }

        try {
            $days = $request->get('days', 30);
            $statistics = $this->assetMeterService->getStatistics($id, $days);

            return ApiResponse::success(
                $statistics,
                'Estadísticas obtenidas exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage(), [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al obtener estadísticas: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Activar medidor
     * 
     * POST /api/asset-meters/{id}/activate
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $meter = AssetMeter::whereHas('asset', fn($q) => $q->where('company_id', $companyId))->find($id);
        
        if (!$meter) {
            return ApiResponse::notFound('Medidor no encontrado');
        }

        try {
            $meter = $this->assetMeterService->activateMeter($id);

            Log::info('Medidor activado', [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                new AssetMeterResource($meter->load(['asset.category'])),
                'Medidor activado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al activar medidor: ' . $e->getMessage(), [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al activar medidor: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Desactivar medidor
     * 
     * POST /api/asset-meters/{id}/deactivate
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $meter = AssetMeter::whereHas('asset', fn($q) => $q->where('company_id', $companyId))->find($id);
        
        if (!$meter) {
            return ApiResponse::notFound('Medidor no encontrado');
        }

        try {
            $meter = $this->assetMeterService->deactivateMeter($id);

            Log::info('Medidor desactivado', [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                new AssetMeterResource($meter->load(['asset.category'])),
                'Medidor desactivado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al desactivar medidor: ' . $e->getMessage(), [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al desactivar medidor: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Eliminar medidor (soft delete)
     * 
     * DELETE /api/asset-meters/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $meter = AssetMeter::whereHas('asset', fn($q) => $q->where('company_id', $companyId))->find($id);
        
        if (!$meter) {
            return ApiResponse::notFound('Medidor no encontrado');
        }

        try {
            $this->assetMeterService->deleteMeter($id);

            Log::info('Medidor eliminado', [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::success(
                null,
                'Medidor eliminado exitosamente'
            );

        } catch (\Exception $e) {
            Log::error('Error al eliminar medidor: ' . $e->getMessage(), [
                'meter_id' => $id,
                'user_id' => Auth::id()
            ]);

            return ApiResponse::error(
                'Error al eliminar medidor: ' . $e->getMessage(),
                500
            );
        }
    }
}
