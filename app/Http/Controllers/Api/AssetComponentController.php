<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Asset;
use App\Models\AssetComponent;
use App\Models\AssetComponentConsumption;
use App\Models\Component;
use App\Models\ComponentWarehouseStock;
use App\Models\CompanySetting;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AssetComponentController extends Controller
{
    /**
     * GET /assets/{assetId}/components
     * Lista de componentes de un activo.
     */
    public function index(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $user = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso para ver componentes', 403);
        }

        $asset = Asset::where('company_id', $companyId)->find($assetId);
        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $assetComponents = AssetComponent::forAsset($assetId)
            ->with(['component.type', 'component.stock.warehouse', 'createdBy'])
            ->get()
            ->map(function ($ac) {
                return [
                    'id'                 => $ac->id,
                    'specified_quantity' => $ac->specified_quantity,
                    'installed_quantity' => $ac->installed_quantity,
                    'status'             => $ac->status,
                    'notes'              => $ac->notes,
                    'created_at'         => $ac->created_at,
                    'component'          => $ac->component ? [
                        'id'              => $ac->component->id,
                        'code'            => $ac->component->code,
                        'name'            => $ac->component->name,
                        'reference'       => $ac->component->reference,
                        'brand'           => $ac->component->brand,
                        'unit_of_measure' => $ac->component->unit_of_measure,
                        'unit_cost'       => $ac->component->unit_cost,
                        'minimum_stock'   => $ac->component->minimum_stock,
                        'is_critical'     => $ac->component->is_critical,
                        'type'            => $ac->component->type ? [
                            'id'          => $ac->component->type->id,
                            'name'        => $ac->component->type->name,
                            'code_prefix' => $ac->component->type->code_prefix,
                        ] : null,
                        'total_stock'     => $ac->component->total_stock,
                        'stock'           => $ac->component->stock->map(fn($s) => [
                            'warehouse_id'   => $s->warehouse_id,
                            'warehouse_name' => $s->warehouse->name ?? '-',
                            'quantity'       => $s->quantity,
                        ]),
                    ] : null,
                ];
            });

        return ApiResponse::success($assetComponents, 'Componentes del activo obtenidos');
    }

    /**
     * POST /assets/{assetId}/components
     * Asociar componente a un activo.
     */
    public function store(Request $request, int $companyId, int $assetId): JsonResponse
    {
        $user = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso para gestionar componentes de activos', 403);
        }

        $validator = Validator::make($request->all(), [
            'component_id'       => 'required|integer',
            'specified_quantity' => 'required|numeric|min:0.001',
            'installed_quantity' => 'nullable|numeric|min:0',
            'notes'              => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $asset = Asset::where('company_id', $companyId)->find($assetId);
        if (!$asset) {
            return ApiResponse::notFound('Activo no encontrado');
        }

        $component = Component::forCompany($companyId)->find($request->component_id);
        if (!$component) {
            return ApiResponse::notFound('Componente no encontrado');
        }

        if (AssetComponent::where('asset_id', $assetId)->where('component_id', $component->id)->whereNull('deleted_at')->exists()) {
            return ApiResponse::error('Este componente ya está asociado al activo', 422);
        }

        $ac = new AssetComponent([
            'asset_id'           => $assetId,
            'component_id'       => $component->id,
            'specified_quantity' => $request->specified_quantity,
            'installed_quantity' => $request->installed_quantity ?? 0,
            'notes'              => $request->notes,
            'created_by'         => $user->id,
        ]);
        $ac->recalculateStatus();
        $ac->save();

        $ac->load(['component.type']);

        return ApiResponse::created($ac, 'Componente asociado al activo');
    }

    /**
     * PUT /assets/{assetId}/components/{id}
     * Actualizar cantidades (especificada e instalada) de un componente en el activo.
     */
    public function update(Request $request, int $companyId, int $assetId, int $id): JsonResponse
    {
        $user = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso', 403);
        }

        $validator = Validator::make($request->all(), [
            'specified_quantity' => 'sometimes|numeric|min:0.001',
            'installed_quantity' => 'sometimes|numeric|min:0',
            'notes'              => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $ac = AssetComponent::where('asset_id', $assetId)->find($id);
        if (!$ac) {
            return ApiResponse::notFound('Relación componente-activo no encontrada');
        }

        if ($request->has('specified_quantity')) $ac->specified_quantity = $request->specified_quantity;
        if ($request->has('installed_quantity')) $ac->installed_quantity = $request->installed_quantity;
        if ($request->has('notes'))              $ac->notes              = $request->notes;

        if ((float) $ac->installed_quantity > (float) $ac->specified_quantity) {
            return ApiResponse::error(
                'La cantidad instalada no puede superar la cantidad especificada.',
                422
            );
        }

        $ac->recalculateStatus();
        $ac->save();

        return ApiResponse::success($ac, 'Componente actualizado');
    }

    /**
     * DELETE /assets/{assetId}/components/{id}
     * Desasociar componente del activo.
     */
    public function destroy(Request $request, int $companyId, int $assetId, int $id): JsonResponse
    {
        $user = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso', 403);
        }

        $ac = AssetComponent::where('asset_id', $assetId)->find($id);
        if (!$ac) {
            return ApiResponse::notFound('Relación no encontrada');
        }

        $ac->delete();

        return ApiResponse::success(null, 'Componente desasociado del activo');
    }

    /**
     * POST /assets/{assetId}/components/{id}/consume
     *
     * movement_type:
     *   installation — nuevo componente instalado: -stock, +installed_qty
     *   replacement  — reemplazo en mantenimiento: -stock, installed_qty sin cambio
     *   removal      — retiro sin reposición:       +stock (devuelve), -installed_qty
     */
    public function consume(Request $request, int $companyId, int $assetId, int $id): JsonResponse
    {
        $user = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso para registrar movimientos de componentes', 403);
        }

        $movementType = $request->input('movement_type', 'replacement');
        $needsWarehouse = in_array($movementType, ['installation', 'replacement', 'removal']);

        $validator = Validator::make($request->all(), [
            'movement_type' => 'required|in:installation,replacement,removal',
            'warehouse_id'  => 'required|integer',
            'quantity'      => 'required|numeric|min:0.001',
            'work_order_id' => 'nullable|integer',
            'notes'         => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $ac = AssetComponent::where('asset_id', $assetId)->with('component')->find($id);
        if (!$ac) {
            return ApiResponse::notFound('Relación componente-activo no encontrada');
        }

        $warehouse = Warehouse::where('company_id', $companyId)->find($request->warehouse_id);
        if (!$warehouse) {
            return ApiResponse::notFound('Almacén no encontrado');
        }

        $qty = (float) $request->quantity;

        $installed = (float) $ac->installed_quantity;
        $specified = (float) $ac->specified_quantity;

        if ($movementType === 'removal' && $qty > $installed) {
            return ApiResponse::error(
                "No puedes retirar {$qty} unidades. Solo hay {$installed} instaladas.",
                422
            );
        }

        if ($movementType === 'replacement' && $qty > $installed) {
            return ApiResponse::error(
                "No puedes reemplazar {$qty} unidades. Solo hay {$installed} instaladas.",
                422
            );
        }

        if ($movementType === 'installation') {
            $available = max(0, $specified - $installed);
            if ($qty > $available) {
                return ApiResponse::error(
                    "Solo puedes instalar {$available} más (especificada: {$specified}, instalada: {$installed}).",
                    422
                );
            }
        }

        // Para installation y replacement validamos stock disponible
        if (in_array($movementType, ['installation', 'replacement'])) {
            $validateStock = CompanySetting::get((int) $companyId, 'validate_component_stock', true);
            $stockRow = ComponentWarehouseStock::where('warehouse_id', $warehouse->id)
                ->where('component_id', $ac->component_id)
                ->first();
            $availableQty = $stockRow ? (float) $stockRow->quantity : 0;

            if ($validateStock && $availableQty < $qty) {
                return ApiResponse::error(
                    "Stock insuficiente en '{$warehouse->name}'. Disponible: {$availableQty} {$ac->component->unit_of_measure}",
                    422
                );
            }
        }

        DB::beginTransaction();
        try {
            $stockRow = ComponentWarehouseStock::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'component_id' => $ac->component_id],
                ['quantity' => 0]
            );

            $unitCost     = $stockRow->average_unit_cost ?? $ac->component->unit_cost;
            $quantityDelta = 0;
            $stockDelta    = 0;

            switch ($movementType) {
                case 'installation':
                    // Nuevo componente: descuenta stock, incrementa instalados
                    $stockDelta    = -$qty;
                    $quantityDelta = +$qty;
                    $ac->installed_quantity = max(0, (float) $ac->installed_quantity + $qty);
                    break;

                case 'replacement':
                    // Reemplazo: solo descuenta stock, instalados no cambian
                    $stockDelta    = -$qty;
                    $quantityDelta = 0;
                    break;

                case 'removal':
                    // Retiro: devuelve al stock, reduce instalados
                    $stockDelta    = +$qty;
                    $quantityDelta = -$qty;
                    $ac->installed_quantity = max(0, (float) $ac->installed_quantity - $qty);
                    break;
            }

            $stockRow->applyMovement($stockDelta);
            $ac->recalculateStatus();
            $ac->save();

            AssetComponentConsumption::create([
                'company_id'        => $companyId,
                'asset_id'          => $assetId,
                'component_id'      => $ac->component_id,
                'work_order_id'     => $request->work_order_id,
                'warehouse_id'      => $warehouse->id,
                'movement_type'     => $movementType,
                'quantity_consumed' => $qty,
                'quantity_delta'    => $quantityDelta,
                'returns_to_stock'  => ($movementType === 'removal'),
                'unit_cost'         => $unitCost,
                'total_cost'        => abs($stockDelta) > 0 ? (float) $unitCost * $qty : null,
                'stock_after'       => $stockRow->quantity,
                'notes'             => $request->notes,
                'performed_by'      => $user->id,
                'consumed_at'       => now(),
            ]);

            DB::commit();

            Log::info('Movimiento de componente registrado', [
                'asset_id'      => $assetId,
                'component_id'  => $ac->component_id,
                'movement_type' => $movementType,
                'quantity'      => $qty,
                'warehouse_id'  => $warehouse->id,
                'performed_by'  => $user->id,
            ]);

            return ApiResponse::success([
                'installed_quantity' => $ac->installed_quantity,
                'stock_after'        => $stockRow->quantity,
                'status'             => $ac->status,
                'movement_type'      => $movementType,
            ], 'Movimiento registrado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al registrar movimiento: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /assets/{assetId}/components/{id}/consumption-history
     * Historial de consumo de un componente en un activo específico.
     */
    public function consumptionHistory(Request $request, int $companyId, int $assetId, int $id): JsonResponse
    {
        $user = Auth::user();

        if (!\App\Helpers\PermissionHelper::hasPermission($user, 'INVENTORY_READ_COMPONENT', $companyId)) {
            return ApiResponse::error('Sin permiso', 403);
        }

        $ac = AssetComponent::where('asset_id', $assetId)->find($id);
        if (!$ac) {
            return ApiResponse::notFound('Relación no encontrada');
        }

        $history = AssetComponentConsumption::where('asset_id', $assetId)
            ->where('component_id', $ac->component_id)
            ->select([
                'id', 'movement_type', 'quantity_consumed', 'quantity_delta',
                'returns_to_stock', 'unit_cost', 'total_cost', 'stock_after',
                'notes', 'consumed_at', 'warehouse_id', 'work_order_id', 'performed_by',
            ])
            ->with([
                'warehouse:id,name',
                'workOrder:id,code',
                'performedBy:id,first_name,last_name',
            ])
            ->orderBy('consumed_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return ApiResponse::success($history, 'Historial de consumo obtenido');
    }
}
