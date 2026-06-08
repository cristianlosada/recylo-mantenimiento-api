<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;
use App\Models\MaintenancePlan;

class UpdateMaintenancePlanRequest extends FormRequest
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
     * Reglas de validación para actualizar plan de mantenimiento
     */
    public function rules(): array
    {
        return [
            // Campos opcionales generales (puede actualizar solo algunos campos)
            'plan_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'priority' => [
                'sometimes',
                'string',
                'in:' . implode(',', [
                    MaintenancePlan::PRIORITY_LOW,
                    MaintenancePlan::PRIORITY_MEDIUM,
                    MaintenancePlan::PRIORITY_HIGH,
                    MaintenancePlan::PRIORITY_URGENT
                ])
            ],
            'asset_category_id' => 'sometimes|nullable|integer|exists:asset_categories,id',
            'company_site_id' => 'sometimes|nullable|integer|exists:company_sites,id',
            'estimated_duration_hours' => 'sometimes|nullable|numeric|min:0|max:99999.99',
            'estimated_cost' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'requires_shutdown' => 'sometimes|boolean',
            'safety_notes' => 'sometimes|nullable|string',
            'instructions' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
            
            // Campos de frecuencia (para time_based o hybrid)
            'frequency_type' => [
                'sometimes',
                'nullable',
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
            'frequency_value' => 'sometimes|nullable|integer|min:1|max:365',
            'start_date' => 'sometimes|nullable|date',
            
            // Campos de medidor (para meter_based o hybrid)
            'meter_type' => [
                'sometimes',
                'nullable',
                'string',
                'in:hours,kilometers,cycles,units_produced'
            ],
            'meter_threshold' => 'sometimes|nullable|numeric|min:1|max:999999999.99',
            'trigger_mode' => [
                'sometimes',
                'nullable',
                'string',
                'in:' . implode(',', [
                    MaintenancePlan::TRIGGER_FIRST,
                    MaintenancePlan::TRIGGER_BOTH
                ])
            ],
            
            // Checklist templates
            'checklist_items' => 'sometimes|nullable|array',
            'checklist_items.*.item_text' => 'required|string|max:500',
            'checklist_items.*.item_order' => 'nullable|integer|min:0',
            'checklist_items.*.requires_photo' => 'nullable|boolean',
            'checklist_items.*.is_mandatory' => 'nullable|boolean',
            
            // Material templates
            'materials' => 'sometimes|nullable|array',
            'materials.*.material_id' => 'required|integer|exists:materials,id',
            'materials.*.estimated_quantity' => 'required|numeric|min:0.01',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'plan_name.max' => 'El nombre del plan no puede exceder 255 caracteres',
            'priority.in' => 'La prioridad debe ser: low, medium, high o critical',
            'asset_category_id.exists' => 'La categoría de activo especificada no existe',
            'company_site_id.exists' => 'El sitio especificado no existe',
            'estimated_duration_hours.min' => 'La duración estimada no puede ser negativa',
            'estimated_cost.min' => 'El costo estimado no puede ser negativo',
            'requires_shutdown.boolean' => 'El campo requiere paro debe ser verdadero o falso',
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso',
            
            // Frecuencia
            'frequency_type.in' => 'El tipo de frecuencia debe ser: daily, weekly, monthly, quarterly, semiannual o annual',
            'frequency_value.min' => 'El valor de frecuencia debe ser al menos 1',
            'frequency_value.max' => 'El valor de frecuencia no puede exceder 365',
            
            // Medidor
            'meter_type.in' => 'El tipo de medidor debe ser: hours, kilometers, cycles o units_produced',
            'meter_threshold.min' => 'El umbral de medidor debe ser al menos 1',
            
            // Trigger
            'trigger_mode.in' => 'El modo de activación debe ser: first o both',
            
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
}
