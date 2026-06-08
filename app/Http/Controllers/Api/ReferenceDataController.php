<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Country;
use App\Models\DocumentType;
use App\Models\Module;
use App\Models\Plan;
use App\Models\Currency;
use App\Models\CompanySize;
use App\Models\SiteType;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\AssetPriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReferenceDataController extends Controller
{
    /**
     * Obtener tipos de documento
     */
    public function getDocumentTypes(): JsonResponse
    {
        $documentTypes = DocumentType::select('id', 'name', 'code')
            ->where('country_id', 1)
            ->orderBy('name')
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'name' => $type->name,
                    'code' => $type->code
                ];
            });

        return ApiResponse::success($documentTypes, 'Tipos de documento recuperados exitosamente');
    }

    /**
     * Obtener países
     */
    public function getCountries(): JsonResponse
    {
        $countries = Country::select('id', 'name', 'code')
                          ->orderBy('name')
                          ->where('id', 1)
                          ->get()
                          ->map(function ($country) {
                              return [
                                  'id' => $country->id,
                                  'name' => $country->name,
                                  'code' => $country->code,
                                //   'phone_code' => $country->phone_code,
                                //   'currency_code' => $country->currency_code
                              ];
                          });

        return ApiResponse::success($countries, 'Países recuperados exitosamente');
    }

    /**
     * Obtener opciones de género
     */
    public function getGenderOptions(): JsonResponse
    {
        $genderOptions = [
            ['value' => 'male', 'label' => 'Masculino'],
            ['value' => 'female', 'label' => 'Femenino'],
            ['value' => 'other', 'label' => 'Otro'],
            ['value' => 'prefer_not_to_say', 'label' => 'Prefiero no decir']
        ];

        return ApiResponse::success($genderOptions, 'Opciones de género recuperadas exitosamente');
    }

    /**
     * Obtener opciones de estado de usuario
     */
    public function getUserStatusOptions(): JsonResponse
    {
        $statusOptions = [
            ['value' => 'active', 'label' => 'Activo'],
            ['value' => 'inactive', 'label' => 'Inactivo'],
            ['value' => 'suspended', 'label' => 'Suspendido'],
            ['value' => 'pending_verification', 'label' => 'Pendiente de Verificación']
        ];

        return ApiResponse::success($statusOptions, 'Estados de usuario recuperados exitosamente');
    }

    /**
     * Obtener datos para formulario de usuario
     */
    public function getUserFormData(): JsonResponse
    {
        $data = [
            'document_types' => DocumentType::select('id', 'code', 'name')
                ->orderBy('name')->get(),
            'countries' => Country::select('id', 'name', 'code')
                ->orderBy('name')->get(),
            'gender_options' => [
                ['value' => 'male', 'label' => 'Masculino'],
                ['value' => 'female', 'label' => 'Femenino'],
                ['value' => 'other', 'label' => 'Otro'],
                ['value' => 'prefer_not_to_say', 'label' => 'Prefiero no decir']
            ],
            'status_options' => [
                ['value' => 'active', 'label' => 'Activo'],
                ['value' => 'inactive', 'label' => 'Inactivo'],
                ['value' => 'suspended', 'label' => 'Suspendido'],
                ['value' => 'pending_verification', 'label' => 'Pendiente de Verificación']
            ]
        ];

        return ApiResponse::success($data, 'Datos de formulario recuperados exitosamente');
    }

    /**
     * Obtener datos para formulario de empresa
     */
    public function getCompanyFormData(): JsonResponse
    {
        $data = [
            'countries' => Country::select('id', 'name', 'code')->orderBy('name')->get(),
            'status_options' => [
                ['code' => 'active', 'name' => 'Activo'],
                ['code' => 'inactive', 'name' => 'Inactivo'],
                ['code' => 'suspended', 'name' => 'Suspendido']
            ]
        ];

        return ApiResponse::success($data, 'Datos de formulario de empresa recuperados exitosamente');
    }

    /**
     * Obtener módulos disponibles
     */
    public function getCompanyModules(): JsonResponse
    {
        $modules = Module::select('id', 'name', 'code', 'description')
                        // ->with(['permissions' => function ($query) {
                        //     $query->select('id', 'module_id', 'name', 'code');
                        // }])
                        ->orderBy('name')
                        ->get()
                        ->map(function ($module) {
                            return [
                                'id' => $module->id,
                                'name' => $module->name,
                                'code' => $module->code,
                                'description' => $module->description,
                                // 'permissions' => $module->permissions->map(function ($permission) {
                                //     return [
                                //         'id' => $permission->id,
                                //         'name' => $permission->name,
                                //         'code' => $permission->code
                                //     ];
                                // })
                            ];
                        });

        return ApiResponse::success($modules, 'Módulos recuperados exitosamente');
    }

    /**
     * Obtener planes disponibles
     */
    public function getPlans(): JsonResponse
    {
        $plans = Plan::select('id',
            'code',
            'name',
            'description',
            'price',
            'currency',
            'billing_cycle_days',
            'is_active')
            ->with(['modules' => function ($query) {
                $query->select('modules.id', 'modules.name', 'modules.code');
            }])
            ->orderBy('price')
            ->get()
            ->map(function ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'code' => $plan->code,
                    'description' => $plan->description,
                    'price' => $plan->price,
                    'currency' => $plan->currency,
                    'billing_cycle_days' => $plan->billing_cycle_days,
                    'modules' => $plan->modules->map(function ($module) {
                        return [
                            'id' => $module->id,
                            'name' => $module->name,
                            'code' => $module->code
                        ];
                    })
                ];
            });

        return ApiResponse::success($plans, 'Planes recuperados exitosamente');
    }

    /**
     * Obtener monedas disponibles
     */
    public function getCurrencies(): JsonResponse
    {
        $currencies = Currency::select('code', 'name', 'symbol')
                            ->where('is_active', true)
                            ->where('country_id', 1)
                            ->orderBy('name')
                            ->get()
                            ->map(function ($currency) {
                                return [
                                    'code' => $currency->code,
                                    'name' => $currency->name,
                                    'symbol' => $currency->symbol
                                ];
                            });

        return ApiResponse::success($currencies, 'Monedas recuperadas exitosamente');
    }

    /**
     * Obtener datos de ubicación (departamentos y municipios)
     */
    public function getLocationData(int $countryId): JsonResponse
    {
        $country = Country::with([
            'departments' => function ($query) {
                $query->orderBy('name');
            },
            'departments.municipalities' => function ($query) {
                $query->orderBy('name');
            }
        ])->find($countryId);

        if (!$country) {
            return ApiResponse::notFound('País no encontrado');
        }

        $data = [
            'country' => [
                'id' => $country->id,
                'name' => $country->name,
                'code' => $country->code
            ],
            'departments' => $country->departments->map(function ($department) {
                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'municipalities' => $department->municipalities->map(function ($municipality) {
                        return [
                            'id' => $municipality->id,
                            'name' => $municipality->name,
                            'code' => $municipality->code
                        ];
                    })
                ];
            })
        ];

        return ApiResponse::success($data, 'Datos de ubicación recuperados exitosamente');
    }

    /**
     * Obtener datos para tamanos de empresa
     */
    public function getCompanySizes(): JsonResponse
    {
        $data = CompanySize::select('id', 'name', 'code', 'min_employees', 'max_employees')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return ApiResponse::success($data, 'Datos de tamanos de empresa recuperados exitosamente');
    }

    /**
     * Obtener datos para actividades de empresa
     */
    public function getCompanyActivities(): JsonResponse
    {
        // economic_activity es un campo string en companies, no una tabla separada
        return ApiResponse::success([], 'Las actividades económicas se manejan como texto libre en la empresa');
    }

    /**
     * Obtener monedas disponibles
     */
    public function getEmploymentTypes(): JsonResponse
    {
        // employment_type es un enum en user_companies, no una tabla separada
        $employmentTypes = [
            ['value' => 'full_time', 'label' => 'Tiempo completo'],
            ['value' => 'part_time', 'label' => 'Medio tiempo'],
            ['value' => 'contractor', 'label' => 'Contratista'],
            ['value' => 'intern', 'label' => 'Practicante'],
        ];

        return ApiResponse::success($employmentTypes, 'Tipos de empleo recuperados exitosamente');
    }

    /** 
     * Obtener los job categories
     */
    public function getJobCategories(): JsonResponse
    {
        // job_categories no existe como tabla. job_position es un campo string en user_companies
        return ApiResponse::success([], 'Las categorías de trabajo se manejan como texto libre');
    }

    /**
     * Obtener tipos de sede
     */
    public function getSiteTypes(): JsonResponse
    {
        $siteTypes = DB::table('site_types')
            ->select('id', 'code', 'name', 'description')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($type) {
                return [
                    'id' => $type->id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'description' => $type->description
                ];
            });

        return ApiResponse::success($siteTypes, 'Tipos de sede recuperados exitosamente');
    }

    /**
     * Obtener categorías de activos
     */
    public function getAssetCategories(): JsonResponse
    {
        $categories = AssetCategory::select('id', 'code', 'name', 'description', 'icon', 'color')
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'description' => $category->description,
                    'icon' => $category->icon,
                    'color' => $category->color
                ];
            });

        return ApiResponse::success($categories, 'Categorías de activos recuperadas exitosamente');
    }

    /**
     * Obtener estados de activos
     */
    public function getAssetStatuses(): JsonResponse
    {
        $statuses = AssetStatus::select('id', 'code', 'name', 'description', 'color', 'requires_note', 'is_operational')
            ->active()
            ->orderBy('name')
            ->get()
            ->map(function ($status) {
                return [
                    'id' => $status->id,
                    'code' => $status->code,
                    'name' => $status->name,
                    'description' => $status->description,
                    'color' => $status->color,
                    'requires_note' => $status->requires_note,
                    'is_operational' => $status->is_operational
                ];
            });

        return ApiResponse::success($statuses, 'Estados de activos recuperados exitosamente');
    }

    /**
     * Obtener prioridades de activos
     */
    public function getAssetPriorities(): JsonResponse
    {
        $priorities = AssetPriority::select('id', 'code', 'name', 'level', 'color', 'description')
            ->active()
            ->orderedByLevel()
            ->get()
            ->map(function ($priority) {
                return [
                    'id' => $priority->id,
                    'code' => $priority->code,
                    'name' => $priority->name,
                    'level' => $priority->level,
                    'color' => $priority->color,
                    'description' => $priority->description
                ];
            });

        return ApiResponse::success($priorities, 'Prioridades de activos recuperadas exitosamente');
    }
}