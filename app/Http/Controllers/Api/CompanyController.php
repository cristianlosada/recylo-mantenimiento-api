<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Models\Country;
use App\Models\Plan;
use App\Models\SubscriptionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /**
     * Mostrar lista de empresas con filtros y paginación
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|max:255',
            'status' => 'string|in:active,inactive',
            'country_id' => 'integer|exists:countries,id',
            'sort_by' => 'string|in:legal_name,trade_name,tax_id,created_at',
            'sort_order' => 'string|in:asc,desc'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        $query = Company::with([
                    'country',
                    'municipality.department',
                    'companySize'
                ])
                ->withCount('userCompanies');

        // Filtro de búsqueda
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('legal_name', 'like', "%{$search}%")
                  ->orWhere('trade_name', 'like', "%{$search}%")
                  ->orWhere('tax_id', 'like', "%{$search}%");
            });
        }

        // Filtro por estado
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por país
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->country_id);
        }

        // Ordenamiento
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginación
        $perPage = $request->get('per_page', 15);
        $companies = $query->paginate($perPage);

        // Transformar datos
        $transformedCompanies = $companies->getCollection()->map(function ($company) {
            return [
                'id' => $company->id,
                'legal_name' => $company->legal_name,
                'trade_name' => $company->trade_name,
                'name' => $company->name,
                'tax_id' => $company->tax_id,
                'status' => $company->status,
                'country' => $company->country ? [
                    'id' => $company->country->id,
                    'name' => $company->country->name,
                    'code' => $company->country->code
                ] : null,
                'municipality' => $company->municipality ? [
                    'id' => $company->municipality->id,
                    'name' => $company->municipality->name,
                    'department' => $company->municipality->departmentGeo ? [
                        'id' => $company->municipality->departmentGeo->id,
                        'name' => $company->municipality->departmentGeo->name
                    ] : null
                ] : null,
                'company_size' => $company->companySize ? [
                    'id' => $company->companySize->id,
                    'name' => $company->companySize->name,
                    'code' => $company->companySize->code
                ] : null,
                'economic_activity' => $company->economic_activity,
                'users_count' => $company->user_companies_count ?? 0,
                'created_at' => $company->created_at,
                'updated_at' => $company->updated_at
            ];
        });

        return ApiResponse::paginated(
            $transformedCompanies,
            [
                'current_page' => $companies->currentPage(),
                'last_page' => $companies->lastPage(),
                'per_page' => $companies->perPage(),
                'total' => $companies->total(),
                'from' => $companies->firstItem(),
                'to' => $companies->lastItem()
            ],
            'Empresas recuperadas exitosamente'
        );
    }

    /**
     * Mostrar una empresa específica
     */
    public function show(int $id): JsonResponse
    {
        $company = Company::with([
            'country',
            'municipality.department',
            'departmentGeo',
            'companySize',
            'sites.siteType',
            'sites.municipality',
            'users',
            'subscriptions.plan',
            'subscriptions.subscriptionStatus',
            'enabledModules.module'
        ])->find($id);

        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $data = [
            'id' => $company->id,
            'legal_name' => $company->legal_name,
            'trade_name' => $company->trade_name,
            'name' => $company->name,
            'tax_id' => $company->tax_id,
            'status' => $company->status,
            'country' => $company->country ? [
                'id' => $company->country->id,
                'name' => $company->country->name,
                'code' => $company->country->code
            ] : null,
            'municipality' => $company->municipality ? [
                'id' => $company->municipality->id,
                'name' => $company->municipality->name,
                'department' => $company->municipality->departmentGeo ? [
                    'id' => $company->municipality->departmentGeo->id,
                    'name' => $company->municipality->departmentGeo->name,
                    'country' => $company->municipality->departmentGeo->country ? [
                        'id' => $company->municipality->departmentGeo->country->id,
                        'name' => $company->municipality->departmentGeo->country->name,
                        'code' => $company->municipality->departmentGeo->country->code
                    ] : null
                ] : null
            ] : null,
            'company_size' => $company->companySize ? [
                'id' => $company->companySize->id,
                'name' => $company->companySize->name,
                'code' => $company->companySize->code,
                'min_employees' => $company->companySize->min_employees,
                'max_employees' => $company->companySize->max_employees
            ] : null,
            'economic_activity' => $company->economic_activity,
            'address' => [
                'line_1' => $company->address_line_1,
                'line_2' => $company->address_line_2,
                'postal_code' => $company->postal_code
            ],
            'founded_at' => $company->founded_at,
            'employee_count' => $company->employee_count,
            'sites' => $company->sites->map(function ($site) {
                return [
                    'id' => $site->id,
                    'name' => $site->name,
                    'site_type' => $site->siteType ? [
                        'id' => $site->siteType->id,
                        'code' => $site->siteType->code,
                        'name' => $site->siteType->name
                    ] : null,
                    'municipality' => $site->municipality ? [
                        'id' => $site->municipality->id,
                        'name' => $site->municipality->name
                    ] : null,
                    'address' => [
                        'line_1' => $site->address_line_1,
                        'line_2' => $site->address_line_2,
                        'postal_code' => $site->postal_code,
                        'full_address' => $site->full_address
                    ],
                    'coordinates' => [
                        'latitude' => $site->latitude,
                        'longitude' => $site->longitude
                    ],
                    'is_headquarters' => $site->is_headquarters,
                    'is_active' => $site->is_active
                ];
            }),
            'users' => $company->users->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'status' => $user->pivot->status,
                    'employee_code' => $user->pivot->employee_code,
                    'hire_date' => $user->pivot->hire_date,
                    'termination_date' => $user->pivot->termination_date,
                    'is_primary' => $user->pivot->is_primary
                ];
            }),
            'subscriptions' => $company->subscriptions->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'plan' => [
                        'id' => $subscription->plan->id,
                        'name' => $subscription->plan->name,
                        'code' => $subscription->plan->code
                    ],
                    'subscription_status' => $subscription->subscriptionStatus ? [
                        'id' => $subscription->subscriptionStatus->id,
                        'name' => $subscription->subscriptionStatus->name,
                        'code' => $subscription->subscriptionStatus->code
                    ] : null,
                    'start_date' => $subscription->start_date,
                    'end_date' => $subscription->end_date,
                    'amount' => $subscription->amount
                ];
            }),
            'enabled_modules' => $company->enabledModules->map(function ($enabledModule) {
                return [
                    'id' => $enabledModule->module->id,
                    'name' => $enabledModule->module->name,
                    'code' => $enabledModule->module->code,
                    'enabled' => $enabledModule->enabled,
                    'config' => $enabledModule->config,
                    'enabled_at' => $enabledModule->created_at
                ];
            }),
            'created_at' => $company->created_at,
            'updated_at' => $company->updated_at
        ];

        return ApiResponse::success($data, 'Empresa recuperada exitosamente');
    }

    /**
     * Crear nueva empresa
     */
    public function store(CompanyRequest $request): JsonResponse
    {

        try {
            DB::beginTransaction();

            // Crear empresa
            $company = Company::create([
                'legal_name' => $request->legal_name,
                'trade_name' => $request->trade_name,
                'tax_id' => $request->tax_id,
                'country_id' => $request->country_id,
                'status' => $request->get('status', 'active')
            ]);

            // obtenemos el detalle del plan para obtener el precio
            $plan = Plan::find($request->subscription['plan_id']);
            if (!$plan) {
                DB::rollBack();
                return ApiResponse::error('Plan no encontrado', 404);
            }

            // Crear suscripción si se proporciona
            if ($request->has('subscription')) {
                $company->subscriptions()->create([
                    'plan_id' => $request->subscription['plan_id'],
                    'subscription_status_id' => SubscriptionStatus::STATUS_ACTIVE,
                    'start_date' => $request->subscription['start_date'] ?? now(),
                    'end_date' => $request->subscription['end_date'] ?? null,
                    'amount' => $request->subscription['amount'] ?? $plan->price,
                ]);
            }

            // Habilitar módulos si se proporcionan
            if ($request->has('modules') && is_array($request->modules)) {
                foreach ($request->modules as $moduleId) {
                    $company->enabledModules()->create([
                        'module_id' => $moduleId,
                        'enabled' => true
                    ]);
                }
            }

            DB::commit();

            // Recargar empresa con relaciones
            $company->load(['country', 'subscriptions.plan', 'enabledModules.module']);

            return ApiResponse::created([
                'id' => $company->id,
                'legal_name' => $company->legal_name,
                'trade_name' => $company->trade_name,
                'name' => $company->name,
                'tax_id' => $company->tax_id,
                'status' => $company->status,
                'country' => [
                    'id' => $company->country->id,
                    'name' => $company->country->name,
                    'code' => $company->country->code
                ],
                'subscription' => $company->subscriptions->first() ? [
                    'plan' => [
                        'id' => $company->subscriptions->first()->plan->id,
                        'name' => $company->subscriptions->first()->plan->name
                    ],
                    'start_date' => $company->subscriptions->first()->start_date,
                    'end_date' => $company->subscriptions->first()->end_date,
                    'amount' => $company->subscriptions->first()->amount
                ] : null,
                'enabled_modules' => $company->enabledModules->map(function ($module) {
                    return [
                        'id' => $module->module->id,
                        'name' => $module->module->name,
                        'code' => $module->module->code
                    ];
                })
            ], 'Empresa creada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al crear la empresa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar empresa existente
     */
    public function update(CompanyRequest $request, int $id): JsonResponse
    {
        $company = Company::find($id);

        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        try {
            DB::beginTransaction();

            // Campos básicos
            $updateData = $request->only([
                'legal_name',
                'trade_name',
                'tax_id',
                'country_id',
                'municipality_id',
                'department_geo_id',
                'company_size_id',
                'economic_activity',
                'address_line_1',
                'address_line_2',
                'postal_code',
                'founded_at',
                'employee_count',
                'status'
            ]);

            $company->update($updateData);

            // Actualizar configuraciones si se proporcionan
            if ($request->has('settings') && is_array($request->settings)) {
                foreach ($request->settings as $key => $value) {
                    \App\Models\CompanySetting::set(
                        $company->id,
                        $key,
                        $value['value'] ?? $value,
                        $value['description'] ?? null,
                        $value['type'] ?? 'string'
                    );
                }
            }

            DB::commit();

            // Recargar empresa con todas las relaciones
            $company->load([
                'country',
                'municipality.department',
                'departmentGeo',
                'companySize',
                'sites',
                'settings'
            ]);

            return ApiResponse::updated([
                'id' => $company->id,
                'legal_name' => $company->legal_name,
                'trade_name' => $company->trade_name,
                'name' => $company->name,
                'tax_id' => $company->tax_id,
                'status' => $company->status,
                'country' => $company->country ? [
                    'id' => $company->country->id,
                    'name' => $company->country->name,
                    'code' => $company->country->code
                ] : null,
                'municipality' => $company->municipality ? [
                    'id' => $company->municipality->id,
                    'name' => $company->municipality->name,
                    'department' => $company->municipality->departmentGeo ? [
                        'id' => $company->municipality->departmentGeo->id,
                        'name' => $company->municipality->departmentGeo->name
                    ] : null
                ] : null,
                'company_size' => $company->companySize ? [
                    'id' => $company->companySize->id,
                    'name' => $company->companySize->name,
                    'code' => $company->companySize->code
                ] : null,
                'economic_activity' => $company->economic_activity,
                'address' => [
                    'line_1' => $company->address_line_1,
                    'line_2' => $company->address_line_2,
                    'postal_code' => $company->postal_code
                ],
                'founded_at' => $company->founded_at,
                'employee_count' => $company->employee_count,
                'sites_count' => $company->sites->count(),
                'updated_at' => $company->updated_at
            ], 'Empresa actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar la empresa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Eliminar empresa (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        $company = Company::find($id);

        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        try {
            // Verificar si hay usuarios activos
            $activeUsers = $company->userCompanies()->where('status', 'active')->count();
            
            if ($activeUsers > 0) {
                return ApiResponse::error(
                    'No se puede eliminar la empresa porque tiene usuarios activos. '.
                    'Desactive o transfiera los usuarios primero.',
                    400
                );
            }

            // Desactivar suscripciones (cambiar estado a cancelado)
            $company->subscriptions()->update([
                'subscription_status_id' => SubscriptionStatus::STATUS_CANCELLED
            ]);

            // Desactivar módulos
            $company->enabledModules()->update(['enabled' => false]);

            // Soft delete de la empresa
            $company->delete();

            return ApiResponse::deleted('Empresa eliminada exitosamente');

        } catch (\Exception $e) {
            return ApiResponse::error('Error al eliminar la empresa: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar suscripción de la empresa
     */
    public function updateSubscription(Request $request, int $id): JsonResponse
    {
        $company = Company::find($id);

        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:plans,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'amount' => 'nullable|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Cancelar suscripción actual si existe
            $company->subscriptions()
                   ->whereHas('subscriptionStatus', function ($q) {
                       $q->where('code', SubscriptionStatus::CODE_ACTIVE);
                   })
                   ->update([
                       'subscription_status_id' => SubscriptionStatus::STATUS_CANCELLED
                   ]);

            // Obtener plan para precios por defecto
            $plan = Plan::findOrFail($request->plan_id);

            // Crear nueva suscripción
            $subscription = $company->subscriptions()->create([
                'plan_id' => $request->plan_id,
                'subscription_status_id' => SubscriptionStatus::STATUS_ACTIVE,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'amount' => $request->amount ?? $plan->price
            ]);

            DB::commit();

            // Recargar suscripción con el plan
            $subscription->load(['plan', 'subscriptionStatus']);

            return ApiResponse::success([
                'id' => $subscription->id,
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'code' => $subscription->plan->code
                ],
                'subscription_status' => [
                    'id' => $subscription->subscriptionStatus->id,
                    'name' => $subscription->subscriptionStatus->name,
                    'code' => $subscription->subscriptionStatus->code
                ],
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
                'amount' => $subscription->amount
            ], 'Suscripción actualizada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar la suscripción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Actualizar módulos habilitados
     */
    public function updateModules(Request $request, int $id): JsonResponse
    {
        $company = Company::find($id);

        if (!$company) {
            return ApiResponse::notFound('Empresa no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'modules' => 'required|array',
            'modules.*' => 'integer|exists:modules,id'
        ]);

        if ($validator->fails()) {
            return ApiResponse::validation($validator->errors()->toArray());
        }

        try {
            DB::beginTransaction();

            // Desactivar módulos actuales
            $company->enabledModules()->update(['enabled' => false]);

            // Habilitar nuevos módulos
            foreach ($request->modules as $moduleId) {
                $company->enabledModules()->updateOrCreate(
                    ['module_id' => $moduleId],
                    ['enabled' => true]
                );
            }

            DB::commit();

            // Recargar módulos
            $company->load('enabledModules.module');

            return ApiResponse::success([
                'enabled_modules' => $company->enabledModules
                    ->where('enabled', true)
                    ->map(function ($enabledModule) {
                        return [
                            'id' => $enabledModule->module->id,
                            'name' => $enabledModule->module->name,
                            'code' => $enabledModule->module->code,
                            'enabled' => $enabledModule->enabled,
                            'config' => $enabledModule->config,
                            'enabled_at' => $enabledModule->updated_at
                        ];
                    })
            ], 'Módulos actualizados exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Error al actualizar los módulos: ' . $e->getMessage(), 500);
        }
    }
}