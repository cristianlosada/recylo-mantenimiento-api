<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Material;
use App\Models\WarehouseStock;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class MaterialController extends Controller
{
    /**
     * Listar materiales con filtros y paginación
     * 
     * GET /api/materials
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = Material::where('company_id', $companyId)
            ->with(['category', 'stock.warehouse', 'creator']);

        // Filtros
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('material_category_id', $request->category_id);
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

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $materials = $query->paginate($perPage);

        return ApiResponse::success($materials, 'Materiales obtenidos exitosamente');
    }

    /**
     * Mostrar material específico con stock
     * 
     * GET /api/materials/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $material = Material::with([
            'company',
            'category',
            'stock.warehouse',
            'transactions' => fn($q) => $q->latest()->limit(10),
            'creator'
        ])
        ->where('company_id', $companyId)
        ->find($id);

        if (!$material) {
            return ApiResponse::notFound('Material no encontrado');
        }

        // Calcular stock total
        $totalStock = $material->stock->sum('quantity');
        $totalValue = $material->stock->sum('total_value');

        $response = $material->toArray();
        $response['total_stock'] = $totalStock;
        $response['total_value'] = $totalValue;
        $response['needs_reorder'] = $totalStock <= ($material->reorder_point ?? 0);

        return ApiResponse::success($response, 'Material obtenido exitosamente');
    }

    /**
     * Crear nuevo material
     * 
     * POST /api/materials
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        $userId = Auth::id();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'material_category_id' => 'nullable|exists:material_categories,id',
            'barcode' => 'nullable|string|max:100',
            'sku' => 'nullable|string|max:100',
            'manufacturer_part_number' => 'nullable|string|max:100',
            'unit_of_measure' => 'required|string|max:50',
            'unit_cost' => 'required|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'maximum_stock' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'reorder_quantity' => 'nullable|numeric|min:0',
            'default_supplier' => 'nullable|string',
            'is_critical' => 'boolean',
            'image_path' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }

        try {
            DB::beginTransaction();

            // Determinar si es herramienta basado en la categoría
            $isTool = false;
            $categoryPrefix = 'MAT'; // Por defecto
            
            if ($request->material_category_id) {
                $category = \App\Models\MaterialCategory::find($request->material_category_id);
                if ($category) {
                    $isTool = $category->isToolCategory();
                    
                    // Obtener prefijo de la categoría
                    if ($category->code) {
                        // Extraer las últimas letras del código (ej: CAT-HERR-MAN -> MAN)
                        $codeParts = explode('-', $category->code);
                        $categoryPrefix = end($codeParts);
                    }
                }
            }

            // Generar código automático único
            $code = $this->generateUniqueCode($companyId, $categoryPrefix);

            $material = Material::create([
                'company_id' => $companyId,
                'material_category_id' => $request->material_category_id,
                'code' => $code,
                'name' => $request->name,
                'description' => $request->description,
                'barcode' => $request->barcode,
                'sku' => $request->sku,
                'manufacturer_part_number' => $request->manufacturer_part_number,
                'unit_of_measure' => $request->unit_of_measure,
                'unit_cost' => $request->unit_cost,
                'minimum_stock' => $request->minimum_stock ?? 0,
                'maximum_stock' => $request->maximum_stock,
                'reorder_point' => $request->reorder_point,
                'reorder_quantity' => $request->reorder_quantity,
                'default_supplier' => $request->default_supplier,
                'is_active' => true,
                'is_critical' => $request->boolean('is_critical'),
                'is_tool' => $isTool,
                'image_path' => $request->image_path,
                'notes' => $request->notes,
                'created_by' => $userId,
            ]);

            DB::commit();

            $material->load(['category', 'creator']);

            return ApiResponse::created($material, 'Material creado exitosamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear material: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar material
     * 
     * PUT /api/materials/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $material = Material::where('company_id', $companyId)->find($id);

        if (!$material) {
            return ApiResponse::notFound('Material no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:50',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'material_category_id' => 'nullable|exists:material_categories,id',
            'unit_cost' => 'sometimes|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'maximum_stock' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'reorder_quantity' => 'nullable|numeric|min:0',
            'is_active' => 'boolean',
            'is_critical' => 'boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }

        try {
            $material->update($request->only([
                'code', 'name', 'description', 'material_category_id',
                'barcode', 'sku', 'manufacturer_part_number',
                'unit_of_measure', 'unit_cost', 'minimum_stock',
                'maximum_stock', 'reorder_point', 'reorder_quantity',
                'default_supplier', 'is_active', 'is_critical',
                'image_path', 'notes'
            ]));

            $material->load(['category', 'stock.warehouse']);

            return ApiResponse::success($material, 'Material actualizado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar material: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar material (soft delete)
     * 
     * DELETE /api/materials/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $material = Material::where('company_id', $companyId)->find($id);

        if (!$material) {
            return ApiResponse::notFound('Material no encontrado');
        }

        // Verificar si tiene stock
        $hasStock = $material->stock()->where('quantity', '>', 0)->exists();
        if ($hasStock) {
            return ApiResponse::error('No se puede eliminar un material con stock existente', 422);
        }

        try {
            $material->delete();
            return ApiResponse::success(null, 'Material eliminado exitosamente');
        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar material: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener stock de un material en todos los almacenes
     * 
     * GET /api/materials/{id}/stock
     */
    public function getStock(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $material = Material::where('company_id', $companyId)->find($id);

        if (!$material) {
            return ApiResponse::notFound('Material no encontrado');
        }

        $stock = WarehouseStock::where('material_id', $id)
            ->with('warehouse')
            ->get();

        $totalStock = $stock->sum('quantity');
        $totalValue = $stock->sum('total_value');

        return ApiResponse::success([
            'material' => $material,
            'stock_by_warehouse' => $stock,
            'total_stock' => $totalStock,
            'total_value' => $totalValue,
            'minimum_stock' => $material->minimum_stock,
            'needs_reorder' => $totalStock <= ($material->reorder_point ?? 0),
            'is_low_stock' => $totalStock < $material->minimum_stock,
        ], 'Stock obtenido exitosamente');
    }

    /**
     * Obtener estadísticas de materiales
     * 
     * GET /api/materials/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $totalMaterials = Material::where('company_id', $companyId)->count();
        $activeMaterials = Material::where('company_id', $companyId)->active()->count();
        $criticalMaterials = Material::where('company_id', $companyId)->critical()->count();
        
        // Materiales con bajo stock
        $lowStockMaterials = Material::where('company_id', $companyId)
            ->whereHas('stock', function($q) {
                $q->whereRaw('quantity < minimum_stock');
            })
            ->count();

        // Valor total del inventario
        $totalValue = WarehouseStock::whereHas('warehouse', function($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->get()->sum('total_value');

        return ApiResponse::success([
            'total_materials' => $totalMaterials,
            'active_materials' => $activeMaterials,
            'critical_materials' => $criticalMaterials,
            'low_stock_materials' => $lowStockMaterials,
            'total_inventory_value' => round($totalValue, 2),
        ], 'Estadísticas obtenidas exitosamente');
    }

    /**
     * Generar código de barras EAN-13 para un material
     * 
     * POST /api/materials/{id}/barcode/generate
     */
    public function generateBarcode(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');
        
        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }
        
        $material = Material::where('company_id', $companyId)
            ->where('id', $id)
            ->first();
        
        if (!$material) {
            return ApiResponse::notFound('Material no encontrado');
        }
        
        // Verificar si ya tiene código de barras
        if ($material->barcode) {
            return ApiResponse::error('El material ya tiene un código de barras asignado', 400);
        }
        
        try {
            DB::beginTransaction();
            
            // Generar código EAN-13 único
            $barcode = $this->generateUniqueEAN13($companyId);
            
            // Actualizar material
            $material->barcode = $barcode;
            $material->save();
            
            // Generar imágenes del código de barras
            $barcodeImages = $this->generateBarcodeImages($barcode, $material->code);
            
            DB::commit();
            
            return ApiResponse::success([
                'barcode' => $barcode,
                'barcode_image_svg' => $barcodeImages['svg'],
                'barcode_image_png' => $barcodeImages['png_base64'],
                'barcode_url' => $barcodeImages['url']
            ], 'Código de barras generado exitosamente');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error generando código de barras: ' . $e->getMessage());
            return ApiResponse::error('Error al generar código de barras: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener imagen del código de barras
     * 
     * GET /api/materials/{id}/barcode?format=png|svg
     */
    public function getBarcodeImage(Request $request, int $id)
    {
        $companyId = $request->header('x-company-id');
        $format = $request->query('format', 'png'); // png o svg
        
        $material = Material::where('company_id', $companyId)
            ->where('id', $id)
            ->first();
        
        if (!$material || !$material->barcode) {
            return response()->json(['error' => 'Código de barras no encontrado'], 404);
        }
        
        try {
            $barcodeImages = $this->generateBarcodeImages($material->barcode, $material->code);
            
            if ($format === 'svg') {
                return response($barcodeImages['svg'])
                    ->header('Content-Type', 'image/svg+xml');
            }
            
            // PNG
            $imageData = base64_decode(str_replace('data:image/png;base64,', '', $barcodeImages['png_base64']));
            
            return response($imageData)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', "inline; filename=\"{$material->code}.png\"");
                
        } catch (\Exception $e) {
            Log::error('Error obteniendo imagen de código de barras: ' . $e->getMessage());
            return response()->json(['error' => 'Error al generar imagen'], 500);
        }
    }

    /**
     * Genera un código EAN-13 único
     */
    private function generateUniqueEAN13(int $companyId): string
    {
        $maxAttempts = 100;
        $attempts = 0;
        
        do {
            // Prefijo del país (ejemplo: 789 para Argentina, 850 para Cuba, 770 para Colombia)
            $countryPrefix = '789';
            
            // Código de empresa (5 dígitos basados en company_id)
            $companyCode = str_pad((string) ($companyId % 99999), 5, '0', STR_PAD_LEFT);
            
            // Código de producto aleatorio (4 dígitos)
            $productCode = str_pad((string) rand(0, 9999), 4, '0', STR_PAD_LEFT);
            
            // Primeros 12 dígitos
            $code12 = $countryPrefix . $companyCode . $productCode;
            
            // Calcular dígito verificador EAN-13
            $checkDigit = $this->calculateEAN13CheckDigit($code12);
            
            $barcode = $code12 . $checkDigit;
            
            // Verificar que no exista
            $exists = Material::where('barcode', $barcode)->exists();
            
            $attempts++;
            
        } while ($exists && $attempts < $maxAttempts);
        
        if ($attempts >= $maxAttempts) {
            throw new \Exception('No se pudo generar un código de barras único');
        }
        
        return $barcode;
    }

    /**
     * Calcula el dígito verificador de EAN-13
     */
    private function calculateEAN13CheckDigit(string $code12): int
    {
        $sum = 0;
        
        for ($i = 0; $i < 12; $i++) {
            $digit = (int) $code12[$i];
            $sum += ($i % 2 === 0) ? $digit : $digit * 3;
        }
        
        $mod = $sum % 10;
        return $mod === 0 ? 0 : 10 - $mod;
    }

    /**
     * Genera las imágenes del código de barras en múltiples formatos
     */
    private function generateBarcodeImages(string $barcode, string $materialCode): array
    {
        $generatorPNG = new BarcodeGeneratorPNG();
        $generatorSVG = new BarcodeGeneratorSVG();
        
        // Generar PNG
        $pngData = $generatorPNG->getBarcode($barcode, $generatorPNG::TYPE_EAN_13, 2, 50);
        $pngBase64 = 'data:image/png;base64,' . base64_encode($pngData);
        
        // Generar SVG
        $svgData = $generatorSVG->getBarcode($barcode, $generatorSVG::TYPE_EAN_13, 2, 50);
        
        // Guardar PNG en storage
        $directory = 'barcodes';
        $filename = "{$materialCode}.png";
        Storage::disk('public')->put("{$directory}/{$filename}", $pngData);
        $url = asset("storage/{$directory}/{$filename}");
        
        return [
            'svg' => $svgData,
            'png_base64' => $pngBase64,
            'url' => $url
        ];
    }

    /**
     * Genera un código único para el material basado en la categoría.
     * Formato: {PREFIX}-{NUMERO} (ej: MAT-0001, COR-0001, HERR-0002)
     *
     * Usa Cache::lock para prevenir race conditions en peticiones simultáneas.
     * DEBE llamarse dentro de una transacción DB activa.
     */
    private function generateUniqueCode(int $companyId, string $prefix = 'MAT'): string
    {
        $lockKey = "material_code_generation_{$companyId}_{$prefix}";
        $lock = \Cache::lock($lockKey, 5);

        try {
            // Esperar hasta 10 segundos para obtener el lock
            $lock->block(10);

            // Buscar el número más alto ya usado para este prefijo+empresa
            $lastMaterial = Material::where('company_id', $companyId)
                ->where('code', 'like', "{$prefix}-%")
                ->orderByRaw('CAST(SUBSTRING_INDEX(code, \'-\', -1) AS UNSIGNED) DESC')
                ->first();

            $startNumber = $lastMaterial
                ? ((int) end(explode('-', $lastMaterial->code))) + 1
                : 1;

            // Avanzar iterativamente hasta encontrar un código libre (cubre huecos)
            $maxAttempts = 200;
            for ($i = 0; $i < $maxAttempts; $i++) {
                $code = sprintf('%s-%04d', $prefix, $startNumber + $i);

                $exists = Material::where('company_id', $companyId)
                    ->where('code', $code)
                    ->exists();

                if (!$exists) {
                    $lock->release();
                    return $code;
                }
            }

            $lock->release();
            throw new \RuntimeException("No se pudo generar un código único para el prefijo {$prefix} después de {$maxAttempts} intentos.");

        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            throw new \RuntimeException("No se pudo obtener el lock para generar el código de material. Intenta de nuevo.");
        }
    }
}

