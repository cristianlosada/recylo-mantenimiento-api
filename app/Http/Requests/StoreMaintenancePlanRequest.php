<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;
use App\Models\MaintenancePlan;

class StoreMaintenancePlanRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para hacer esta petición
     */
    public function authorize(): bool
    {
        // La autorización se maneja en el middleware de permisos
        return true;
    }

    /**
     * Reglas de validación para crear plan de mantenimiento
     */
    public function rules(): array
    {
        $planType = $this->input('plan_type');
        
        $rules = [
            // Campos requeridos base
            'company_id' => 'required|integer|exists:companies,id',
            'asset_id' => 'required|integer|exists:assets,id',
            'plan_name' => 'required|string|max:255',
            'description' => 'required|string',
            'plan_type' => [
                'required',
                'string',
                'in:' . implode(',', [
                    MaintenancePlan::TYPE_TIME_BASED,
                    MaintenancePlan::TYPE_METER_BASED,
                    MaintenancePlan::TYPE_HYBRID
                ])
            ],
            'priority' => [
                'required',
                'string',
                'in:' . implode(',', [
                    MaintenancePlan::PRIORITY_LOW,
                    MaintenancePlan::PRIORITY_MEDIUM,
                    MaintenancePlan::PRIORITY_HIGH,
                    MaintenancePlan::PRIORITY_URGENT
                ])
            ],
            
            // Campos opcionales generales
            'asset_category_id' => 'nullable|integer|exists:asset_categories,id',
            'company_site_id' => 'nullable|integer|exists:company_sites,id',
            'estimated_duration_hours' => 'nullable|numeric|min:0|max:99999.99',
            'estimated_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'requires_shutdown' => 'nullable|boolean',
            'safety_notes' => 'nullable|string',
            'instructions' => 'nullable|string',
            'is_active' => 'nullable|boolean',
            
            // Checklist templates
            'checklist_items' => 'nullable|array',
            'checklist_items.*.item_text' => 'required|string|max:500',
            'checklist_items.*.item_order' => 'nullable|integer|min:0',
            'checklist_items.*.requires_photo' => 'nullable|boolean',
            'checklist_items.*.is_mandatory' => 'nullable|boolean',
            
            // Material templates
            'materials' => 'nullable|array',
            'materials.*.material_id' => 'required|integer|exists:materials,id',
            'materials.*.estimated_quantity' => 'required|numeric|min:0.01',
        ];

        // Validaciones específicas por tipo de plan
        if ($planType === MaintenancePlan::TYPE_TIME_BASED) {
            $rules = array_merge($rules, [
                'frequency_type' => [
                    'required',
                    'string',
                    'in:' . implode(',', [
                        MaintenancePlan::FREQ_DAILY,
                        MaintenancePlan::FREQ_WEEKLY,
                        MaintenancePlan::FREQ_MONTHLY,
                        MaintenancePlan::FREQ_QUARTERLY,
                        MaintenancePlan::FREQ_SEMIANNUAL,
                        MaintenancePlan::FREQ_ANNUAL
                    ])
                ],
                'frequency_value' => 'required|integer|min:1|max:365',
                'start_date' => 'required|date|after_or_equal:today',
                
                // Prohibir campos de medidor
                'meter_type' => 'prohibited',
                'meter_threshold' => 'prohibited',
                'trigger_mode' => 'prohibited',
            ]);
        } elseif ($planType === MaintenancePlan::TYPE_METER_BASED) {
            $rules = array_merge($rules, [
                'meter_type' => [
                    'required',
                    'string',
                    'in:hours,kilometers,cycles,units_produced'
                ],
                'meter_threshold' => 'required|numeric|min:1|max:999999999.99',
                
                // Prohibir campos de frecuencia
                'frequency_type' => 'prohibited',
                'frequency_value' => 'prohibited',
                'start_date' => 'prohibited',
            ]);
        } elseif ($planType === MaintenancePlan::TYPE_HYBRID) {
            $rules = array_merge($rules, [
                // Requiere TODOS los campos
                'frequency_type' => [
                    'required',
                    'string',
                    'in:' . implode(',', [
                        MaintenancePlan::FREQ_DAILY,
                        MaintenancePlan::FREQ_WEEKLY,
                        MaintenancePlan::FREQ_MONTHLY,
                        MaintenancePlan::FREQ_QUARTERLY,
                        MaintenancePlan::FREQ_SEMIANNUAL,
                        MaintenancePlan::FREQ_ANNUAL
                    ])
                ],
                'frequency_value' => 'required|integer|min:1|max:365',
                'start_date' => 'required|date|after_or_equal:today',
                'meter_type' => [
                    'required',
                    'string',
                    'in:hours,kilometers,cycles,units_produced'
                ],
                'meter_threshold' => 'required|numeric|min:1|max:999999999.99',
                'trigger_mode' => [
                    'required',
                    'string',
                    'in:' . implode(',', [
                        MaintenancePlan::TRIGGER_FIRST,
                        MaintenancePlan::TRIGGER_BOTH
                    ])
                ],
            ]);
        }

        return $rules;
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'asset_id.required' => 'El activo es requerido',
            'asset_id.exists' => 'El activo especificado no existe',
            'plan_name.required' => 'El nombre del plan es requerido',
            'plan_name.max' => 'El nombre del plan no puede exceder 255 caracteres',
            'description.required' => 'La descripción es requerida',
            'plan_type.required' => 'El tipo de plan es requerido',
            'plan_type.in' => 'El tipo de plan debe ser: time_based, meter_based o hybrid',
            'priority.required' => 'La prioridad es requerida',
            'priority.in' => 'La prioridad debe ser: low, medium, high o critical',
            'asset_category_id.exists' => 'La categoría de activo especificada no existe',
            'company_site_id.exists' => 'El sitio especificado no existe',
            'estimated_duration_hours.min' => 'La duración estimada no puede ser negativa',
            'estimated_cost.min' => 'El costo estimado no puede ser negativo',
            'requires_shutdown.boolean' => 'El campo requiere paro debe ser verdadero o falso',
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso',
            
            // Time-based
            'frequency_type.required' => 'El tipo de frecuencia es requerido para planes basados en tiempo',
            'frequency_type.in' => 'El tipo de frecuencia debe ser: daily, weekly, monthly, quarterly, semiannual o annual',
            'frequency_value.required' => 'El valor de frecuencia es requerido para planes basados en tiempo',
            'frequency_value.min' => 'El valor de frecuencia debe ser al menos 1',
            'frequency_value.max' => 'El valor de frecuencia no puede exceder 365',
            'start_date.required' => 'La fecha de inicio es requerida para planes basados en tiempo',
            'start_date.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy',
            
            // Meter-based
            'meter_type.required' => 'El tipo de medidor es requerido para planes basados en medidor',
            'meter_type.in' => 'El tipo de medidor debe ser: hours, kilometers, cycles o units_produced',
            'meter_threshold.required' => 'El umbral de medidor es requerido para planes basados en medidor',
            'meter_threshold.min' => 'El umbral de medidor debe ser al menos 1',
            
            // Hybrid
            'trigger_mode.required' => 'El modo de activación es requerido para planes híbridos',
            'trigger_mode.in' => 'El modo de activación debe ser: first o both',
            
            // Prohibited
            'meter_type.prohibited' => 'No se debe especificar tipo de medidor para planes basados en tiempo',
            'meter_threshold.prohibited' => 'No se debe especificar umbral de medidor para planes basados en tiempo',
            'trigger_mode.prohibited' => 'No se debe especificar modo de activación para planes basados en tiempo',
            'frequency_type.prohibited' => 'No se debe especificar tipo de frecuencia para planes basados en medidor',
            'frequency_value.prohibited' => 'No se debe especificar valor de frecuencia para planes basados en medidor',
            'start_date.prohibited' => 'No se debe especificar fecha de inicio para planes basados en medidor',
            
            // Checklist
            'checklist_items.array' => 'Los items de checklist deben enviarse como un arreglo',
            'checklist_items.*.item_text.required' => 'El texto del item es requerido',
            'checklist_items.*.item_text.max' => 'El texto del item no puede exceder 500 caracteres',
            'checklist_items.*.item_order.min' => 'El orden del item no puede ser negativo',
            'checklist_items.*.requires_photo.boolean' => 'El campo requiere foto debe ser verdadero o falso',
            'checklist_items.*.is_mandatory.boolean' => 'El campo obligatorio debe ser verdadero o falso',
            
            // Materials
            'materials.array' => 'Los materiales deben enviarse como un arreglo',
            'materials.*.material_id.required' => 'El ID del material es requerido',
            'materials.*.material_id.exists' => 'Uno o más materiales no existen',
            'materials.*.estimated_quantity.required' => 'La cantidad estimada es requerida',
            'materials.*.estimated_quantity.min' => 'La cantidad estimada debe ser mayor que 0',
        ];
    }

    /**
     * Manejar validación fallida
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        \Log::info('prepareForValidation ejecutándose', [
            'has_company_id_in_request' => $this->has('company_id'),
            'header_x_company_id' => $this->header('x-company-id'),
            'all_headers' => $this->headers->all()
        ]);

        // Agregar company_id del header o del usuario autenticado
        if (!$this->has('company_id')) {
            // Intentar obtener del header primero
            $companyId = $this->header('x-company-id');
            
            \Log::info('Obteniendo company_id del header', [
                'company_id_from_header' => $companyId
            ]);
            
            // Si no viene en el header, intentar obtener de la primera empresa del usuario
            if (!$companyId) {
                $user = auth()->user();
                if ($user) {
                    $userCompany = $user->companies()->first();
                    $companyId = $userCompany ? $userCompany->id : null;
                    
                    \Log::info('company_id desde relación de usuario', [
                        'user_id' => $user->id,
                        'company_id' => $companyId
                    ]);
                }
            }
            
            if ($companyId) {
                $this->merge(['company_id' => $companyId]);
                \Log::info('company_id merged al request', ['company_id' => $companyId]);
            } else {
                \Log::warning('No se pudo obtener company_id');
            }
        }

        // Si no se especifica is_active, usar true por defecto
        if (!$this->has('is_active')) {
            $this->merge([
                'is_active' => true,
            ]);
        }

        // Agregar created_by con el usuario autenticado
        $user = auth()->user();
        if ($user) {
            $this->merge([
                'created_by' => $user->id,
            ]);
        }

        // Asegurar valores por defecto para campos numéricos
        $defaults = [
            'estimated_duration_hours' => 0,
            'estimated_cost' => 0,
        ];

        foreach ($defaults as $field => $defaultValue) {
            $value = $this->input($field);
            if (!$this->has($field) || $value === null || $value === '') {
                $this->merge([$field => $defaultValue]);
            }
        }
    }
}
