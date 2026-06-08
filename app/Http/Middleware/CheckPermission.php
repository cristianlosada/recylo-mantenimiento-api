<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$permissions
     */
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (!$request->user()) {
            return ApiResponse::unauthorized('Usuario no autenticado');
        }

        $user = $request->user();
        
        // Obtener company_id del contexto actual
        $companyId = $request->header('X-Company-ID') ?? $request->input('company_id');
        
        // Obtener permisos del usuario a través de sus roles
        $userPermissions = $user->userRoles()
            ->when($companyId, function ($query, $companyId) {
                return $query->whereHas('role', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                      ->where('is_active', true);
                });
            })
            ->with(['role.permissions' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get()
            ->flatMap(function ($userRole) {
                return $userRole->role->permissions->pluck('code');
            });

        // Obtener permisos de delegaciones activas
        $delegatedPermissions = $user->activeDelegations()
            ->when($companyId, function ($query, $companyId) {
                return $query->whereHas('role', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId)
                      ->where('is_active', true);
                });
            })
            ->with(['role.permissions' => function ($query) {
                $query->where('is_active', true);
            }])
            ->get()
            ->flatMap(function ($delegation) {
                return $delegation->role->permissions->pluck('code');
            });

        // Combinar permisos directos y delegados
        $allPermissions = $userPermissions->merge($delegatedPermissions)->unique()->toArray();

        // Verificar si el usuario tiene alguno de los permisos requeridos
        $hasPermission = !empty(array_intersect($permissions, $allPermissions));
        
        if (!$hasPermission) {
            return ApiResponse::forbidden(
                'No tienes los permisos necesarios para realizar esta acción',
                [
                    'required_permissions' => $permissions,
                    'user_permissions' => $allPermissions
                ]
            );
        }

        return $next($request);
    }
}
