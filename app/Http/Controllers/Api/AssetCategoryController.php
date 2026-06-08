<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AssetCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssetCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AssetCategory::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('code', 'like', "%{$s}%");
            });
        }

        $categories = $query->orderBy('name')->get();

        return ApiResponse::success($categories, 'Categorías recuperadas exitosamente');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code'        => 'required|string|max:50|unique:asset_categories,code',
            'name'        => 'required|string|max:150',
            'description' => 'nullable|string',
            'icon'        => 'nullable|string|max:10',
            'color'       => 'nullable|string|max:50',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $category = AssetCategory::create([
            'code'        => strtoupper($request->code),
            'name'        => $request->name,
            'description' => $request->description,
            'icon'        => $request->icon,
            'color'       => $request->color,
            'is_active'   => $request->get('is_active', true),
        ]);

        return ApiResponse::success($category, 'Categoría creada exitosamente', 201);
    }

    public function show(int $id): JsonResponse
    {
        $category = AssetCategory::find($id);
        if (!$category) {
            return ApiResponse::notFound('Categoría no encontrada');
        }

        return ApiResponse::success($category, 'Categoría recuperada exitosamente');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $category = AssetCategory::find($id);
        if (!$category) {
            return ApiResponse::notFound('Categoría no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'code'        => 'sometimes|string|max:50|unique:asset_categories,code,' . $id,
            'name'        => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'icon'        => 'nullable|string|max:10',
            'color'       => 'nullable|string|max:50',
            'is_active'   => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $data = $request->only(['name', 'description', 'icon', 'color', 'is_active']);
        if ($request->filled('code')) {
            $data['code'] = strtoupper($request->code);
        }

        $category->update($data);

        return ApiResponse::success($category->fresh(), 'Categoría actualizada exitosamente');
    }

    public function destroy(int $id): JsonResponse
    {
        $category = AssetCategory::find($id);
        if (!$category) {
            return ApiResponse::notFound('Categoría no encontrada');
        }

        $assetsCount = $category->assets()->count();
        if ($assetsCount > 0) {
            return ApiResponse::error(
                "No se puede eliminar: hay {$assetsCount} activo(s) con esta categoría",
                422
            );
        }

        $category->delete();

        return ApiResponse::success(null, 'Categoría eliminada exitosamente');
    }
}
