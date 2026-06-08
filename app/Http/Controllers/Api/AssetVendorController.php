<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\AssetVendor;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AssetVendorController extends Controller
{
    /**
     * Listar fabricantes/proveedores de la empresa autenticada.
     *
     * Filtros opcionales: ?type=manufacturer|supplier|both, ?is_active=true
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $query = AssetVendor::byCompany($companyId);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        } else {
            $query->active();
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%");
            });
        }

        if ($request->filled('type')) {
            $type = $request->type;
            if ($type === 'manufacturer') {
                $query->manufacturers();
            } elseif ($type === 'supplier') {
                $query->suppliers();
            }
        }

        $vendors = $query->orderBy('name')->get();

        return ApiResponse::success($vendors, 'Fabricantes/proveedores recuperados exitosamente');
    }

    /**
     * Crear un nuevo fabricante/proveedor.
     */
    public function store(Request $request): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $company = Company::find($companyId);
        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'code'          => 'required|string|max:50',
            'name'          => 'required|string|max:150',
            'type'          => 'nullable|in:manufacturer,supplier,both',
            'contact_name'  => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:150',
            'contact_phone' => 'nullable|string|max:50',
            'notes'         => 'nullable|string',
            'is_active'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $exists = AssetVendor::byCompany($companyId)
            ->where('code', strtoupper($request->code))
            ->exists();

        if ($exists) {
            return ApiResponse::error('Ya existe un fabricante/proveedor con ese código para esta empresa', 422);
        }

        $vendor = AssetVendor::create([
            'company_id'    => $companyId,
            'code'          => strtoupper($request->code),
            'name'          => $request->name,
            'type'          => $request->get('type', 'both'),
            'contact_name'  => $request->contact_name,
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'notes'         => $request->notes,
            'is_active'     => $request->get('is_active', true),
        ]);

        return ApiResponse::success($vendor, 'Fabricante/proveedor creado exitosamente', 201);
    }

    /**
     * Mostrar un fabricante/proveedor.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $vendor = AssetVendor::byCompany($companyId)->find($id);
        if (!$vendor) {
            return ApiResponse::notFound('Fabricante/proveedor no encontrado');
        }

        return ApiResponse::success($vendor, 'Fabricante/proveedor recuperado exitosamente');
    }

    /**
     * Actualizar un fabricante/proveedor.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $vendor = AssetVendor::byCompany($companyId)->find($id);
        if (!$vendor) {
            return ApiResponse::notFound('Fabricante/proveedor no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'code'          => 'sometimes|string|max:50',
            'name'          => 'sometimes|string|max:150',
            'type'          => 'nullable|in:manufacturer,supplier,both',
            'contact_name'  => 'nullable|string|max:100',
            'contact_email' => 'nullable|email|max:150',
            'contact_phone' => 'nullable|string|max:50',
            'notes'         => 'nullable|string',
            'is_active'     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        if ($request->filled('code') && strtoupper($request->code) !== $vendor->code) {
            $exists = AssetVendor::byCompany($companyId)
                ->where('code', strtoupper($request->code))
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return ApiResponse::error('Ya existe un fabricante/proveedor con ese código para esta empresa', 422);
            }
        }

        $vendor->update($request->only([
            'code', 'name', 'type',
            'contact_name', 'contact_email', 'contact_phone',
            'notes', 'is_active',
        ]));

        return ApiResponse::success($vendor->fresh(), 'Fabricante/proveedor actualizado exitosamente');
    }

    /**
     * Eliminar (soft delete) un fabricante/proveedor.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $vendor = AssetVendor::byCompany($companyId)->find($id);
        if (!$vendor) {
            return ApiResponse::notFound('Fabricante/proveedor no encontrado');
        }

        $inUse = $vendor->manufacturedAssets()->count() + $vendor->suppliedAssets()->count();
        if ($inUse > 0) {
            return ApiResponse::error(
                "No se puede eliminar: este vendor está asignado a {$inUse} activo(s)",
                422
            );
        }

        $vendor->delete();

        return ApiResponse::success(null, 'Fabricante/proveedor eliminado exitosamente');
    }
}
