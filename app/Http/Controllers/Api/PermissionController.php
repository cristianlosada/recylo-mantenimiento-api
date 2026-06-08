<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Permission;
use App\Models\Module;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    /**
     * Mostrar lista de permisos con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'module_id' => 'integer|exists:modules,id',
            'sort_by' => 'string|in:name,code,created_at',
            'sort_order' => 'string|in:asc,desc'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = Permission::with(['module']);

        // Filtro de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtro por módulo
        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $permissions = $query->paginate($perPage);

        // Transformar datos
        $transformedPermissions = $permissions->getCollection()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'code' => $permission->code,
                'description' => $permission->description,
                'module' => $permission->module ? [
                    'id' => $permission->module->id,
                    'name' => $permission->module->name,
                    'code' => $permission->module->code
                ] : null,
                'roles_count' => $permission->roles()->count(),
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at
            ];
        });

        return ApiResponse::paginated(
            $transformedPermissions,
            [
                'current_page' => $permissions->currentPage(),
                'last_page' => $permissions->lastPage(),
                'per_page' => $permissions->perPage(),
                'total' => $permissions->total()
            ],
            'Permisos recuperados exitosamente'
        );
    }

    /**
     * Mostrar un permiso específico
     */
    public function show(int $id): JsonResponse
    {
        $permission = Permission::with(['module', 'roles'])->find($id);

        if (!$permission) {
            return ApiResponse::notFound('Permiso no encontrado');
        }

        return ApiResponse::success([
            'id' => $permission->id,
            'name' => $permission->name,
            'code' => $permission->code,
            'description' => $permission->description,
            'module' => $permission->module ? [
                'id' => $permission->module->id,
                'name' => $permission->module->name,
                'code' => $permission->module->code
            ] : null,
            'roles' => $permission->roles->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                    'company_id' => $role->company_id
                ];
            }),
            'created_at' => $permission->created_at,
            'updated_at' => $permission->updated_at
        ], 'Permiso recuperado exitosamente');
    }

    /**
     * Crear nuevo permiso
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:permissions',
            'description' => 'nullable|string',
            'module_id' => 'required|integer|exists:modules,id'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            // Crear permiso
            $permission = Permission::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'module_id' => $request->module_id
            ]);

            // Recargar permiso con módulo
            $permission->load('module');

            return ApiResponse::created([
                'id' => $permission->id,
                'name' => $permission->name,
                'code' => $permission->code,
                'description' => $permission->description,
                'module' => $permission->module ? [
                    'id' => $permission->module->id,
                    'name' => $permission->module->name,
                    'code' => $permission->module->code
                ] : null
            ], 'Permiso creado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear el permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar permiso existente
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return ApiResponse::notFound('Permiso no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'code' => [
                'string',
                'max:50',
                Rule::unique('permissions')->ignore($permission->id)
            ],
            'description' => 'nullable|string',
            'module_id' => 'integer|exists:modules,id'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            $updateData = $request->only([
                'name',
                'code',
                'description',
                'module_id'
            ]);

            $permission->update($updateData);

            // Recargar permiso con módulo
            $permission->load('module');

            return ApiResponse::updated([
                'id' => $permission->id,
                'name' => $permission->name,
                'code' => $permission->code,
                'description' => $permission->description,
                'module' => $permission->module ? [
                    'id' => $permission->module->id,
                    'name' => $permission->module->name,
                    'code' => $permission->module->code
                ] : null,
                'updated_at' => $permission->updated_at
            ], 'Permiso actualizado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar el permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar permiso
     */
    public function destroy(int $id): JsonResponse
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return ApiResponse::notFound('Permiso no encontrado');
        }

        try {
            // Verificar si el permiso está asignado a roles
            if ($permission->roles()->exists()) {
                return ApiResponse::error(
                    'No se puede eliminar el permiso porque está asignado a roles',
                    400
                );
            }

            // Eliminar permiso
            $permission->delete();

            return ApiResponse::deleted('Permiso eliminado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar el permiso: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener permisos por módulo
     */
    public function getPermissionsByModule(int $moduleId): JsonResponse
    {
        $module = Module::find($moduleId);

        if (!$module) {
            return ApiResponse::notFound('Módulo no encontrado');
        }

        $permissions = Permission::where('module_id', $moduleId)
                               ->get()
                               ->map(function ($permission) {
                                   return [
                                       'id' => $permission->id,
                                       'name' => $permission->name,
                                       'code' => $permission->code,
                                       'description' => $permission->description,
                                       'roles_count' => $permission->roles()->count()
                                   ];
                               });

        return ApiResponse::success([
            'module' => [
                'id' => $module->id,
                'name' => $module->name,
                'code' => $module->code
            ],
            'permissions' => $permissions
        ], 'Permisos del módulo recuperados exitosamente');
    }

    /**
     * Obtener todos los permisos agrupados por módulo
     */
    public function getPermissionsByModules(): JsonResponse
    {
        // Obtener todos los módulos con sus permisos
        $modules = Module::with(['permissions' => function ($query) {
            $query->where('is_active', true);
        }])->where('is_active', true)->get();

        if ($modules->isEmpty()) {
            return ApiResponse::notFound('No se encontraron módulos');
        }

        // Transformar la estructura agrupando permisos por módulo
        $modulesByPermissions = $modules->map(function ($module) {
            return [
                'module' => [
                    'id' => $module->id,
                    'name' => $module->name,
                    'code' => $module->code,
                    'description' => $module->description
                ],
                'permissions_count' => $module->permissions->count(),
                'permissions' => $module->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'code' => $permission->code,
                        'description' => $permission->description,
                        'action' => $permission->action,
                        'is_active' => $permission->is_active
                    ];
                })
            ];
        });

        return ApiResponse::success([
            'modules' => $modulesByPermissions,
            'total_modules' => $modules->count(),
            'total_permissions' => $modules->sum(function ($module) {
                return $module->permissions->count();
            })
        ], 'Permisos agrupados por módulo recuperados exitosamente');
    }

    /**
     * Obtener permisos por rol
     */
    public function getPermissionsByRole(int $roleId): JsonResponse
    {
        $role = Role::find($roleId);

        if (!$role) {
            return ApiResponse::notFound('Rol no encontrado');
        }

        // Obtener permisos del rol
        $permissions = $role->permissions()
                           ->with('module')
                           ->get();

        // Agrupar por módulo
        $permissionsByModule = $permissions->groupBy('module.name')
                                          ->map(function ($permissions, $moduleName) {
                                              $firstPermission = $permissions->first();
                                              return [
                                                  'module' => [
                                                      'id' => $firstPermission->module->id ?? null,
                                                      'name' => $firstPermission->module->name ?? null,
                                                      'code' => $firstPermission->module->code ?? null
                                                  ],
                                                  'permissions' => $permissions->map(function ($permission) {
                                                      return [
                                                          'id' => $permission->id,
                                                          'name' => $permission->name,
                                                          'code' => $permission->code,
                                                          'description' => $permission->description
                                                      ];
                                                  })
                                              ];
                                          })
                                          ->values();

        return ApiResponse::success([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code
            ],
            'permissions_by_module' => $permissionsByModule,
            'total_permissions' => $permissions->count()
        ], 'Permisos del rol recuperados exitosamente');
    }

    /**
     * Obtener todos los módulos con sus permisos
     */
    public function getModulesWithPermissions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'active_only' => 'boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = Module::with(['permissions' => function ($query) {
            $query->where('is_active', true);
        }]);

        // Filtrar solo módulos activos si se solicita
        if ($request->get('active_only', true)) {
            $query->where('is_active', true);
        }

        $modules = $query->get()->map(function ($module) {
            return [
                'id' => $module->id,
                'name' => $module->name,
                'code' => $module->code,
                'description' => $module->description,
                'is_active' => $module->is_active,
                'permissions_count' => $module->permissions->count(),
                'permissions' => $module->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'code' => $permission->code,
                        'description' => $permission->description,
                        'action' => $permission->action,
                        'is_active' => $permission->is_active
                    ];
                })
            ];
        });

        return ApiResponse::success(
            $modules,
            'Módulos con permisos recuperados exitosamente'
        );
    }
}
