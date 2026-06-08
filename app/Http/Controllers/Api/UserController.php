<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Helpers\PermissionHelper;
use App\Models\ProductionLine;
use App\Models\Role;
use App\Models\UserCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Mostrar lista de usuarios con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:200',
            'search' => 'string|max:255',
            'status' => 'string|in:active,inactive,suspended',
            'is_active' => 'boolean',
            'company_id' => 'integer|exists:companies,id',
            'role_code' => 'string|exists:roles,code',
            'role_codes' => 'array',
            'role_codes.*' => 'string|exists:roles,code',
            'sort_by' => 'string|in:first_name,last_name,email,created_at,last_login_at',
            'sort_order' => 'string|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = User::with(['companies', 'userRoles.role', 'documentType', 'contacts'])
                    ->withCount('companies');

        // Filtro de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('document_number', 'like', "%{$search}%");
            });
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por empresa
        if ($request->filled('company_id')) {
            $query->whereHas('companies', function ($q) use ($request) {
                $q->where('companies.id', $request->company_id);
            });
        }

        // Filtro por rol (único o múltiples)
        if ($request->filled('role_codes')) {
            $query->whereHas('userRoles.role', function ($q) use ($request) {
                $q->whereIn('code', $request->role_codes);
            });
        } elseif ($request->filled('role_code')) {
            $query->whereHas('userRoles.role', function ($q) use ($request) {
                $q->where('code', $request->role_code);
            });
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage);

        // Transformar datos
        $transformedUsers = $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'second_last_name' => $user->second_last_name,
                'middle_name' => $user->middle_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'document_type' => $user->document_type_info,
                'document_number' => $user->document_number,
                'phone' => $user->phone,
                'birth_date' => $user->birth_date,
                'gender' => $user->gender,
                'status' => $user->status,
                'last_login_at' => $user->last_login_at,
                'created_at' => $user->created_at,
                'companies_count' => $user->companies_count ?? 0,
                'companies' => $user->companies->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'tax_id' => $company->tax_id,
                        'status' => $company->pivot->status
                    ];
                }),
                'roles' => $user->userRoles->map(function ($userRole) {
                    return [
                        'role_id' => $userRole->role->id,
                        'role_code' => $userRole->role->code,
                        'role_name' => $userRole->role->name,
                        'company_id' => $userRole->company_id
                    ];
                })
            ];
        });

        return ApiResponse::paginated(
            $transformedUsers,
            [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem()
            ],
            'Usuarios recuperados exitosamente'
        );
    }

    /**
     * Mostrar un usuario específico
     */
    public function show(Request $request, $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $user = User::with(['companies', 'userRoles.role.permissions', 'contacts', 'documentType'])
            ->find($id);

        // Cargar nombres de sede, línea de producción y cargo para cada empresa
        $userCompanies = \App\Models\UserCompany::where('user_id', $id)
            ->with(['site:id,name', 'productionLine:id,name', 'jobPosition:id,name,code'])
            ->get()
            ->keyBy('company_id');

        if (!$user) {
            return ApiResponse::notFound('Usuario no encontrado');
        }

        $canManageSalary = $companyId
            ? PermissionHelper::hasPermission($request->user(), 'USERS_MANAGE_SALARY', $companyId)
            : false;

        $userData = [
            'id' => $user->id,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'second_last_name' => $user->second_last_name,
            'middle_name' => $user->middle_name,
            'full_name' => $user->full_name,
            'email' => $user->email,
            'document_type' => $user->document_type_info,
            'document_number' => $user->document_number,
            'phone' => $user->phone,
            'birth_date' => $user->birth_date,
            'gender' => $user->gender,
            'status' => $user->status,
            'last_login_at' => $user->last_login_at,
            'email_verified_at' => $user->email_verified_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'companies' => $user->companies->map(function ($company) use ($canManageSalary, $userCompanies) {
                $uc = $userCompanies->get($company->id);
                $data = [
                    'id'                  => $company->id,
                    'legal_name'          => $company->legal_name,
                    'trade_name'          => $company->trade_name,
                    'name'                => $company->name,
                    'tax_id'              => $company->tax_id,
                    'status'              => $company->pivot->status,
                    'joined_at'           => $company->pivot->created_at,
                    'site_id'              => $company->pivot->site_id,
                    'site_name'            => $uc?->site?->name,
                    'production_line_id'   => $company->pivot->production_line_id,
                    'production_line_name' => $uc?->productionLine?->name,
                    'job_position'         => $company->pivot->job_position,
                    'job_position_id'      => $company->pivot->job_position_id,
                    'job_position_name'    => $uc?->jobPosition?->name ?? $company->pivot->job_position,
                    'employee_code'        => $company->pivot->employee_code,
                    'hire_date'            => $company->pivot->hire_date,
                    'employment_type'      => $company->pivot->employment_type,
                ];
                if ($canManageSalary) {
                    $data['salary_amount']   = $company->pivot->salary_amount;
                    $data['salary_currency'] = $company->pivot->salary_currency;
                    $data['hourly_rate']     = $company->pivot->hourly_rate;
                }
                return $data;
            }),
            'roles' => $user->userRoles->map(function ($userRole) {
                return [
                    'role_id' => $userRole->role->id,
                    'role_code' => $userRole->role->code,
                    'role_name' => $userRole->role->name,
                    'role_description' => $userRole->role->description,
                    'company_id' => $userRole->company_id,
                    'assigned_at' => $userRole->created_at,
                    'permissions' => $userRole->role->permissions->map(function ($permission) {
                        return [
                            'code' => $permission->code,
                            'name' => $permission->name,
                            'module' => $permission->module ? $permission->module->code : null
                        ];
                    })
                ];
            }),
            'contacts' => $user->contacts->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'contact_type_id' => $contact->contact_type_id,
                    'value' => $contact->value,
                    'is_primary' => $contact->is_primary
                ];
            }),
            'documents' => []
        ];

        return ApiResponse::success($userData, 'Usuario recuperado exitosamente');
    }

    /**
     * Crear nuevo usuario
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:150',
            'password' => 'required|string|min:8|confirmed',
            'document_type_id' => 'required|integer|exists:document_types,id',
            'document_number' => 'required|string|max:50|unique:users',
            'middle_name' => 'nullable|string|max:120',
            'second_last_name' => 'nullable|string|max:120',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'nationality_country_id' => 'nullable|integer|exists:countries,id',
            'status' => 'in:active,inactive',
            'companies' => 'array',
            'companies.*' => 'integer|exists:companies,id',
            'site_id' => 'nullable|integer|exists:company_sites,id',
            'production_line_id' => 'nullable|integer|exists:production_lines,id',
            'job_position'    => 'nullable|string|max:150',
            'job_position_id' => 'nullable|integer|exists:job_positions,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $userByEmailAddress = User::where("email", $request->email)->first();

        if ($userByEmailAddress) {
            return ApiResponse::error('El correo electrónico ya está en uso por el usuario ' . $userByEmailAddress->full_name . '.', 422);
        }

        try {
            // Crear usuario
            $user = User::create([
                'first_name' => $request->first_name,
                'middle_name' => $request->middle_name,
                'last_name' => $request->last_name,
                'second_last_name' => $request->second_last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'document_type_id' => $request->document_type_id,
                'document_number' => $request->document_number,
                'birth_date' => $request->birth_date,
                'gender' => $request->gender,
                'nationality_country_id' => $request->nationality_country_id,
                'status' => $request->get('status', 'active')
            ]);

            // Asociar contactos si se proporcionan
            if ($request->has('contacts') && is_array($request->contacts)) {
                foreach ($request->contacts as $contact) {
                    if (isset($contact['contact_type_id'], $contact['value'])) {
                        $user->contacts()->create([
                            'contact_type_id' => $contact['contact_type_id'],
                            'value' => $contact['value'],
                            'is_primary' => $contact['is_primary'] ?? false
                        ]);
                    }
                }
            }

            // validar si se le asigno una empresa
            if ($request->has('companies') && is_array($request->companies)) {
                if (empty($request->companies)) {
                    // asigna la empresa 1
                    $request->merge(['companies' => [1]]);
                }
            }

            // Asociar empresas si se proporcionan
            if ($request->has('companies') && is_array($request->companies)) {
                foreach ($request->companies as $companyId) {
                    $user->companies()->attach($companyId, [
                        'status'             => 'active',
                        'site_id'            => $request->site_id,
                        'production_line_id' => $request->production_line_id,
                        'job_position'       => $request->job_position,
                        'job_position_id'    => $request->job_position_id,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                }
            }

            // crear o asignar roles
            if ($request->has('roles')) {
                $user->userRoles()->delete();
                foreach ($request->roles as $roleData) {
                    if (isset($roleData)) {
                        $user->userRoles()->create([
                            'role_id' => $roleData
                        ]);
                    }
                }
            }

            // Cargar relaciones para la respuesta
            $user->load(['companies', 'userRoles.role']);

            return ApiResponse::created([
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'document_type' => $user->document_type_info,
                'document_number' => $user->document_number,
                'phone' => $user->phone,
                'status' => $user->status,
                'created_at' => $user->created_at,
                'companies' => $user->companies->map(function ($company) {
                    return [
                        'id' => $company->id,
                        'name' => $company->name,
                        'status' => $company->pivot->status
                    ];
                })
            ], 'Usuario creado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al crear el usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar usuario existente
     */
    public function update(Request $request, $id): JsonResponse
    {
        $companyId = $request->header('x-company-id');

        $user = User::find($id);

        if (!$user) {
            return ApiResponse::notFound('Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'string|max:100',
            'last_name' => 'string|max:100',
            'email' => [
                'string',
                'email',
                'max:150'
            ],
            'document_type_id' => 'integer|exists:document_types,id',
            'document_number' => [
                'string',
                'max:50',
                Rule::unique('users')->ignore($user->id)
            ],
            'middle_name' => 'nullable|string|max:120',
            'second_last_name' => 'nullable|string|max:120',
            'birth_date' => 'nullable|date|before:today',
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'nationality_country_id' => 'nullable|integer|exists:countries,id',
            'status' => 'in:active,inactive',
            'is_active' => 'boolean',
            'password' => 'nullable|string|min:8|confirmed',
            'companies' => 'array',
            'companies.*' => 'integer|exists:companies,id',
            'site_id' => 'nullable|integer|exists:company_sites,id',
            'production_line_id' => 'nullable|integer|exists:production_lines,id',
            'job_position' => 'nullable|string|max:150',
            'job_position_id' => 'nullable|integer|exists:job_positions,id',
            'salary_amount' => 'nullable|numeric|min:0',
            'salary_currency' => 'nullable|string|max:10',
            'hourly_rate' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $userByEmailAddress = User::where("email", $request->email)->where("id", "!=", $user->id)->first();

        if ($userByEmailAddress) {
            return ApiResponse::error('El correo electrónico ya está en uso por el usuario ' . $userByEmailAddress->full_name . '.', 422);
        }

        try {
            if ($request->has('is_active')) {
                $user->status = $request->is_active ? 'active' : 'suspended';
            }
            // Actualizar campos
            $updateData = $request->only([
                'first_name', 'middle_name', 'last_name', 'second_last_name',
                'email', 'document_type_id', 'document_number', 'birth_date', 
                'gender', 'nationality_country_id', 'status',
            ]);

            // Actualizar el campo name si se cambian los nombres
            if ($request->hasAny(['first_name', 'middle_name', 'last_name', 'second_last_name'])) {
                $updateData['name'] = trim(
                    ($request->first_name ?? $user->first_name) . ' ' .
                    ($request->middle_name ?? $user->middle_name) . ' ' .
                    ($request->last_name ?? $user->last_name) . ' ' .
                    ($request->second_last_name ?? $user->second_last_name)
                );
            }

            // Actualizar contraseña si se proporciona
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            // actualizar los roles
            if ($request->has('roles')) {
                $user->userRoles()->delete();
                foreach ($request->roles as $roleData) {
                    if (isset($roleData)) {
                        $user->userRoles()->create([
                            'role_id' => $roleData
                        ]);
                    }
                }
            }

            // Asociar contactos si se proporcionan
            if ($request->has('contacts') && is_array($request->contacts)) {
                foreach ($request->contacts as $contact) {
                    if (isset($contact['contact_type_id'], $contact['value'])) {
                        $user->contacts()->create([
                            'contact_type_id' => $contact['contact_type_id'],
                            'value' => $contact['value'],
                            'is_primary' => $contact['is_primary'] ?? false
                        ]);
                    }
                }
            }

            // Actualizar empresas asociadas
            if ($request->has('companies') && is_array($request->companies)) {
                // Preparar datos para sync con timestamps
                $companiesData = [];
                foreach ($request->companies as $cid) {
                    $companiesData[$cid] = [
                        'status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
                $user->companies()->sync($companiesData);
            }

            // Actualizar campos operativos del pivote para la empresa actual
            if ($companyId && $user->companies()->where('companies.id', $companyId)->exists()) {
                $pivotData = ['updated_at' => now()];

                if ($request->has('site_id'))            $pivotData['site_id']            = $request->site_id;
                if ($request->has('production_line_id')) $pivotData['production_line_id'] = $request->production_line_id;
                if ($request->has('job_position'))       $pivotData['job_position']       = $request->job_position;
                if ($request->has('job_position_id'))    $pivotData['job_position_id']    = $request->job_position_id;

                $canManageSalary = PermissionHelper::hasPermission($request->user(), 'USERS_MANAGE_SALARY', $companyId);
                if ($canManageSalary) {
                    if ($request->has('salary_amount'))   $pivotData['salary_amount']   = $request->salary_amount;
                    if ($request->has('salary_currency')) $pivotData['salary_currency'] = $request->salary_currency;
                    if ($request->has('hourly_rate'))     $pivotData['hourly_rate']     = $request->hourly_rate;
                }

                if (count($pivotData) > 1) {
                    $user->companies()->updateExistingPivot($companyId, $pivotData);
                }
            }

            // Recargar usuario con relaciones
            $user->load(['companies', 'userRoles.role']);

            return ApiResponse::updated([
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'document_type' => $user->document_type_info,
                'document_number' => $user->document_number,
                'phone' => $user->phone,
                'status' => $user->status,
                'updated_at' => $user->updated_at
            ], 'Usuario actualizado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al actualizar el usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar usuario (soft delete)
     */
    public function destroy($id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponse::notFound('Usuario no encontrado');
        }

        try {
            // Verificar si el usuario tiene tokens activos
            if ($user->tokens()->count() > 0) {
                // Eliminar todos los tokens del usuario
                $user->tokens()->delete();
            }

            // Soft delete del usuario
            $user->delete();

            return ApiResponse::deleted('Usuario eliminado exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar el usuario: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Asignar usuario a empresa
     */
    public function assignToCompany(Request $request, $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponse::notFound('Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id',
            'status' => 'in:active,inactive'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            // Verificar si ya está asignado
            if ($user->companies()->where('company_id', $request->company_id)->exists()) {
                return ApiResponse::error('El usuario ya está asignado a esta empresa', 400);
            }

            // Asignar a la empresa
            $user->companies()->attach($request->company_id, [
                'status' => $request->get('status', 'active'),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return ApiResponse::success(null, 'Usuario asignado a la empresa exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al asignar usuario a empresa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remover usuario de empresa
     */
    public function removeFromCompany(Request $request, $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return ApiResponse::notFound('Usuario no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'company_id' => 'required|integer|exists:companies,id'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            // Verificar si está asignado
            if (!$user->companies()->where('company_id', $request->company_id)->exists()) {
                return ApiResponse::error('El usuario no está asignado a esta empresa', 400);
            }

            // Remover de la empresa
            $user->companies()->detach($request->company_id);

            return ApiResponse::success(null, 'Usuario removido de la empresa exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al remover usuario de empresa: ' . $e->getMessage(), 500);
        }
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
     * Importación masiva de usuarios desde archivo CSV/Excel
     *
     * Formato CSV esperado (con encabezado):
     *   nombre,linea_produccion
     *   Juan García,Línea A
     *
     * Parámetros de formulario requeridos:
     *   file         → archivo CSV
     *   role_id      → ID del rol a asignar (Técnico u Operario)
     *   password     → contraseña genérica para todos
     *   company_id   → empresa a la que pertenecen
     */
    public function bulkImport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'file'         => 'required|file|mimes:csv,txt,xlsx|max:5120',
            'role_id'      => 'required|integer|exists:roles,id',
            'email_suffix' => 'required|string|max:100',
            'password'     => 'required|string|min:8',
            'company_id'   => 'required|integer|exists:companies,id',
            'site_id'      => 'nullable|integer|exists:company_sites,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $file       = $request->file('file');
        $roleId     = (int) $request->role_id;
        $suffix     = $request->email_suffix;
        $password   = Hash::make($request->password);
        $companyId  = (int) $request->company_id;
        $siteId     = $request->site_id ? (int) $request->site_id : null;

        // Obtener el primer document_type_id genérico (CC o cualquiera)
        $defaultDocTypeId = DB::table('document_types')->value('id') ?? 1;

        // Parsear CSV
        $rows = $this->parseCsvFile($file->getRealPath());

        if (empty($rows)) {
            return ApiResponse::error('El archivo no contiene filas válidas', 422);
        }

        $created  = [];
        $skipped  = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $index => $row) {
                // Soporte para: "nombre completo", "nombre", columna 0
                $fullName = trim(
                    $row['nombre completo'] ??
                    $row['nombre_completo'] ??
                    $row['nombre'] ??
                    $row[0] ?? ''
                );
                // Cargo: "cargo", columna 1
                $jobPosition = trim(
                    $row['cargo'] ??
                    $row[1] ?? ''
                );
                // Centro de costo / línea: "centro de costo", "linea_produccion", columna 2
                $lineName = trim(
                    $row['centro de costo'] ??
                    $row['centro_de_costo'] ??
                    $row['linea_produccion'] ??
                    $row['linea de produccion'] ??
                    $row[2] ?? ''
                );

                if (empty($fullName)) {
                    $skipped[] = ['fila' => $index + 2, 'razon' => 'Nombre vacío'];
                    continue;
                }

                // Dividir nombre en first_name + last_name
                $nameParts = preg_split('/\s+/', $fullName, 2);
                $firstName = $nameParts[0];
                $lastName  = $nameParts[1] ?? $nameParts[0];

                // Generar username en snake_case y email
                $baseSlug = Str::slug(Str::ascii($fullName), '_');
                $email    = $baseSlug . $suffix;

                // Evitar duplicados: si el email ya existe, agregar sufijo numérico
                $finalEmail = $email;
                $counter    = 1;
                while (User::withTrashed()->where('email', $finalEmail)->exists()) {
                    $finalEmail = $baseSlug . '_' . $counter . $suffix;
                    $counter++;
                }

                // Número de documento genérico único
                $docNumber = 'GEN-' . strtoupper(Str::random(8));
                while (User::withTrashed()->where('document_number', $docNumber)->exists()) {
                    $docNumber = 'GEN-' . strtoupper(Str::random(8));
                }

                // Resolver línea de producción por nombre dentro de la empresa
                $productionLineId = null;
                if (!empty($lineName)) {
                    $line = ProductionLine::byCompany($companyId)
                        ->where(function ($q) use ($lineName) {
                            $q->where('name', $lineName)
                              ->orWhere('name', 'like', "%{$lineName}%")
                              ->orWhere('code', $lineName);
                        })
                        ->first();
                    $productionLineId = $line?->id;
                }

                // Crear usuario
                $user = User::create([
                    'first_name'       => $firstName,
                    'last_name'        => $lastName,
                    'email'            => $finalEmail,
                    'password'         => $password,
                    'document_type_id' => $defaultDocTypeId,
                    'document_number'  => $docNumber,
                    'status'           => 'active',
                ]);

                // Asociar a empresa con sede, línea de producción y cargo
                $user->companies()->attach($companyId, [
                    'status'             => 'active',
                    'site_id'            => $siteId,
                    'job_position'       => $jobPosition ?: null,
                    'production_line_id' => $productionLineId,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);

                // Asignar rol
                $user->userRoles()->create(['role_id' => $roleId]);

                $created[] = [
                    'nombre'                => $fullName,
                    'email'                 => $finalEmail,
                    'cargo'                 => $jobPosition ?: null,
                    'linea_produccion'      => $lineName ?: null,
                    'production_line_found' => $productionLineId !== null,
                ];
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error durante la importación: ' . $e->getMessage(), 500);
        }

        return ApiResponse::success([
            'creados'  => count($created),
            'omitidos' => count($skipped),
            'detalle_creados'  => $created,
            'detalle_omitidos' => $skipped,
        ], count($created) . ' usuario(s) creado(s) correctamente.');
    }

    /**
     * Parsea un archivo CSV y devuelve array de filas asociativas.
     * La primera fila se usa como encabezado (keys).
     */
    private function parseCsvFile(string $path): array
    {
        $rows    = [];
        $headers = null;

        if (($handle = fopen($path, 'r')) === false) {
            return [];
        }

        // Detectar y omitir BOM UTF-8 si existe
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        while (($line = fgetcsv($handle, 0, ',')) !== false) {
            // Intentar también con punto y coma (Excel en español)
            if (count($line) === 1) {
                $line = str_getcsv($line[0], ';');
            }

            $line = array_map('trim', $line);

            if ($headers === null) {
                // Primera fila: encabezados normalizados en minúsculas sin tildes
                $headers = array_map(function ($h) {
                    return strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $h));
                }, $line);
                continue;
            }

            if (count(array_filter($line)) === 0) {
                continue; // fila vacía
            }

            $rows[] = array_combine(
                array_slice($headers, 0, count($line)),
                array_slice($line, 0, count($headers))
            );
        }

        fclose($handle);
        return $rows;
    }
}