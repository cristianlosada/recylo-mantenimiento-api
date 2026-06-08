<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Role;
use App\Models\User;
use App\Models\Company;
use App\Models\Permission;
use App\Models\RolePermission;
use App\Models\RoleDelegation;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Requests\UpdateRolePermissionsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Mostrar lista de roles con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'company_id' => 'integer|exists:companies,id',
            'sort_by' => 'string|in:name,code,created_at',
            'sort_order' => 'string|in:asc,desc'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = Role::with(['permissions', 'company']);

        // Filtro de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtro por empresa
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $roles = $query->paginate($perPage);

        // Transformar datos
        $transformedRoles = $roles->getCollection()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'company' => $role->company ? [
                    'id' => $role->company->id,
                    'name' => $role->company->name
                ] : null,
                'permissions_count' => $role->permissions->count(),
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at
            ];
        });

        return ApiResponse::paginated(
            $transformedRoles,
            [
                'current_page' => $roles->currentPage(),
                'last_page' => $roles->lastPage(),
                'per_page' => $roles->perPage(),
                'total' => $roles->total()
            ],
            'Roles recuperados exitosamente'
        );
    }

    /**
     * Mostrar un rol específico
     */
    public function show(int $id): JsonResponse
    {
        $role = Role::with(['permissions.module', 'company'])->find($id);

        if (!$role) {
            return ApiResponse::notFound('Rol no encontrado');
        }

        // Agrupar permisos por módulo
        $permissionsByModule = $role->permissions->groupBy(function ($permission) {
            return $permission->module_id;
        })->map(function ($permissions, $moduleId) {
            $firstPermission = $permissions->first();
            return [
                'module' => [
                    'id' => $firstPermission->module_id,
                    'name' => $firstPermission->module?->name
                ],
                'permissions' => $permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'code' => $permission->code,
                        'description' => $permission->description,
                        'action' => $permission->action
                    ];
                })->values()
            ];
        })->values();

        return ApiResponse::success([
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'company' => $role->company ? [
                'id' => $role->company->id,
                'name' => $role->company->name
            ] : null,
            'permissions_by_module' => $permissionsByModule,
            'created_at' => $role->created_at,
            'updated_at' => $role->updated_at
        ], 'Rol recuperado exitosamente');
    }

    /**
     * Crear nuevo rol
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Crear rol
            $role = Role::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'company_id' => $request->company_id,
                'is_system' => false
            ]);

            // Asignar permisos
            if ($request->has('permissions')) {
                foreach ($request->permissions as $permissionId) {
                    RolePermission::create([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId
                    ]);
                }
            }

            DB::commit();

            // Recargar rol con relaciones
            $role->load(['permissions.module', 'company']);

            return ApiResponse::created([
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'company' => $role->company ? [
                    'id' => $role->company->id,
                    'name' => $role->company->name
                ] : null,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'code' => $permission->code,
                        'module' => $permission->module ? [
                            'id' => $permission->module->id,
                            'name' => $permission->module->name
                        ] : null
                    ];
                })
            ], 'Rol creado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear el rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar rol existente
     */
    public function update(UpdateRoleRequest $request, int $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return ApiResponse::notFound('Rol no encontrado');
        }

        // Verificar si es un rol del sistema
        if ($role->is_system) {
            return ApiResponse::error('No se puede modificar un rol del sistema', 403);
        }

        try {
            $updateData = $request->only([
                'name',
                'code',
                'description'
            ]);

            $role->update($updateData);

            return ApiResponse::updated([
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'company_id' => $role->company_id,
                'updated_at' => $role->updated_at
            ], 'Rol actualizado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar el rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar rol
     */
    public function destroy(int $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return ApiResponse::notFound('Rol no encontrado');
        }

        // Verificar si es un rol del sistema
        if ($role->is_system) {
            return ApiResponse::error('No se puede eliminar un rol del sistema', 403);
        }

        try {
            // Verificar si hay usuarios asignados
            if ($role->userRoles()->exists()) {
                return ApiResponse::error(
                    'No se puede eliminar el rol porque tiene usuarios asignados',
                    400
                );
            }

            // Eliminar relaciones
            $role->permissions()->detach();
            
            // Eliminar rol
            $role->delete();

            return ApiResponse::deleted('Rol eliminado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar el rol: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener roles por empresa
     */
    public function getRolesByCompany(int $companyId): JsonResponse
    {
        $company = Company::find($companyId);

        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $roles = Role::where('company_id', $companyId)
                    ->with(['permissions'])
                    ->get()
                    ->map(function ($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'code' => $role->code,
                            'description' => $role->description,
                            'is_system' => $role->is_system,
                            'permissions_count' => $role->permissions->count(),
                            'created_at' => $role->created_at
                        ];
                    });

        return ApiResponse::success(
            $roles,
            'Roles de la empresa recuperados exitosamente'
        );
    }

    /**
     * Actualizar permisos de un rol
     */
    public function updatePermissions(UpdateRolePermissionsRequest $request, int $id): JsonResponse
    {
        $role = Role::find($id);

        if (!$role) {
            return ApiResponse::notFound('Rol no encontrado');
        }

        // Obtener roles del usuario autenticado mediante userRoles
        $user = $request->user();
        
        // Cargar roles a través de userRoles (relación que sabemos que funciona)
        $userRolesWithRole = $user->userRoles()->with('role')->get();
        
        // Extraer códigos de roles
        $userRoleCodes = $userRolesWithRole->pluck('role.code')->toArray();

        // Verificar si es un rol del sistema y el usuario no es super admin
        if ($role->is_system && !in_array('SUPER_ADMIN', $userRoleCodes)) {
            return ApiResponse::error('No se pueden modificar los permisos de un rol del sistema', 403);
        }

        try {
            DB::beginTransaction();

            // Actualizar permisos
            $role->permissions()->sync($request->permissions);

            DB::commit();

            // Recargar rol con permisos
            $role->load('permissions.module');

            return ApiResponse::success([
                'id' => $role->id,
                'permissions' => $role->permissions->map(function ($permission) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'code' => $permission->code,
                        'module' => $permission->module ? [
                            'id' => $permission->module->id,
                            'name' => $permission->module->name,
                            'code' => $permission->module->code
                        ] : null
                    ];
                })
            ], 'Permisos actualizados exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar los permisos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener todos los roles con sus permisos agrupados por módulo
     */
    public function getRolesWithPermissionsByModule(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'integer|exists:companies,id',
            'active_only' => 'boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = Role::with(['permissions.module', 'company']);

        // Filtrar por empresa si se especifica
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Filtrar solo activos si se solicita
        if ($request->boolean('active_only')) {
            $query->where('is_active', true)
                  ->whereHas('permissions', function ($q) {
                      $q->where('is_active', true);
                  });
        }

        $roles = $query->get();

        // Transformar roles con permisos agrupados por módulo
        $transformedRoles = $roles->map(function ($role) use ($request) {
            $permissions = $request->boolean('active_only')
                ? $role->permissions->where('is_active', true)
                : $role->permissions;

            // Agrupar permisos por módulo
            $permissionsByModule = $permissions->groupBy(function ($permission) {
                return $permission->module_id;
            })->map(function ($modulePermissions) {
                $firstPermission = $modulePermissions->first();
                return [
                    'module' => $firstPermission->module ? [
                        'id' => $firstPermission->module->id,
                        'name' => $firstPermission->module->name,
                        'code' => $firstPermission->module->code
                    ] : null,
                    'permissions_count' => $modulePermissions->count(),
                    'permissions' => $modulePermissions->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'code' => $permission->code,
                            'action' => $permission->action,
                            'is_active' => $permission->is_active
                        ];
                    })->values()
                ];
            })->values();

            return [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'is_active' => $role->is_active,
                'company' => $role->company ? [
                    'id' => $role->company->id,
                    'name' => $role->company->name
                ] : null,
                'total_permissions' => $permissions->count(),
                'modules_count' => $permissionsByModule->count(),
                'permissions_by_module' => $permissionsByModule,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at
            ];
        });

        return ApiResponse::success(
            $transformedRoles,
            'Roles con permisos agrupados por módulo recuperados exitosamente'
        );
    }
}