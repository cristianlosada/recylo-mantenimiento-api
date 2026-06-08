<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\UnitOfMeasure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Obtener catálogo de unidades de medida
     * 
     * GET /api/catalogs/units-of-measure
     */
    public function getUnitsOfMeasure(Request $request): JsonResponse
    {
        $type = $request->query('type'); // discrete, weight, volume, length, area, electric
        
        if ($type) {
            $units = UnitOfMeasure::byType($type);
        } else {
            $units = UnitOfMeasure::all();
        }
        
        return ApiResponse::success($units, 'Unidades de medida obtenidas exitosamente');
    }

    /**
     * Obtener tipos de almacén
     * 
     * GET /api/catalogs/warehouse-types
     */
    public function getWarehouseTypes(): JsonResponse
    {
        $types = [
            ['value' => 'main', 'label' => 'Principal', 'icon' => '🏢', 'description' => 'Almacén principal de la empresa'],
            ['value' => 'secondary', 'label' => 'Secundario', 'icon' => '📦', 'description' => 'Almacén de apoyo o sucursal'],
            ['value' => 'mobile', 'label' => 'Móvil', 'icon' => '🚚', 'description' => 'Vehículo o contenedor móvil'],
            ['value' => 'external', 'label' => 'Externo', 'icon' => '🏭', 'description' => 'Almacén de terceros']
        ];
        
        return ApiResponse::success($types, 'Tipos de almacén obtenidos exitosamente');
    }

    /**
     * Obtener tipos de transacción de inventario
     * 
     * GET /api/catalogs/transaction-types
     */
    public function getTransactionTypes(): JsonResponse
    {
        $types = [
            ['value' => 'purchase', 'label' => 'Compra', 'icon' => '🛒', 'color' => 'success'],
            ['value' => 'adjustment', 'label' => 'Ajuste', 'icon' => '⚖️', 'color' => 'warning'],
            ['value' => 'work_order_out', 'label' => 'Salida por OT', 'icon' => '🔧', 'color' => 'info'],
            ['value' => 'return', 'label' => 'Devolución', 'icon' => '↩️', 'color' => 'success'],
            ['value' => 'transfer', 'label' => 'Transferencia', 'icon' => '🔄', 'color' => 'info'],
            ['value' => 'damage', 'label' => 'Daño/Pérdida', 'icon' => '💥', 'color' => 'danger'],
            ['value' => 'initial', 'label' => 'Inventario Inicial', 'icon' => '📋', 'color' => 'secondary']
        ];
        
        return ApiResponse::success($types, 'Tipos de transacción obtenidos exitosamente');
    }
}
