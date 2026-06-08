<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ModuleController extends Controller
{
    /**
     * Obtener lista de módulos con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'active_only' => 'boolean',
            'core_only' => 'boolean',
            'with_permissions' => 'boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = Module::query();

        // Filtrar solo activos
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        // Filtrar solo módulos core
        if ($request->boolean('core_only')) {
            $query->where('is_core', true);
        }

        // Incluir permisos si se solicita
        if ($request->boolean('with_permissions')) {
            $query->with(['permissions' => function ($q) use ($request) {
                if ($request->boolean('active_only')) {
                    $q->where('is_active', true);
                }
            }]);
        }

        $modules = $query->orderBy('order')->orderBy('name')->get();

        // Transformar datos
        $transformedModules = $modules->map(function ($module) use ($request) {
            $data = [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'description' => $module->description,
                'icon' => $module->icon,
                'order' => $module->order,
                'is_core' => $module->is_core,
                'is_active' => $module->is_active,
                'created_at' => $module->created_at,
                'updated_at' => $module->updated_at
            ];

            // if ($request->boolean('with_permissions') && $module->relationLoaded('permissions')) {
                $data['permissions'] = $module->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'code' => $permission->code,
                        'name' => $permission->name,
                        'action' => $permission->action,
                        'is_active' => $permission->is_active
                    ];
                });
                $data['permissions_count'] = $module->permissions->count();
            // }

            return $data;
        });

        return ApiResponse::success(
            $transformedModules,
            'Módulos recuperados exitosamente'
        );
    }

    /**
     * Mostrar un módulo específico
     */
    public function show(int $id): JsonResponse
    {
        $module = Module::with(['permissions' => function ($query) {
            $query->orderBy('name');
        }])->find($id);

        if (!$module) {
            return ApiResponse::notFound('Módulo no encontrado');
        }

        return ApiResponse::success([
            'id' => $module->id,
            'code' => $module->code,
            'name' => $module->name,
            'description' => $module->description,
            'icon' => $module->icon,
            'order' => $module->order,
            'is_core' => $module->is_core,
            'is_active' => $module->is_active,
            'permissions' => $module->permissions->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'code' => $permission->code,
                    'name' => $permission->name,
                    'description' => $permission->description,
                    'action' => $permission->action,
                    'is_active' => $permission->is_active
                ];
            }),
            'permissions_count' => $module->permissions->count(),
            'created_at' => $module->created_at,
            'updated_at' => $module->updated_at
        ], 'Módulo recuperado exitosamente');
    }

    /**
     * Obtener módulos disponibles para una empresa
     */
    public function getCompanyModules(int $companyId): JsonResponse
    {
        $company = \App\Models\Company::find($companyId);

        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        // Obtener módulos habilitados para la empresa
        $enabledModules = $company->enabledModules()
            ->with('module')
            ->get()
            ->map(function ($enabledModule) {
                return [
                    'id' => $enabledModule->module->id,
                    'code' => $enabledModule->module->code,
                    'name' => $enabledModule->module->name,
                    'description' => $enabledModule->module->description,
                    'icon' => $enabledModule->module->icon,
                    'order' => $enabledModule->module->order,
                    'is_core' => $enabledModule->module->is_core,
                    'enabled' => $enabledModule->enabled,
                    'config' => $enabledModule->config
                ];
            });

        // Obtener todos los módulos core (siempre disponibles)
        $coreModules = Module::where('is_core', true)
            ->where('is_active', true)
            ->get()
            ->map(function ($module) {
                return [
                    'id' => $module->id,
                    'code' => $module->code,
                    'name' => $module->name,
                    'description' => $module->description,
                    'icon' => $module->icon,
                    'order' => $module->order,
                    'is_core' => true,
                    'is_active' => true
                ];
            });

        return ApiResponse::success([
            'company' => [
                'id' => $company->id,
                'name' => $company->name
            ],
            'core_modules' => $coreModules,
            'enabled_modules' => $enabledModules,
            'total_available_modules' => $coreModules->count() + $enabledModules->count()
        ], 'Módulos de la empresa recuperados exitosamente');
    }

    /**
     * Obtener módulos con conteo de permisos
     */
    public function getModulesStats(): JsonResponse
    {
        $modules = Module::withCount('permissions')
            ->orderBy('name')
            ->get()
            ->map(function ($module) {
                return [
                    'id' => $module->id,
                    'code' => $module->code,
                    'name' => $module->name,
                    'is_core' => $module->is_core,
                    'is_active' => $module->is_active,
                    'permissions_count' => $module->permissions_count
                ];
            });

        $stats = [
            'total_modules' => $modules->count(),
            'core_modules' => $modules->where('is_core', true)->count(),
            'active_modules' => $modules->where('is_active', true)->count(),
            'total_permissions' => $modules->sum('permissions_count')
        ];

        return ApiResponse::success([
            'modules' => $modules,
            'stats' => $stats
        ], 'Estadísticas de módulos recuperadas exitosamente');
    }

    /**
     * Crear nuevo módulo
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:modules,code',
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0',
            'is_core' => 'boolean',
            'is_active' => 'boolean',
            'permissions' => 'nullable|array',
            'permissions.*.action' => 'required_with:permissions|string|max:50',
            'permissions.*.name' => 'required_with:permissions|string|max:255',
            'permissions.*.description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $module = Module::create([
                'code' => strtoupper($request->code),
                'name' => $request->name,
                'description' => $request->description,
                'icon' => $request->icon,
                'order' => $request->get('order', 0),
                'is_core' => $request->get('is_core', false),
                'is_active' => $request->get('is_active', true)
            ]);

            // Crear permisos si se enviaron
            if ($request->has('permissions') && is_array($request->permissions)) {
                foreach ($request->permissions as $permissionData) {
                    $module->permissions()->create([
                        'code' => strtoupper($module->code) . '_' . strtoupper($permissionData['action']),
                        'name' => $permissionData['name'],
                        'module_id' => $module->id,
                        'action' => strtoupper($permissionData['action']),
                        'description' => $permissionData['description'] ?? null,
                        'is_active' => true
                    ]);
                }
            }

            DB::commit();

            // Recargar con permisos
            $module->load('permissions');

            return ApiResponse::created([
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'description' => $module->description,
                'icon' => $module->icon,
                'order' => $module->order,
                'is_core' => $module->is_core,
                'is_active' => $module->is_active,
                'permissions' => $module->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'code' => $permission->code,
                        'name' => $permission->name,
                        'action' => $permission->action,
                        'description' => $permission->description,
                        'is_active' => $permission->is_active
                    ];
                }),
                'permissions_count' => $module->permissions->count()
            ], 'Módulo creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear el módulo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar módulo existente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $module = Module::find($id);

        if (!$module) {
            return ApiResponse::notFound('Módulo no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:50|unique:modules,code,' . $id,
            'name' => 'sometimes|string|max:120',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'order' => 'nullable|integer|min:0',
            'is_core' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'permissions' => 'nullable|array',
            'permissions.*.id' => 'nullable|integer|exists:permissions,id',
            'permissions.*.action' => 'required_with:permissions|string|max:50',
            'permissions.*.name' => 'required_with:permissions|string|max:255',
            'permissions.*.description' => 'nullable|string',
            'permissions.*.is_active' => 'nullable|boolean',
            'permissions.*._deleted' => 'nullable|boolean',
            'permissions.*._isNew' => 'nullable|boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            $updateData = [];

            if ($request->has('code')) {
                $updateData['code'] = strtoupper($request->code);
            }
            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description;
            }
            if ($request->has('icon')) {
                $updateData['icon'] = $request->icon;
            }
            if ($request->has('order')) {
                $updateData['order'] = $request->integer('order');
            }
            if ($request->has('is_core')) {
                $updateData['is_core'] = $request->boolean('is_core');
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }

            $module->update($updateData);

            // Actualizar o crear permisos si se enviaron
            if ($request->has('permissions') && is_array($request->permissions)) {
                foreach ($request->permissions as $permissionData) {
                    if (isset($permissionData['id']) && !$permissionData['_isNew']) {
                        // Verificar si se debe eliminar

                        if (isset($permissionData['_deleted']) && $permissionData['_deleted'] === true) {
                            $permission = $module->permissions()->find($permissionData['id']);
                            if ($permission) {
                                $permission->delete();
                            }
                        } else {
                            // Actualizar permiso existente
                            $permission = $module->permissions()->find($permissionData['id']);
                            if ($permission) {
                                $permission->update([
                                    'name' => $permissionData['name'],
                                    'description' => $permissionData['description'] ?? $permission->description,
                                    'is_active' => $permissionData['is_active'] ?? $permission->is_active
                                ]);
                            }
                        }
                    } else {
                        // Crear nuevo permiso
                        $module->permissions()->create([
                            'code' => strtoupper($module->code) . '_' . strtoupper($permissionData['action']),
                            'name' => $permissionData['name'],
                            'action' => strtoupper($permissionData['action']),
                            'description' => $permissionData['description'] ?? null,
                            'is_active' => $permissionData['is_active'] ?? true
                        ]);
                    }
                }
            }

            DB::commit();

            // Recargar con permisos
            $module->load('permissions');

            return ApiResponse::updated([
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'description' => $module->description,
                'icon' => $module->icon,
                'order' => $module->order,
                'is_core' => $module->is_core,
                'is_active' => $module->is_active,
                'permissions' => $module->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'code' => $permission->code,
                        'name' => $permission->name,
                        'action' => $permission->action,
                        'description' => $permission->description,
                        'is_active' => $permission->is_active
                    ];
                }),
                'permissions_count' => $module->permissions->count()
            ], 'Módulo actualizado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar el módulo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar módulo (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $module = Module::find($id);

        if (!$module) {
            return ApiResponse::notFound('Módulo no encontrado');
        }

        // Verificar si es módulo core
        if ($module->is_core) {
            return ApiResponse::error(
                'No se puede eliminar un módulo core del sistema',
                400
            );
        }

        try {
            DB::beginTransaction();

            // Verificar si tiene permisos asociados
            $permissionsCount = $module->permissions()->count();
            
            if ($permissionsCount > 0) {
                // Desactivar los permisos en lugar de eliminarlos
                $module->permissions()->update(['is_active' => false]);
            }

            // Verificar si está habilitado en alguna empresa
            $companiesCount = $module->companyEnabledModules()->count();
            
            if ($companiesCount > 0) {
                // Deshabilitar en todas las empresas
                $module->companyEnabledModules()->update(['enabled' => false]);
            }

            // Desactivar el módulo
            $module->update(['is_active' => false]);

            DB::commit();

            return ApiResponse::deleted(
                "Módulo desactivado exitosamente. Se deshabilitó en {$companiesCount} empresa(s) y se desactivaron {$permissionsCount} permiso(s)"
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al eliminar el módulo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Activar/Desactivar módulo
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $module = Module::find($id);

        if (!$module) {
            return ApiResponse::notFound('Módulo no encontrado');
        }

        try {
            $newStatus = !$module->is_active;
            $module->update(['is_active' => $newStatus]);

            // Si se desactiva, actualizar permisos y empresas
            if (!$newStatus) {
                $module->permissions()->update(['is_active' => false]);
                $module->companyEnabledModules()->update(['enabled' => false]);
            }

            return ApiResponse::success([
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'is_active' => $module->is_active
            ], $newStatus ? 'Módulo activado exitosamente' : 'Módulo desactivado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al cambiar estado del módulo: ' . $e->getMessage(), 500);
        }
    }
}
