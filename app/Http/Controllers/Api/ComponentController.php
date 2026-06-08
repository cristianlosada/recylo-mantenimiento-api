<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Component;
use App\Models\ComponentType;
use App\Models\ComponentWarehouseStock;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ComponentController extends Controller
{
    /**
     * GET /components
     * Listado con filtros y paginación.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso para ver componentes', 403);
        }

        $query = Component::forCompany($companyId)
            ->with(['type', 'stock.warehouse', 'createdBy']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('code', 'like', "%{$s}%")
                  ->orWhere('name', 'like', "%{$s}%")
                  ->orWhere('reference', 'like', "%{$s}%")
                  ->orWhere('brand', 'like', "%{$s}%");
            });
        }

        if ($request->filled('component_type_id')) {
            $query->where('component_type_id', $request->component_type_id);
        }

        if ($request->boolean('only_active')) {
            $query->active();
        }

        if ($request->boolean('only_critical')) {
            $query->critical();
        }

        if ($request->boolean('only_low_stock')) {
            $query->lowStock();
        }

        $sortBy    = $request->get('sort_by', 'code');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage    = $request->get('per_page', 15);
        $components = $request->boolean('all')
            ? $query->get()
            : $query->paginate($perPage);

        return ApiResponse::success($components, 'Componentes obtenidos');
    }

    /**
     * GET /components/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso', 403);
        }

        $total       = Component::forCompany($companyId)->count();
        $active      = Component::forCompany($companyId)->active()->count();
        $critical    = Component::forCompany($companyId)->critical()->count();

        $totalStock = DB::table('component_warehouse_stock')
            ->join('components', 'components.id', '=', 'component_warehouse_stock.component_id')
            ->where('components.company_id', $companyId)
            ->sum('component_warehouse_stock.quantity');

        $totalValue = DB::table('component_warehouse_stock')
            ->join('components', 'components.id', '=', 'component_warehouse_stock.component_id')
            ->where('components.company_id', $companyId)
            ->selectRaw('SUM(component_warehouse_stock.quantity * COALESCE(component_warehouse_stock.average_unit_cost, components.unit_cost, 0)) as total_value')
            ->value('total_value') ?? 0;

        $lowStock = Component::forCompany($companyId)
            ->where('minimum_stock', '>', 0)
            ->whereHas('stock', function ($q) {
                $q->whereColumn('quantity', '<', 'components.minimum_stock');
            })
            ->count();

        return ApiResponse::success([
            'total_components'  => $total,
            'active_components' => $active,
            'critical_count'    => $critical,
            'low_stock_count'   => $lowStock,
            'total_stock_units' => (float) $totalStock,
            'total_stock_value' => (float) $totalValue,
        ], 'Estadísticas de componentes');
    }

    /**
     * GET /components/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso', 403);
        }

        $component = Component::forCompany($companyId)
            ->with(['type', 'stock.warehouse', 'assetComponents.asset', 'createdBy'])
            ->find($id);

        if (!$component) {
            return ApiResponse::notFound('Componente no encontrado');
        }

        return ApiResponse::success($component, 'Componente obtenido');
    }

    /**
     * POST /components
     * Crea componente. Si se envía new_type, crea también el tipo.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $user      = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_CREATE_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso para crear componentes', 403);
        }

        $rules = [
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string',
            'reference'             => 'nullable|string|max:100',
            'brand'                 => 'nullable|string|max:100',
            'unit_of_measure'       => 'required|string|max:50',
            'unit_cost'             => 'nullable|numeric|min:0',
            'minimum_stock'         => 'nullable|numeric|min:0',
            'maximum_stock'         => 'nullable|numeric|min:0',
            'reorder_point'         => 'nullable|numeric|min:0',
            'is_active'             => 'boolean',
            'is_critical'           => 'boolean',
            'notes'                 => 'nullable|string',

            // Tipo existente O nuevo tipo
            'component_type_id'     => 'required_without:new_type|nullable|integer|exists:component_types,id',
            'new_type'              => 'required_without:component_type_id|nullable|array',
            'new_type.code_prefix'  => 'required_with:new_type|string|max:10',
            'new_type.name'         => 'required_with:new_type|string|max:100',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        DB::beginTransaction();
        try {
            // Crear tipo inline si se solicitó
            $typeId = $request->component_type_id;

            if ($request->filled('new_type')) {
                $prefix = strtoupper(trim($request->new_type['code_prefix']));
                if (ComponentType::forCompany($companyId)->where('code_prefix', $prefix)->exists()) {
                    return ApiResponse::error("Ya existe un tipo con el prefijo '{$prefix}'", 422);
                }
                $newType = ComponentType::create([
                    'company_id'  => $companyId,
                    'code_prefix' => $prefix,
                    'name'        => $request->new_type['name'],
                    'description' => $request->new_type['description'] ?? null,
                    'is_active'   => true,
                    'created_by'  => $user->id,
                ]);
                $typeId = $newType->id;
            }

            $type = ComponentType::find($typeId);
            if (!$type || $type->company_id != $companyId) {
                DB::rollBack();
                return ApiResponse::error('Tipo de componente no válido para esta empresa', 422);
            }

            $code = $type->generateNextComponentCode();

            $component = Component::create([
                'company_id'         => $companyId,
                'component_type_id'  => $typeId,
                'code'               => $code,
                'name'               => $request->name,
                'description'        => $request->description,
                'reference'          => $request->reference,
                'brand'              => $request->brand,
                'unit_of_measure'    => $request->unit_of_measure,
                'unit_cost'          => $request->unit_cost ?? 0,
                'minimum_stock'      => $request->minimum_stock ?? 0,
                'maximum_stock'      => $request->maximum_stock,
                'reorder_point'      => $request->reorder_point,
                'is_active'          => $request->boolean('is_active', true),
                'is_critical'        => $request->boolean('is_critical', false),
                'notes'              => $request->notes,
                'created_by'         => $user->id,
            ]);

            DB::commit();

            $component->load(['type', 'stock']);

            return ApiResponse::created($component, 'Componente creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear componente', ['error' => $e->getMessage()]);
            return ApiResponse::error('Error al crear componente: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /components/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $user      = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_UPDATE_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso para actualizar componentes', 403);
        }

        $component = Component::forCompany($companyId)->find($id);
        if (!$component) {
            return ApiResponse::notFound('Componente no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'component_type_id' => 'nullable|integer',
            'new_type'          => 'nullable|array',
            'new_type.code_prefix' => 'required_with:new_type|string|max:10',
            'new_type.name'        => 'required_with:new_type|string|max:100',
            'name'             => 'sometimes|string|max:255',
            'description'      => 'nullable|string',
            'reference'        => 'nullable|string|max:100',
            'brand'            => 'nullable|string|max:100',
            'unit_of_measure'  => 'sometimes|string|max:50',
            'unit_cost'        => 'nullable|numeric|min:0',
            'minimum_stock'    => 'nullable|numeric|min:0',
            'maximum_stock'    => 'nullable|numeric|min:0',
            'reorder_point'    => 'nullable|numeric|min:0',
            'is_active'        => 'boolean',
            'is_critical'      => 'boolean',
            'notes'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        // Resolver tipo: nuevo inline o existente
        if ($request->filled('new_type')) {
            $newTypeData = $request->input('new_type');
            $prefix = strtoupper(trim($newTypeData['code_prefix']));

            $exists = ComponentType::where('company_id', $companyId)
                ->where('code_prefix', $prefix)->exists();
            if ($exists) {
                return ApiResponse::error("Ya existe un tipo con el prefijo '{$prefix}'", 422);
            }

            $componentType = ComponentType::create([
                'company_id'  => $companyId,
                'code_prefix' => $prefix,
                'name'        => $newTypeData['name'],
                'is_active'   => true,
                'created_by'  => Auth::id(),
            ]);
            $component->component_type_id = $componentType->id;
        } elseif ($request->filled('component_type_id')) {
            $type = ComponentType::where('company_id', $companyId)->find($request->component_type_id);
            if (!$type) {
                return ApiResponse::notFound('Tipo de componente no encontrado');
            }
            $component->component_type_id = $type->id;
        }

        $fields = ['name','description','reference','brand','unit_of_measure','unit_cost','minimum_stock','maximum_stock','reorder_point','notes'];
        foreach ($fields as $f) {
            if ($request->has($f)) $component->$f = $request->$f;
        }
        if ($request->has('is_active'))   $component->is_active   = $request->boolean('is_active');
        if ($request->has('is_critical')) $component->is_critical = $request->boolean('is_critical');

        $component->save();
        $component->load(['type', 'stock.warehouse']);

        return ApiResponse::success($component, 'Componente actualizado');
    }

    /**
     * DELETE /components/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_DELETE_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso para eliminar componentes', 403);
        }

        $component = Component::forCompany($companyId)->withCount('assetComponents')->find($id);
        if (!$component) {
            return ApiResponse::notFound('Componente no encontrado');
        }

        if ($component->asset_components_count > 0) {
            return ApiResponse::error('No se puede eliminar: el componente está asociado a uno o más activos', 422);
        }

        $totalStock = ComponentWarehouseStock::where('component_id', $id)->sum('quantity');
        if ($totalStock > 0) {
            return ApiResponse::error('No se puede eliminar: el componente tiene stock en almacén', 422);
        }

        $component->delete();

        return ApiResponse::success(null, 'Componente eliminado');
    }

    /**
     * GET /components/{id}/stock
     * Stock por almacén.
     */
    public function getStock(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        if (!\App\Helpers\PermissionHelper::hasPermission(Auth::user(), 'INVENTORY_VIEW_STOCK', $companyId)) {
            return ApiResponse::error('Sin permiso para ver stock', 403);
        }

        $component = Component::forCompany($companyId)->find($id);
        if (!$component) {
            return ApiResponse::notFound('Componente no encontrado');
        }

        $stock = ComponentWarehouseStock::where('component_id', $id)
            ->with('warehouse:id,code,name')
            ->get();

        return ApiResponse::success([
            'component' => ['id' => $component->id, 'code' => $component->code, 'name' => $component->name],
            'stock'     => $stock,
            'total'     => $stock->sum('quantity'),
        ], 'Stock obtenido');
    }

    /**
     * POST /components/{id}/adjust-stock
     * Ajuste manual de stock en un almacén.
     */
    public function adjustStock(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $user      = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_ADJUST_STOCK', $companyId)) {
            return ApiResponse::error('Sin permiso para ajustar stock', 403);
        }

        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'required|integer',
            'quantity'     => 'required|numeric',   // positivo=entrada, negativo=salida
            'unit_cost'    => 'nullable|numeric|min:0',
            'reason'       => 'nullable|string|max:500',
            'location'     => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $component = Component::forCompany($companyId)->find($id);
        if (!$component) {
            return ApiResponse::notFound('Componente no encontrado');
        }

        $warehouse = Warehouse::where('company_id', $companyId)->find($request->warehouse_id);
        if (!$warehouse) {
            return ApiResponse::notFound('Almacén no encontrado');
        }

        DB::beginTransaction();
        try {
            $stockRow = ComponentWarehouseStock::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'component_id' => $id],
                ['quantity' => 0, 'average_unit_cost' => $request->unit_cost]
            );

            if ($request->filled('location')) {
                $stockRow->location = $request->location;
            }

            $newQty = $stockRow->quantity + $request->quantity;
            if ($newQty < 0) {
                DB::rollBack();
                return ApiResponse::error('Stock insuficiente para realizar la salida', 422);
            }

            $stockRow->applyMovement((float) $request->quantity, $request->unit_cost ? (float) $request->unit_cost : null);

            DB::commit();

            return ApiResponse::success([
                'stock_after' => $stockRow->quantity,
                'warehouse'   => $warehouse->name,
            ], 'Stock ajustado correctamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al ajustar stock: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /components/{id}/purchase
     * Registrar compra (entrada) de componentes.
     */
    public function registerPurchase(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $user      = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_CREATE_PURCHASE', $companyId)) {
            return ApiResponse::error('Sin permiso para registrar compras', 403);
        }

        $validator = Validator::make($request->all(), [
            'warehouse_id'    => 'required|integer',
            'quantity'        => 'required|numeric|min:0.001',
            'unit_cost'       => 'required|numeric|min:0',
            'reference_doc'   => 'nullable|string|max:100',
            'notes'           => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $component = Component::forCompany($companyId)->find($id);
        if (!$component) {
            return ApiResponse::notFound('Componente no encontrado');
        }

        $warehouse = Warehouse::where('company_id', $companyId)->find($request->warehouse_id);
        if (!$warehouse) {
            return ApiResponse::notFound('Almacén no encontrado');
        }

        DB::beginTransaction();
        try {
            $stockRow = ComponentWarehouseStock::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'component_id' => $id],
                ['quantity' => 0]
            );

            $stockRow->applyMovement((float) $request->quantity, (float) $request->unit_cost);

            // Actualizar costo unitario del componente al último precio de compra
            $component->unit_cost = $request->unit_cost;
            $component->save();

            DB::commit();

            return ApiResponse::created([
                'stock_after' => $stockRow->quantity,
                'warehouse'   => $warehouse->name,
            ], 'Compra registrada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al registrar compra: ' . $e->getMessage(), 500);
        }
    }
}
