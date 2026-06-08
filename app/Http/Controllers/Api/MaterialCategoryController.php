<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MaterialCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MaterialCategoryController extends Controller
{
    /**
     * Listar categorías de materiales
     * 
     * GET /api/material-categories
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-Id');
        
        $query = MaterialCategory::where('company_id', $companyId)
            ->with(['parentCategory:id,name,code', 'childCategories'])
            ->withCount('materials');
        
        // Filtros
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('code', 'LIKE', "%{$search}%")
                  ->orWhere('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }
        
        if ($request->has('parent_category_id')) {
            $query->where('parent_category_id', $request->input('parent_category_id'));
        }
        
        if ($request->boolean('only_active')) {
            $query->where('is_active', true);
        }
        
        if ($request->boolean('only_parents')) {
            $query->whereNull('parent_category_id');
        }
        
        // Ordenamiento
        $sortBy = $request->input('sort_by', 'name');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Paginación
        $perPage = $request->input('per_page', 15);
        
        if ($perPage === 'all') {
            $categories = $query->get();
            return ApiResponse::success($categories, 'Categorías de materiales obtenidas exitosamente');
        }
        
        $categories = $query->paginate($perPage);
        
        return ApiResponse::success($categories, 'Categorías de materiales obtenidas exitosamente');
    }

    /**
     * Ver detalle de una categoría
     * 
     * GET /api/material-categories/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('X-Company-Id');
        
        $category = MaterialCategory::where('company_id', $companyId)
            ->where('id', $id)
            ->with([
                'parentCategory:id,name,code',
                'childCategories',
                'materials' => function ($query) {
                    $query->select('id', 'code', 'name', 'material_category_id', 'is_active')
                        ->where('is_active', true)
                        ->limit(10);
                }
            ])
            ->withCount('materials')
            ->first();
        
        if (!$category) {
            return ApiResponse::error('Categoría no encontrada', 404);
        }
        
        return ApiResponse::success($category, 'Categoría obtenida exitosamente');
    }

    /**
     * Crear nueva categoría
     * 
     * POST /api/material-categories
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-Id');
        
        $validator = Validator::make($request->all(), [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/',
                function ($attribute, $value, $fail) use ($companyId) {
                    $exists = MaterialCategory::where('company_id', $companyId)
                        ->where('code', $value)
                        ->exists();
                    if ($exists) {
                        $fail('El código ya existe en esta empresa');
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'parent_category_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($companyId) {
                    if ($value) {
                        $exists = MaterialCategory::where('company_id', $companyId)
                            ->where('id', $value)
                            ->exists();
                        if (!$exists) {
                            $fail('La categoría padre no existe');
                        }
                    }
                },
            ],
            'is_active' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }
        
        try {
            DB::beginTransaction();
            
            $category = MaterialCategory::create([
                'company_id' => $companyId,
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'parent_category_id' => $request->input('parent_category_id'),
                'is_active' => $request->input('is_active', true),
                'created_by' => $request->user()->id,
            ]);
            
            $category->load(['parentCategory:id,name,code', 'childCategories']);
            
            DB::commit();
            
            return ApiResponse::success(
                $category,
                'Categoría creada exitosamente',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al crear categoría de material', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error('Error al crear categoría', 500);
        }
    }

    /**
     * Actualizar categoría
     * 
     * PUT /api/material-categories/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('X-Company-Id');
        
        $category = MaterialCategory::where('company_id', $companyId)
            ->where('id', $id)
            ->first();
        
        if (!$category) {
            return ApiResponse::error('Categoría no encontrada', 404);
        }
        
        $validator = Validator::make($request->all(), [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-_]+$/',
                function ($attribute, $value, $fail) use ($companyId, $id) {
                    $exists = MaterialCategory::where('company_id', $companyId)
                        ->where('code', $value)
                        ->where('id', '!=', $id)
                        ->exists();
                    if ($exists) {
                        $fail('El código ya existe en esta empresa');
                    }
                },
            ],
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'parent_category_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($companyId, $id) {
                    if ($value) {
                        // No puede ser su propio padre
                        if ($value == $id) {
                            $fail('Una categoría no puede ser su propio padre');
                            return;
                        }
                        
                        // La categoría padre debe existir
                        $exists = MaterialCategory::where('company_id', $companyId)
                            ->where('id', $value)
                            ->exists();
                        if (!$exists) {
                            $fail('La categoría padre no existe');
                        }
                    }
                },
            ],
            'is_active' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return ApiResponse::error('Error de validación', 422, $validator->errors());
        }
        
        try {
            DB::beginTransaction();
            
            $category->update($request->only([
                'code',
                'name',
                'description',
                'parent_category_id',
                'is_active',
            ]));
            
            $category->load(['parentCategory:id,name,code', 'childCategories']);
            
            DB::commit();
            
            return ApiResponse::success(
                $category,
                'Categoría actualizada exitosamente'
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error al actualizar categoría de material', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error('Error al actualizar categoría', 500);
        }
    }

    /**
     * Eliminar categoría
     * 
     * DELETE /api/material-categories/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('X-Company-Id');
        
        $category = MaterialCategory::where('company_id', $companyId)
            ->where('id', $id)
            ->withCount(['materials', 'childCategories'])
            ->first();
        
        if (!$category) {
            return ApiResponse::error('Categoría no encontrada', 404);
        }
        
        // Validar que no tenga materiales asociados
        if ($category->materials_count > 0) {
            return ApiResponse::error(
                'No se puede eliminar la categoría porque tiene materiales asociados',
                400
            );
        }
        
        // Validar que no tenga subcategorías
        if ($category->child_categories_count > 0) {
            return ApiResponse::error(
                'No se puede eliminar la categoría porque tiene subcategorías',
                400
            );
        }
        
        try {
            $category->delete();
            
            return ApiResponse::success(
                null,
                'Categoría eliminada exitosamente'
            );
        } catch (\Exception $e) {
            Log::error('Error al eliminar categoría de material', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiResponse::error('Error al eliminar categoría', 500);
        }
    }

    /**
     * Obtener árbol jerárquico de categorías
     * 
     * GET /api/material-categories/tree
     */
    public function tree(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-Id');
        
        $onlyActive = $request->boolean('only_active', true);
        
        $query = MaterialCategory::where('company_id', $companyId)
            ->whereNull('parent_category_id')
            ->with(['childCategories' => function ($query) use ($onlyActive) {
                if ($onlyActive) {
                    $query->where('is_active', true);
                }
                $query->withCount('materials');
            }])
            ->withCount('materials');
        
        if ($onlyActive) {
            $query->where('is_active', true);
        }
        
        $categories = $query->orderBy('name', 'asc')->get();
        
        return ApiResponse::success(
            $categories,
            'Árbol de categorías obtenido exitosamente'
        );
    }

    /**
     * Obtener estadísticas de categorías
     * 
     * GET /api/material-categories/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $companyId = $request->header('X-Company-Id');
        
        $totalCategories = MaterialCategory::where('company_id', $companyId)->count();
        $activeCategories = MaterialCategory::where('company_id', $companyId)
            ->where('is_active', true)
            ->count();
        $parentCategories = MaterialCategory::where('company_id', $companyId)
            ->whereNull('parent_category_id')
            ->count();
        
        $topCategories = MaterialCategory::where('company_id', $companyId)
            ->withCount('materials')
            ->orderBy('materials_count', 'desc')
            ->limit(5)
            ->get(['id', 'code', 'name']);
        
        return ApiResponse::success([
            'total_categories' => $totalCategories,
            'active_categories' => $activeCategories,
            'parent_categories' => $parentCategories,
            'top_categories_by_materials' => $topCategories,
        ], 'Estadísticas de categorías obtenidas exitosamente');
    }
}

