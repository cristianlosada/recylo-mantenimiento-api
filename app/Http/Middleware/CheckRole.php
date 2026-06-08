<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        $user = $request->user();
        
        // Obtener roles del usuario con company_id del contexto actual
        $companyId = $request->header('X-Company-ID') ?? $request->input('company_id');
        
        $userRoles = $user->userRoles()
            ->when($companyId, function ($query, $companyId) {
                return $query->where('company_id', $companyId);
            })
            ->with('role')
            ->get()
            ->pluck('role.code')
            ->toArray();

        // Verificar si el usuario tiene alguno de los roles requeridos
        $hasRole = !empty(array_intersect($roles, $userRoles));
        
        if (!$hasRole) {
            return response()->json([
                'message' => 'No tienes permisos para realizar esta acción',
                'required_roles' => $roles,
                'user_roles' => $userRoles
            ], 403);
        }

        return $next($request);
    }
}
