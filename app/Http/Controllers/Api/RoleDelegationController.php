<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\RoleDelegation;
use App\Models\User;
use App\Models\Role;
use App\Helpers\PermissionHelper;
use App\Http\Requests\StoreDelegationRequest;
use App\Http\Requests\UpdateDelegationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleDelegationController extends Controller
{
    /**
     * Listar delegaciones con filtros
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'integer|exists:users,id',
            'company_id' => 'integer|exists:companies,id',
            'status' => 'string|in:active,expired,all',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = RoleDelegation::with([
            'delegatorUser',
            'delegateUser',
            'role.company'
        ]);

        // Filtrar por usuario delegado
        if ($request->filled('user_id')) {
            $query->where('delegatee_user_id', $request->user_id);
        }

        // Filtrar por empresa (a través del rol)
        if ($request->filled('company_id')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        }

        // Filtrar por estado
        $status = $request->get('status', 'active');
        if ($status === 'active') {
            $query->where('delegated_at', '<=', now())
                  ->whereNull('revoked_at')
                  ->where(function ($q) {
                      $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>=', now());
                  });
        } elseif ($status === 'expired') {
            $query->where('expires_at', '<', now());
        }

        // Ordenar por fecha de delegación descendente
        $query->orderBy('delegated_at', 'desc');

        // Paginación
        $perPage = $request->get('per_page', 15);
        $delegations = $query->paginate($perPage);

        // Transformar datos
        $transformedDelegations = $delegations->getCollection()->map(function ($delegation) {
            $isActive = $delegation->delegated_at <= now() && 
                       $delegation->revoked_at === null &&
                       ($delegation->expires_at === null || $delegation->expires_at >= now());

            return [
                'id' => $delegation->id,
                'delegator' => [
                    'id' => $delegation->delegatorUser->id,
                    'name' => $delegation->delegatorUser->full_name,
                    'email' => $delegation->delegatorUser->email
                ],
                'delegate' => [
                    'id' => $delegation->delegateUser->id,
                    'name' => $delegation->delegateUser->full_name,
                    'email' => $delegation->delegateUser->email
                ],
                'role' => [
                    'id' => $delegation->role->id,
                    'name' => $delegation->role->name,
                    'code' => $delegation->role->code,
                    'company' => [
                        'id' => $delegation->role->company->id,
                        'name' => $delegation->role->company->name
                    ]
                ],
                'delegated_at' => $delegation->delegated_at,
                'expires_at' => $delegation->expires_at,
                'revoked_at' => $delegation->revoked_at,
                'reason' => $delegation->reason,
                'is_active' => $isActive,
                'created_at' => $delegation->created_at
            ];
        });

        return ApiResponse::paginated(
            $transformedDelegations,
            [
                'current_page' => $delegations->currentPage(),
                'last_page' => $delegations->lastPage(),
                'per_page' => $delegations->perPage(),
                'total' => $delegations->total()
            ],
            'Delegaciones recuperadas exitosamente'
        );
    }

    /**
     * Obtener delegaciones activas
     */
    public function getActiveDelegations(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'integer|exists:companies,id'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = RoleDelegation::with([
            'delegatorUser',
            'delegateUser',
            'role.company'
        ])
        ->where('delegated_at', '<=', now())
        ->whereNull('revoked_at')
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>=', now());
        });

        // Filtrar por empresa si se especifica
        if ($request->filled('company_id')) {
            $query->whereHas('role', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        }

        $delegations = $query->orderBy('delegated_at', 'desc')->get();

        $transformedDelegations = $delegations->map(function ($delegation) {
            return [
                'id' => $delegation->id,
                'delegator' => [
                    'id' => $delegation->delegatorUser->id,
                    'name' => $delegation->delegatorUser->full_name
                ],
                'delegate' => [
                    'id' => $delegation->delegateUser->id,
                    'name' => $delegation->delegateUser->full_name
                ],
                'role' => [
                    'id' => $delegation->role->id,
                    'name' => $delegation->role->name,
                    'company_name' => $delegation->role->company->name
                ],
                'delegated_at' => $delegation->delegated_at,
                'expires_at' => $delegation->expires_at,
                'days_remaining' => $delegation->expires_at 
                    ? now()->diffInDays($delegation->expires_at, false) 
                    : null
            ];
        });

        return ApiResponse::success(
            $transformedDelegations,
            'Delegaciones activas recuperadas exitosamente'
        );
    }

    /**
     * Crear nueva delegación
     */
    public function store(StoreDelegationRequest $request): JsonResponse
    {
        // El FormRequest ya valida todos los datos y reglas de negocio
        $delegator = $request->user();
        $delegate = User::find($request->delegatee_user_id);
        $role = Role::find($request->role_id);

        try {
            DB::beginTransaction();

            $delegation = RoleDelegation::create([
                'delegator_user_id' => $delegator->id,
                'delegatee_user_id' => $delegate->id,
                'role_id' => $role->id,
                'company_id' => $request->company_id,
                'delegated_at' => $request->delegated_at ?? now(),
                'expires_at' => $request->expires_at,
                'reason' => $request->reason
            ]);

            DB::commit();

            // Limpiar caché de permisos del usuario delegado
            PermissionHelper::clearAllUserCache($delegate);

            return ApiResponse::created([
                'id' => $delegation->id,
                'delegator' => $delegator->full_name,
                'delegate' => $delegate->full_name,
                'role' => $role->name,
                'delegated_at' => $delegation->delegated_at,
                'expires_at' => $delegation->expires_at
            ], 'Delegación creada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear la delegación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar delegación
     */
    public function update(UpdateDelegationRequest $request, int $id): JsonResponse
    {
        $delegation = RoleDelegation::find($id);

        if (!$delegation) {
            return ApiResponse::notFound('Delegación no encontrada');
        }

        // Solo el delegador puede actualizar
        if ($delegation->delegator_user_id !== $request->user()->id) {
            return ApiResponse::error('Solo el delegador puede actualizar esta delegación', 403);
        }

        try {
            $delegation->update($request->only(['expires_at', 'reason']));

            // Limpiar caché del delegado
            PermissionHelper::clearAllUserCache($delegation->delegateUser);

            return ApiResponse::updated([
                'id' => $delegation->id,
                'expires_at' => $delegation->expires_at,
                'reason' => $delegation->reason
            ], 'Delegación actualizada exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar la delegación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Revocar delegación (terminarla inmediatamente)
     */
    public function revoke(int $id): JsonResponse
    {
        $delegation = RoleDelegation::find($id);

        if (!$delegation) {
            return ApiResponse::notFound('Delegación no encontrada');
        }

        // Solo el delegador puede revocar
        if ($delegation->delegator_user_id !== request()->user()->id) {
            return ApiResponse::error('Solo el delegador puede revocar esta delegación', 403);
        }

        // Verificar que está activa
        $isActive = $delegation->delegated_at <= now() && 
                   $delegation->revoked_at === null &&
                   ($delegation->expires_at === null || $delegation->expires_at >= now());

        if (!$isActive) {
            return ApiResponse::error('La delegación no está activa', 400);
        }

        try {
            $delegation->update([
                'revoked_at' => now()
            ]);

            // Limpiar caché del delegado
            PermissionHelper::clearAllUserCache($delegation->delegateUser);

            return ApiResponse::success(null, 'Delegación revocada exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al revocar la delegación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar delegación
     */
    public function destroy(int $id): JsonResponse
    {
        $delegation = RoleDelegation::find($id);

        if (!$delegation) {
            return ApiResponse::notFound('Delegación no encontrada');
        }

        // Solo el delegador puede eliminar
        if ($delegation->delegator_user_id !== request()->user()->id) {
            return ApiResponse::error('Solo el delegador puede eliminar esta delegación', 403);
        }

        try {
            $delegate = $delegation->delegateUser;
            $delegation->delete();

            // Limpiar caché del delegado
            PermissionHelper::clearAllUserCache($delegate);

            return ApiResponse::deleted('Delegación eliminada exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar la delegación: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Obtener roles delegados a un usuario específico
     */
    public function getDelegatedRoles(int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return ApiResponse::notFound('Usuario no encontrado');
        }

        $delegations = $user->activeDelegations()
            ->with(['role.company', 'role.permissions', 'delegator'])
            ->get();

        $delegatedRoles = $delegations->map(function ($delegation) {
            return [
                'delegation_id' => $delegation->id,
                'role' => [
                    'id' => $delegation->role->id,
                    'name' => $delegation->role->name,
                    'code' => $delegation->role->code,
                    'company' => [
                        'id' => $delegation->role->company->id,
                        'name' => $delegation->role->company->name
                    ],
                    'permissions_count' => $delegation->role->permissions->count()
                ],
                'delegated_by' => [
                    'id' => $delegation->delegatorUser->id,
                    'name' => $delegation->delegatorUser->full_name
                ],
                'delegated_at' => $delegation->delegated_at,
                'expires_at' => $delegation->expires_at,
                'days_remaining' => $delegation->expires_at 
                    ? now()->diffInDays($delegation->expires_at, false) 
                    : null,
                'reason' => $delegation->reason
            ];
        });

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name
            ],
            'delegated_roles' => $delegatedRoles,
            'total_delegations' => $delegatedRoles->count()
        ], 'Roles delegados recuperados exitosamente');
    }

    /**
     * Obtener delegaciones creadas por un usuario
     */
    public function getCreatedDelegations(int $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return ApiResponse::notFound('Usuario no encontrado');
        }

        $delegations = $user->createdDelegations()
            ->with(['delegateUser', 'role.company'])
            ->orderBy('delegated_at', 'desc')
            ->get();

        $createdDelegations = $delegations->map(function ($delegation) {
            $isActive = $delegation->delegated_at <= now() && 
                       $delegation->revoked_at === null &&
                       ($delegation->expires_at === null || $delegation->expires_at >= now());

            return [
                'id' => $delegation->id,
                'delegate' => [
                    'id' => $delegation->delegateUser->id,
                    'name' => $delegation->delegateUser->full_name
                ],
                'role' => [
                    'id' => $delegation->role->id,
                    'name' => $delegation->role->name,
                    'company_name' => $delegation->role->company->name
                ],
                'delegated_at' => $delegation->delegated_at,
                'expires_at' => $delegation->expires_at,
                'is_active' => $isActive,
                'reason' => $delegation->reason
            ];
        });

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->full_name
            ],
            'created_delegations' => $createdDelegations,
            'total' => $createdDelegations->count()
        ], 'Delegaciones creadas recuperadas exitosamente');
    }
}
