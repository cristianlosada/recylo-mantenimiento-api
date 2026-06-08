<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Models\InventoryTransaction;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WarehouseController extends Controller
{
    /**
     * Listar almacenes
     * 
     * GET /api/warehouses
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = Warehouse::where('company_id', $companyId)
            ->with(['companySite', 'manager', 'creator']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('warehouse_type')) {
            $query->where('warehouse_type', $request->warehouse_type);
        }

        if ($request->boolean('only_active')) {
            $query->where('is_active', true);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $warehouses = $query->paginate($request->get('per_page', 15));

        return ApiResponse::success($warehouses, 'Almacenes obtenidos exitosamente');
    }

    /**
     * Mostrar almacén específico con stock
     * 
     * GET /api/warehouses/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $warehouse = Warehouse::with([
            'company',
            'companySite',
            'manager',
            'stock.material.category',
            'creator'
        ])
        ->where('company_id', $companyId)
        ->find($id);

        if (!$warehouse) {
            return ApiResponse::notFound('Almacén no encontrado');
        }

        // Calcular valor total del inventario
        $totalValue = $warehouse->stock->sum('total_value');
        $totalItems = $warehouse->stock->count();

        $response = $warehouse->toArray();
        $response['total_inventory_value'] = round($totalValue, 2);
        $response['total_items'] = $totalItems;

        return ApiResponse::success($response, 'Almacén obtenido exitosamente');
    }

    /**
     * Crear nuevo almacén
     * 
     * POST /api/warehouses
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId = Auth::id();

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'warehouse_type' => 'required|in:main,secondary,mobile,external',
            'company_site_id' => 'nullable|exists:company_sites,id',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'manager_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }

        // Verificar código único
        $exists = Warehouse::where('company_id', $companyId)
            ->where('code', $request->code)
            ->exists();

        if ($exists) {
            return ApiResponse::error('El código del almacén ya existe', 422);
        }

        try {
            $warehouse = Warehouse::create([
                'company_id' => $companyId,
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'warehouse_type' => $request->warehouse_type,
                'company_site_id' => $request->company_site_id,
                'address' => $request->address,
                'phone' => $request->phone,
                'manager_id' => $request->manager_id,
                'is_active' => true,
                'notes' => $request->notes,
                'created_by' => $userId,
            ]);

            $warehouse->load(['companySite', 'manager', 'creator']);

            return ApiResponse::created($warehouse, 'Almacén creado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear almacén: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar almacén
     * 
     * PUT /api/warehouses/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $warehouse = Warehouse::where('company_id', $companyId)->find($id);

        if (!$warehouse) {
            return ApiResponse::notFound('Almacén no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'warehouse_type' => 'sometimes|in:main,secondary,mobile,external',
            'company_site_id' => 'nullable|exists:company_sites,id',
            'manager_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }

        try {
            $warehouse->update($request->only([
                'code', 'name', 'description', 'warehouse_type',
                'company_site_id', 'address', 'phone', 'manager_id',
                'is_active', 'notes'
            ]));

            $warehouse->load(['companySite', 'manager']);

            return ApiResponse::success($warehouse, 'Almacén actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar almacén: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar almacén (soft delete)
     * 
     * DELETE /api/warehouses/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $warehouse = Warehouse::where('company_id', $companyId)->find($id);

        if (!$warehouse) {
            return ApiResponse::notFound('Almacén no encontrado');
        }

        // Verificar si tiene stock
        $hasStock = $warehouse->stock()->where('quantity', '>', 0)->exists();
        if ($hasStock) {
            return ApiResponse::error('No se puede eliminar un almacén con stock existente', 422);
        }

        try {
            $warehouse->delete();
            return ApiResponse::success(null, 'Almacén eliminado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar almacén: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener valorización del almacén
     * 
     * GET /api/warehouses/{id}/valuation
     */
    public function getValuation(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $warehouse = Warehouse::where('company_id', $companyId)->find($id);

        if (!$warehouse) {
            return ApiResponse::notFound('Almacén no encontrado');
        }

        $stock = WarehouseStock::where('warehouse_id', $id)
            ->with('material.category')
            ->get();

        $totalValue = $stock->sum('total_value');
        $totalItems = $stock->count();

        $byCategory = $stock->groupBy('material.category.name')->map(function ($items) {
            return [
                'items_count' => $items->count(),
                'total_value' => round($items->sum('total_value'), 2),
            ];
        });

        return ApiResponse::success([
            'warehouse' => $warehouse,
            'total_value' => round($totalValue, 2),
            'total_items' => $totalItems,
            'by_category' => $byCategory,
            'items' => $stock->map(function($item) {
                return [
                    'material_id' => $item->material_id,
                    'material_code' => $item->material->code,
                    'material_name' => $item->material->name,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->average_unit_cost ?? $item->material->unit_cost,
                    'total_value' => $item->total_value,
                    'location' => $item->location,
                ];
            }),
        ], 'Valorización obtenida exitosamente');
    }

    /**
     * Obtener estadísticas de almacenes
     * 
     * GET /api/warehouses/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $totalWarehouses = Warehouse::where('company_id', $companyId)->count();
        $activeWarehouses = Warehouse::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();

        // Valor total de todos los almacenes
        $totalValue = WarehouseStock::whereHas('warehouse', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->get()->sum('total_value');

        // Materiales únicos en stock
        $uniqueMaterials = WarehouseStock::whereHas('warehouse', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->distinct('material_id')->count('material_id');

        return ApiResponse::success([
            'total_warehouses' => $totalWarehouses,
            'active_warehouses' => $activeWarehouses,
            'total_inventory_value' => round($totalValue, 2),
            'unique_materials' => $uniqueMaterials,
        ], 'Estadísticas obtenidas exitosamente');
    }
}
