<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Iniciar sesión
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember_me' => 'boolean'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray(), 'Datos de entrada inválidos');
        }

        $credentials = $request->only('email', 'password');

        // Verificar si el usuario existe
        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return ApiResponse::unauthorized('Usuario no encontrado');
        }

        // Verificar si el usuario está activo
        if ($user->status !== 'active') {
            return ApiResponse::unauthorized('Usuario inactivo. Contacte al administrador.');
        }

        // Verificar credenciales
        if (!Hash::check($credentials['password'], $user->password)) {
            return ApiResponse::unauthorized('Credenciales inválidas');
        }

        // Crear token con expiración explícita (no depende del global de Sanctum)
        $tokenName  = 'api-token-' . now()->timestamp;
        $webExpires = $request->boolean('remember_me')
            ? now()->addDays(30)
            : now()->addHours(2);
        $token = $user->createToken($tokenName, ['*'], $webExpires)->plainTextToken;

        // Actualizar último login
        $user->update([
            'last_login_at' => now()
        ]);

        // Registrar sesión en user_sessions
        UserSession::create([
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => json_encode([
                'token_name' => $tokenName,
                'login_method' => 'email',
                'remember_me' => $request->boolean('remember_me')
            ]),
            'login_time' => now(),
            'last_activity' => now(),
            'is_active' => true,
            'session_type' => 'api',
            'expires_at' => $request->boolean('remember_me') 
                ? now()->addDays(30) 
                : now()->addHours(2)
        ]);

        // Obtener roles y permisos del usuario
        $userWithRoles = $user->load(['userRoles.role.permissions', 'companies']);

        $expirationMinutes = config('sanctum.expiration') ?? 120;

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'companies' => $user->companies->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'legal_name' => $company->legal_name,
                        'trade_name' => $company->trade_name,
                        'name' => $company->name,
                        'tax_id' => $company->tax_id,
                        'status' => $company->pivot->status
                    ];
                }),
                'roles' => $userWithRoles->userRoles->map(function ($userRole) {
                    return [
                        'role_code' => $userRole->role->code,
                        'role_name' => $userRole->role->name,
                        'company_id' => $userRole->company_id,
                        'permissions' => $userRole->role->permissions->pluck('code')
                    ];
                })
            ],
            'expires_in' => $expirationMinutes * 60
        ], 'Inicio de sesión exitoso')
        ->cookie(
            'auth_token',
            $token,
            $expirationMinutes,
            '/',
            null,
            config('app.env') === 'production',
            true,
            false,
            'lax'
        );
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Actualizar sesión como cerrada
        UserSession::where('user_id', $user->id)
            ->where('ip_address', $request->ip())
            ->where('user_agent', $request->userAgent())
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'logout_time' => now(),
                'expires_at' => now(),
                'last_activity' => now()
            ]);
        
        // Revocar SOLO el token actual (mantener otras sesiones activas)
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Sesión cerrada exitosamente')
            ->cookie(
                'auth_token',
                '',
                -1,
                '/',
                null,
                config('app.env') === 'production',
                true,
                false,
                'lax'
            );
    }

    /**
     * Cerrar todas las sesiones
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Marcar todas las sesiones como expiradas
        UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'logout_time' => now(),
                'expires_at' => now(),
                'last_activity' => now()
            ]);
        
        // Eliminar todos los tokens del usuario
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Todas las sesiones cerradas exitosamente');
    }

    /**
     * Obtener información del usuario autenticado
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = $request->header('X-Company-ID');

        // Cargar roles y permisos
        $user->load(['userRoles.role.permissions', 'companies']);

        // Filtrar roles por empresa si se proporciona
        // Incluir también roles globales (company_id = null) que aplican a todas las empresas
        $roles = $user->userRoles->when($companyId, function ($collection) use ($companyId) {
            return $collection->filter(function ($userRole) use ($companyId) {
                return $userRole->company_id == $companyId || $userRole->company_id === null;
            });
        });

        // Obtener módulos del usuario según la empresa
        $modules = $user->modules($companyId);

        // Si hay companyId, filtrar solo módulos habilitados para esa empresa
        if ($companyId) {
            $enabledModuleIds = DB::table('company_enabled_modules')
                ->where('company_id', $companyId)
                ->where('enabled', true)
                ->pluck('module_id')
                ->toArray();

            $modules = $modules->whereIn('id', $enabledModuleIds);
        }

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'document_type' => $user->document_type_info,
                'document_number' => $user->document_number,
                'phone' => $user->phone,
                'birth_date' => $user->birth_date,
                'gender' => $user->gender,
                'address' => $user->address,
                'city' => $user->city,
                'status' => $user->status,
                'companies' => $user->companies->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'legal_name' => $company->legal_name,
                        'trade_name' => $company->trade_name,
                        'name' => $company->name, // Usa el accessor
                        'tax_id' => $company->tax_id,
                        'status' => $company->pivot->status
                    ];
                }),
                'roles' => $roles->map(function ($userRole) {
                    return [
                        'role_code' => $userRole->role->code,
                        'role_name' => $userRole->role->name,
                        'company_id' => $userRole->company_id,
                        'permissions' => $userRole->role->permissions->pluck('code')
                    ];
                }),
                'modules' => $modules->map(function ($module) use ($companyId) {
                    $enabledModule = null;
                    
                    if ($companyId) {
                        $enabledModule = DB::table('company_enabled_modules')
                            ->where('company_id', $companyId)
                            ->where('module_id', $module->id)
                            ->first();
                    }

                    return [
                        'id' => $module->id,
                        'code' => $module->code,
                        'name' => $module->name,
                        'description' => $module->description,
                        'icon' => $module->icon,
                        'route' => $module->route,
                        'order' => $module->order,
                        'is_active' => $module->is_active,
                        'is_core' => $module->is_core,
                        'enabled_for_company' => $enabledModule ? (bool)$enabledModule->enabled : false,
                        'config' => $enabledModule ? json_decode($enabledModule->config, true) : null
                    ];
                }),
                'current_company_id' => $companyId
            ]
        ], 'Información del usuario recuperada exitosamente');
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray(), 'Datos de entrada inválidos');
        }

        $user = $request->user();

        // Verificar contraseña actual
        if (!Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('La contraseña actual es incorrecta', 400);
        }

        // Actualizar contraseña
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        return ApiResponse::updated(null, 'Contraseña actualizada exitosamente');
    }

    /**
     * Refrescar token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Actualizar última actividad de la sesión
        UserSession::where('user_id', $user->id)
            ->where('ip_address', $request->ip())
            ->where('user_agent', $request->userAgent())
            ->where('is_active', true)
            ->update([
                'last_activity' => now(),
                'expires_at' => now()->addMinutes((int) (config('sanctum.expiration') ?? 120))
            ]);
        
        // Eliminar token actual
        $request->user()->currentAccessToken()->delete();

        // Crear nuevo token con expiración explícita.
        // config('sanctum.expiration') puede ser null → no usar como default.
        $expirationMinutes = config('sanctum.expiration') ?? 120;
        $tokenName  = 'api-token-' . now()->timestamp;
        $webExpires = now()->addMinutes((int) $expirationMinutes);
        $token = $user->createToken($tokenName, ['*'], $webExpires)->plainTextToken;

        return ApiResponse::success([
            'expires_in' => $expirationMinutes * 60
        ], 'Token renovado exitosamente')
        ->cookie(
            'auth_token',
            $token,
            $expirationMinutes,
            '/',
            null,
            config('app.env') === 'production',
            true,
            false,
            'lax'
        );
    }

    /**
     * Obtener sesiones activas del usuario
     */
    public function activeSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $sessions = UserSession::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function ($session) use ($request) {
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'login_time' => $session->login_time,
                    'last_activity' => $session->last_activity,
                    'expires_at' => $session->expires_at,
                    'session_type' => $session->session_type,
                    'is_current' => $session->ip_address === $request->ip() 
                        && $session->user_agent === $request->userAgent()
                ];
            });

        return ApiResponse::success([
            'sessions' => $sessions,
            'total' => $sessions->count()
        ], 'Sesiones activas recuperadas exitosamente');
    }

    /**
     * POST /auth/device-token
     * Login para dispositivos móviles. Devuelve el token en el cuerpo (no en cookie).
     * El token tiene una vida útil de 90 días.
     */
    public function deviceToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'       => 'required|email',
            'password'    => 'required|string|min:6',
            'device_id'   => 'nullable|string|max:255',
            'device_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray(), 'Datos de entrada inválidos');
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return ApiResponse::unauthorized('Usuario no encontrado');
        }

        if ($user->status !== 'active') {
            return ApiResponse::unauthorized('Usuario inactivo. Contacte al administrador.');
        }

        if (!Hash::check($request->password, $user->password)) {
            return ApiResponse::unauthorized('Credenciales inválidas');
        }

        $expiresAt  = now()->addDays(90);
        $tokenName  = 'mobile-' . ($request->device_id ?? 'device') . '-' . now()->timestamp;
        $newToken   = $user->createToken($tokenName, ['*'], $expiresAt);

        $user->update(['last_login_at' => now()]);

        UserSession::create([
            'user_id'      => $user->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'payload'      => json_encode([
                'token_name'  => $tokenName,
                'login_method' => 'device',
                'device_id'   => $request->device_id,
                'device_name' => $request->device_name,
            ]),
            'login_time'   => now(),
            'last_activity' => now(),
            'is_active'    => true,
            'session_type' => 'mobile',
            'expires_at'   => $expiresAt,
        ]);

        $userWithRoles = $user->load(['userRoles.role.permissions', 'companies']);

        return ApiResponse::success([
            'token'      => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in' => 90 * 24 * 60 * 60,
            'user'       => [
                'id'         => $user->id,
                'first_name' => $user->first_name,
                'last_name'  => $user->last_name,
                'full_name'  => $user->full_name,
                'email'      => $user->email,
                'companies'  => $user->companies->map(fn($c) => [
                    'id'         => $c->id,
                    'legal_name' => $c->legal_name,
                    'trade_name' => $c->trade_name,
                    'name'       => $c->name,
                    'tax_id'     => $c->tax_id,
                    'status'     => $c->pivot->status,
                ]),
                'roles' => $userWithRoles->userRoles->map(fn($ur) => [
                    'role_code'   => $ur->role->code,
                    'role_name'   => $ur->role->name,
                    'company_id'  => $ur->company_id,
                    'permissions' => $ur->role->permissions->pluck('code'),
                ]),
            ],
        ], 'Autenticación de dispositivo exitosa');
    }

    /**
     * POST /auth/device-refresh
     * Rota el token móvil y devuelve uno nuevo en el cuerpo con 90 días de vida.
     * Requiere autenticación Bearer (auth:sanctum).
     */
    public function deviceRefresh(Request $request): JsonResponse
    {
        $user      = $request->user();
        $expiresAt = now()->addDays(90);
        $tokenName = 'mobile-refresh-' . now()->timestamp;

        // Revocar token actual
        $request->user()->currentAccessToken()->delete();

        $newToken = $user->createToken($tokenName, ['*'], $expiresAt);

        UserSession::where('user_id', $user->id)
            ->where('session_type', 'mobile')
            ->where('is_active', true)
            ->update([
                'last_activity' => now(),
                'expires_at'    => $expiresAt,
            ]);

        return ApiResponse::success([
            'token'      => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'expires_in' => 90 * 24 * 60 * 60,
        ], 'Token renovado exitosamente');
    }

    /**
     * Cerrar una sesión específica
     */
    public function revokeSession(Request $request, string $sessionId): JsonResponse
    {
        $user = $request->user();
        
        $session = UserSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if (!$session) {
            return ApiResponse::notFound('Sesión no encontrada');
        }

        // Marcar sesión como expirada
        $session->update([
            'is_active' => false,
            'logout_time' => now(),
            'expires_at' => now(),
            'last_activity' => now()
        ]);

        return ApiResponse::success(null, 'Sesión revocada exitosamente');
    }
}
