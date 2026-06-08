<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Company;
use App\Models\InventoryTransaction;
use App\Models\Material;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryTransactionController extends Controller
{
    /**
     * Listar transacciones con filtros y paginación
     * 
     * GET /api/inventory/transactions
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Validar parámetros de entrada
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'transaction_type' => 'string|in:purchase,adjustment,work_order_out,return,transfer,damage,initial',
            'warehouse_id' => 'integer|exists:warehouses,id',
            'material_id' => 'integer|exists:materials,id',
            'date_from' => 'date',
            'date_to' => 'date|after_or_equal:date_from',
            'sort_by' => 'string|in:transaction_date,transaction_code,quantity',
            'sort_order' => 'string|in:asc,desc'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        // Construir query con relaciones
        $query = InventoryTransaction::where('company_id', $companyId)
            ->with([
                'warehouse:id,code,name',
                'material:id,code,name,unit_of_measure',
                'performedBy:id,first_name,last_name,email',
                'approvedBy:id,first_name,last_name,email',
                'fromWarehouse:id,code,name',
                'toWarehouse:id,code,name'
            ]);

        // Aplicar filtros opcionales
        if ($request->filled('transaction_type')) {
            $query->byType($request->transaction_type);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('material_id')) {
            $query->where('material_id', $request->material_id);
        }

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->byDateRange($request->date_from, $request->date_to);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'transaction_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        return ApiResponse::success($transactions, 'Transacciones obtenidas exitosamente');
    }

    /**
     * Mostrar transacción específica
     * 
     * GET /api/inventory/transactions/{id}
     * 
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $transaction = InventoryTransaction::with([
            'company',
            'warehouse',
            'material',
            'performedBy',
            'approvedBy',
            'fromWarehouse',
            'toWarehouse'
        ])
        ->where('company_id', $companyId)
        ->find($id);

        if (!$transaction) {
            return ApiResponse::notFound('Transacción no encontrada');
        }

        return ApiResponse::success($transaction, 'Transacción recuperada exitosamente');
    }

    /**
     * Registrar ajuste de stock (entrada/salida manual)
     * 
     * POST /api/inventory/adjust
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function adjust(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        // Verificar empresa
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Validación de datos
        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'material_id' => 'required|integer|exists:materials,id',
            'quantity' => 'required|numeric|not_in:0',
            'unit_cost' => 'nullable|numeric|min:0',
            'reason' => 'required|string|max:500',
            'reference_document' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Verificar que almacén y material pertenecen a la empresa
            $warehouse = Warehouse::where('company_id', $companyId)
                ->where('id', $request->warehouse_id)
                ->first();

            if (!$warehouse) {
                return ApiResponse::notFound('Almacén no encontrado o no pertenece a la empresa');
            }

            $material = Material::where('company_id', $companyId)
                ->where('id', $request->material_id)
                ->first();

            if (!$material) {
                return ApiResponse::notFound('Material no encontrado o no pertenece a la empresa');
            }

            // Obtener o crear registro de stock
            $stock = WarehouseStock::firstOrNew([
                'warehouse_id' => $request->warehouse_id,
                'material_id' => $request->material_id
            ], [
                'quantity' => 0,
                'average_unit_cost' => $material->unit_cost ?? 0
            ]);

            $oldQuantity = $stock->quantity ?? 0;
            $newQuantity = $oldQuantity + $request->quantity;

            // Validar que no quede stock negativo
            if ($newQuantity < 0) {
                return ApiResponse::error(
                    "Stock insuficiente. Stock actual: {$oldQuantity}, cantidad solicitada: " . abs($request->quantity),
                    422
                );
            }

            // Calcular costo promedio ponderado (CPP) solo para entradas con costo
            $unitCost = $request->unit_cost ?? $material->unit_cost ?? 0;
            
            if ($request->quantity > 0 && $unitCost > 0) {
                // Entrada con costo: recalcular CPP
                $totalValue = ($oldQuantity * $stock->average_unit_cost) + ($request->quantity * $unitCost);
                $stock->average_unit_cost = $newQuantity > 0 ? $totalValue / $newQuantity : $unitCost;
            }

            $stock->quantity = $newQuantity;
            $stock->save();

            // Generar código de transacción
            $transactionCode = $this->generateTransactionCode('ADJ');

            // Crear transacción
            $transaction = InventoryTransaction::create([
                'company_id' => $companyId,
                'transaction_code' => $transactionCode,
                'transaction_type' => 'adjustment',
                'warehouse_id' => $request->warehouse_id,
                'material_id' => $request->material_id,
                'quantity' => $request->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $request->quantity * $unitCost,
                'balance_after' => $newQuantity,
                'reason' => $request->reason,
                'reference_document' => $request->reference_document,
                'transaction_date' => $request->transaction_date ?? now(),
                'performed_by' => Auth::id()
            ]);

            $transaction->load(['warehouse', 'material', 'performedBy']);

            DB::commit();

            return ApiResponse::success(
                $transaction,
                'Ajuste de stock registrado exitosamente',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al registrar ajuste: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Transferir stock entre almacenes
     * 
     * POST /api/inventory/transfer
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function transfer(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        // Verificar empresa
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Validación de datos
        $validator = Validator::make($request->all(), [
            'from_warehouse_id' => 'required|integer|exists:warehouses,id',
            'to_warehouse_id' => 'required|integer|exists:warehouses,id|different:from_warehouse_id',
            'material_id' => 'required|integer|exists:materials,id',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:500',
            'reference_document' => 'nullable|string|max:100',
            'transaction_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Verificar almacenes
            $fromWarehouse = Warehouse::where('company_id', $companyId)
                ->where('id', $request->from_warehouse_id)
                ->first();

            $toWarehouse = Warehouse::where('company_id', $companyId)
                ->where('id', $request->to_warehouse_id)
                ->first();

            if (!$fromWarehouse || !$toWarehouse) {
                return ApiResponse::notFound('Uno o ambos almacenes no encontrados');
            }

            // Verificar material
            $material = Material::where('company_id', $companyId)
                ->where('id', $request->material_id)
                ->first();

            if (!$material) {
                return ApiResponse::notFound('Material no encontrado');
            }

            // Verificar stock origen
            $fromStock = WarehouseStock::where('warehouse_id', $request->from_warehouse_id)
                ->where('material_id', $request->material_id)
                ->first();

            if (!$fromStock || $fromStock->quantity < $request->quantity) {
                return ApiResponse::error(
                    'Stock insuficiente en almacén origen. Disponible: ' . ($fromStock->quantity ?? 0),
                    422
                );
            }

            // Reducir stock origen
            $fromOldQuantity = $fromStock->quantity;
            $fromStock->quantity -= $request->quantity;
            $fromStock->save();

            // Obtener o crear stock destino
            $toStock = WarehouseStock::firstOrNew([
                'warehouse_id' => $request->to_warehouse_id,
                'material_id' => $request->material_id
            ], [
                'quantity' => 0,
                'average_unit_cost' => $fromStock->average_unit_cost
            ]);

            $toOldQuantity = $toStock->quantity ?? 0;

            // Calcular CPP para almacén destino
            $transferUnitCost = $fromStock->average_unit_cost;
            $totalValue = ($toOldQuantity * $toStock->average_unit_cost) + ($request->quantity * $transferUnitCost);
            $toNewQuantity = $toOldQuantity + $request->quantity;
            $toStock->quantity = $toNewQuantity;
            $toStock->average_unit_cost = $toNewQuantity > 0 ? $totalValue / $toNewQuantity : $transferUnitCost;
            $toStock->save();

            // Generar código único para ambas transacciones
            $referenceCode = $this->generateTransactionCode('TRF');

            // Crear transacción de salida
            $transactionOut = InventoryTransaction::create([
                'company_id' => $companyId,
                'transaction_code' => $referenceCode . '-OUT',
                'transaction_type' => 'transfer',
                'warehouse_id' => $request->from_warehouse_id,
                'material_id' => $request->material_id,
                'quantity' => -$request->quantity,
                'unit_cost' => $transferUnitCost,
                'total_cost' => -($request->quantity * $transferUnitCost),
                'balance_after' => $fromStock->quantity,
                'reason' => $request->reason,
                'reference_document' => $referenceCode,
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'transaction_date' => $request->transaction_date ?? now(),
                'performed_by' => Auth::id()
            ]);

            // Crear transacción de entrada
            $transactionIn = InventoryTransaction::create([
                'company_id' => $companyId,
                'transaction_code' => $referenceCode . '-IN',
                'transaction_type' => 'transfer',
                'warehouse_id' => $request->to_warehouse_id,
                'material_id' => $request->material_id,
                'quantity' => $request->quantity,
                'unit_cost' => $transferUnitCost,
                'total_cost' => $request->quantity * $transferUnitCost,
                'balance_after' => $toStock->quantity,
                'reason' => $request->reason,
                'reference_document' => $referenceCode,
                'from_warehouse_id' => $request->from_warehouse_id,
                'to_warehouse_id' => $request->to_warehouse_id,
                'transaction_date' => $request->transaction_date ?? now(),
                'performed_by' => Auth::id()
            ]);

            $transactionOut->load(['fromWarehouse', 'toWarehouse', 'material', 'performedBy']);
            $transactionIn->load(['fromWarehouse', 'toWarehouse', 'material', 'performedBy']);

            DB::commit();

            return ApiResponse::success([
                'transfer_code' => $referenceCode,
                'transaction_out' => $transactionOut,
                'transaction_in' => $transactionIn,
                'from_warehouse' => [
                    'id' => $fromWarehouse->id,
                    'name' => $fromWarehouse->name,
                    'old_quantity' => $fromOldQuantity,
                    'new_quantity' => $fromStock->quantity
                ],
                'to_warehouse' => [
                    'id' => $toWarehouse->id,
                    'name' => $toWarehouse->name,
                    'old_quantity' => $toOldQuantity,
                    'new_quantity' => $toStock->quantity
                ]
            ], 'Transferencia realizada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al realizar transferencia: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Registrar compra (entrada por adquisición)
     * 
     * POST /api/inventory/purchase
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function purchase(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        // Verificar empresa
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Validación de datos
        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'material_id' => 'required|integer|exists:materials,id',
            'quantity' => 'required|numeric|min:0.001',
            'unit_cost' => 'required|numeric|min:0',
            'purchase_order_number' => 'nullable|string|max:100',
            'reference_document' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:500',
            'transaction_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Verificar almacén y material
            $warehouse = Warehouse::where('company_id', $companyId)
                ->where('id', $request->warehouse_id)
                ->first();

            $material = Material::where('company_id', $companyId)
                ->where('id', $request->material_id)
                ->first();

            if (!$warehouse || !$material) {
                return ApiResponse::notFound('Almacén o material no encontrado');
            }

            // Obtener o crear stock
            $stock = WarehouseStock::firstOrNew([
                'warehouse_id' => $request->warehouse_id,
                'material_id' => $request->material_id
            ], [
                'quantity' => 0,
                'average_unit_cost' => 0
            ]);

            $oldQuantity = $stock->quantity ?? 0;
            $oldAverageCost = $stock->average_unit_cost ?? 0;

            // Calcular nuevo CPP
            $totalValue = ($oldQuantity * $oldAverageCost) + ($request->quantity * $request->unit_cost);
            $newQuantity = $oldQuantity + $request->quantity;
            
            $stock->quantity = $newQuantity;
            $stock->average_unit_cost = $newQuantity > 0 ? $totalValue / $newQuantity : $request->unit_cost;
            $stock->save();

            // Generar código de transacción
            $transactionCode = $this->generateTransactionCode('PUR');

            // Crear transacción
            $transaction = InventoryTransaction::create([
                'company_id' => $companyId,
                'transaction_code' => $transactionCode,
                'transaction_type' => 'purchase',
                'warehouse_id' => $request->warehouse_id,
                'material_id' => $request->material_id,
                'quantity' => $request->quantity,
                'unit_cost' => $request->unit_cost,
                'total_cost' => $request->quantity * $request->unit_cost,
                'balance_after' => $newQuantity,
                'reason' => $request->reason ?? 'Compra de material',
                'purchase_order_number' => $request->purchase_order_number,
                'reference_document' => $request->reference_document,
                'transaction_date' => $request->transaction_date ?? now(),
                'performed_by' => Auth::id()
            ]);

            $transaction->load(['warehouse', 'material', 'performedBy']);

            DB::commit();

            return ApiResponse::success([
                'transaction' => $transaction,
                'stock_updated' => [
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'old_average_cost' => $oldAverageCost,
                    'new_average_cost' => $stock->average_unit_cost
                ]
            ], 'Compra registrada exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al registrar compra: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Registrar daño o pérdida de material
     * 
     * POST /api/inventory/damage
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function damage(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        // Verificar empresa
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Validación de datos
        $validator = Validator::make($request->all(), [
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'material_id' => 'required|integer|exists:materials,id',
            'quantity' => 'required|numeric|min:0.001',
            'reason' => 'required|string|max:500',
            'reference_document' => 'nullable|string|max:100'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Verificar stock disponible
            $stock = WarehouseStock::where('warehouse_id', $request->warehouse_id)
                ->where('material_id', $request->material_id)
                ->first();

            if (!$stock || $stock->quantity < $request->quantity) {
                return ApiResponse::error(
                    'Stock insuficiente. Disponible: ' . ($stock->quantity ?? 0),
                    422
                );
            }

            $oldQuantity = $stock->quantity;
            $stock->quantity -= $request->quantity;
            $stock->save();

            // Generar código de transacción
            $transactionCode = $this->generateTransactionCode('DMG');

            // Crear transacción
            $transaction = InventoryTransaction::create([
                'company_id' => $companyId,
                'transaction_code' => $transactionCode,
                'transaction_type' => 'damage',
                'warehouse_id' => $request->warehouse_id,
                'material_id' => $request->material_id,
                'quantity' => -$request->quantity,
                'unit_cost' => $stock->average_unit_cost,
                'total_cost' => -($request->quantity * $stock->average_unit_cost),
                'balance_after' => $stock->quantity,
                'reason' => $request->reason,
                'reference_document' => $request->reference_document,
                'transaction_date' => now(),
                'performed_by' => Auth::id()
            ]);

            $transaction->load(['warehouse', 'material', 'performedBy']);

            DB::commit();

            return ApiResponse::success($transaction, 'Daño registrado exitosamente', 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error(
                'Error al registrar daño: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Generar código único de transacción
     * 
     * Formato: PREFIX-YYYYMM-NNNNN
     * 
     * @param string $prefix
     * @return string
     */
    private function generateTransactionCode(string $prefix): string
    {
        $yearMonth = now()->format('Ym');
        
        // Obtener último número del mes
        $lastTransaction = InventoryTransaction::where('transaction_code', 'like', "{$prefix}-{$yearMonth}-%")
            ->orderBy('transaction_code', 'desc')
            ->first();

        if ($lastTransaction) {
            // Extraer número y sumar 1
            $lastNumber = (int) substr($lastTransaction->transaction_code, -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $yearMonth, $newNumber);
    }
}
