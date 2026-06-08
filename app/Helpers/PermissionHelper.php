<?php

namespace App\Helpers;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PermissionHelper
{
    /**
     * Obtener todos los permisos de un usuario en una empresa (con caché)
     */
    public static function getUserPermissions(User $user, ?int $companyId = null): array
    {
        $cacheKey = "user_{$user->id}_company_{$companyId}_permissions";
        
        return Cache::remember($cacheKey, 3600, function () use ($user, $companyId) {
            // Permisos de roles directos
            $rolePermissions = $user->userRoles()
                ->when($companyId, function ($query, $companyId) {
                    // Incluir roles con company_id específico O roles globales (company_id = null)
                    return $query->where(function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)
                          ->orWhereNull('company_id');
                    });
                })
                ->with(['role' => function ($query) {
                    $query->where('is_active', true)
                          ->with(['permissions' => function ($q) {
                              $q->where('is_active', true);
                          }]);
                }])
                ->get()
                ->filter(function ($userRole) {
                    return $userRole->role !== null;
                })
                ->flatMap(function ($userRole) {
                    return $userRole->role->permissions->pluck('code');
                });

            // Permisos de delegaciones activas
            $delegatedPermissions = $user->activeDelegations()
                ->when($companyId, function ($query, $companyId) {
                    // Incluir delegaciones con company_id específico O delegaciones globales (company_id = null)
                    return $query->where(function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)
                          ->orWhereNull('company_id');
                    });
                })
                ->with(['role' => function ($query) {
                    $query->where('is_active', true)
                          ->with(['permissions' => function ($q) {
                              $q->where('is_active', true);
                          }]);
                }])
                ->get()
                ->filter(function ($delegation) {
                    return $delegation->role !== null;
                })
                ->flatMap(function ($delegation) {
                    return $delegation->role->permissions->pluck('code');
                });

            Log::debug("Role permissions: " . $rolePermissions->implode(', '));

            return $rolePermissions->merge($delegatedPermissions)->unique()->values()->toArray();
        });
    }

    /**
     * Verificar si un usuario tiene un permiso específico
     */
    public static function hasPermission(User $user, string $permissionCode, ?int $companyId = null): bool
    {
        $permissions = self::getUserPermissions($user, $companyId);
        return in_array($permissionCode, $permissions);
    }

    /**
     * Verificar si un usuario tiene todos los permisos especificados
     */
    public static function hasAllPermissions(User $user, array $permissionCodes, ?int $companyId = null): bool
    {
        $permissions = self::getUserPermissions($user, $companyId);
        return count(array_intersect($permissionCodes, $permissions)) === count($permissionCodes);
    }

    /**
     * Verificar si un usuario tiene al menos uno de los permisos especificados
     */
    public static function hasAnyPermission(User $user, array $permissionCodes, ?int $companyId = null): bool
    {
        $permissions = self::getUserPermissions($user, $companyId);
        return !empty(array_intersect($permissionCodes, $permissions));
    }

    /**
     * Limpiar caché de permisos de un usuario
     */
    public static function clearUserPermissionsCache(User $user, ?int $companyId = null): void
    {
        if ($companyId) {
            $cacheKey = "user_{$user->id}_company_{$companyId}_permissions";
            Cache::forget($cacheKey);
        } else {
            // Limpiar para todas las empresas del usuario
            $companies = $user->userCompanies()->pluck('company_id');
            foreach ($companies as $compId) {
                $cacheKey = "user_{$user->id}_company_{$compId}_permissions";
                Cache::forget($cacheKey);
            }
        }
    }

    /**
     * Obtener roles de un usuario en una empresa (con caché)
     */
    public static function getUserRoles(User $user, ?int $companyId = null): array
    {
        $cacheKey = "user_{$user->id}_company_{$companyId}_roles";
        
        return Cache::remember($cacheKey, 3600, function () use ($user, $companyId) {
            // Roles directos
            $directRoles = $user->userRoles()
                ->when($companyId, function ($query, $companyId) {
                    // Incluir roles con company_id específico O roles globales (company_id = null)
                    return $query->where(function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)
                          ->orWhereNull('company_id');
                    });
                })
                ->with(['role' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->get()
                ->filter(function ($userRole) {
                    return $userRole->role !== null;
                })
                ->pluck('role.code');

            // Roles delegados
            $delegatedRoles = $user->activeDelegations()
                ->when($companyId, function ($query, $companyId) {
                    // Incluir delegaciones con company_id específico O delegaciones globales (company_id = null)
                    return $query->where(function ($q) use ($companyId) {
                        $q->where('company_id', $companyId)
                          ->orWhereNull('company_id');
                    });
                })
                ->with(['role' => function ($query) {
                    $query->where('is_active', true);
                }])
                ->get()
                ->filter(function ($delegation) {
                    return $delegation->role !== null;
                })
                ->pluck('role.code');

            return $directRoles->merge($delegatedRoles)->unique()->values()->toArray();
        });
    }

    /**
     * Verificar si un usuario tiene un rol específico
     */
    public static function hasRole(User $user, string $roleCode, ?int $companyId = null): bool
    {
        $roles = self::getUserRoles($user, $companyId);
        return in_array($roleCode, $roles);
    }

    /**
     * Limpiar caché de roles de un usuario
     */
    public static function clearUserRolesCache(User $user, ?int $companyId = null): void
    {
        if ($companyId) {
            $cacheKey = "user_{$user->id}_company_{$companyId}_roles";
            Cache::forget($cacheKey);
        } else {
            // Limpiar para todas las empresas del usuario
            $companies = $user->userCompanies()->pluck('company_id');
            foreach ($companies as $compId) {
                $cacheKey = "user_{$user->id}_company_{$compId}_roles";
                Cache::forget($cacheKey);
            }
        }
    }

    /**
     * Limpiar todo el caché de un usuario
     */
    public static function clearAllUserCache(User $user): void
    {
        self::clearUserPermissionsCache($user);
        self::clearUserRolesCache($user);
    }
}
