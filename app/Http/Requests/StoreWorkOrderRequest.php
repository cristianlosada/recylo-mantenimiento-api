<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class StoreWorkOrderRequest extends FormRequest
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
     * Reglas de validación para crear orden de trabajo
     */
    public function rules(): array
    {
        return [
            // Campos requeridos
            'asset_id' => 'required|integer|exists:assets,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'work_order_type' => 'required|in:corrective,preventive,predictive,inspection,emergency,project,improvement',
            'priority' => 'required|in:low,medium,high,critical',

            // Proyecto asociado (solo aplica cuando work_order_type = 'project')
            'project_id' => 'nullable|integer|exists:projects,id',

            // Campos opcionales pero importantes
            'work_request_id' => 'nullable|integer|exists:work_requests,id',
            'maintenance_plan_id' => 'nullable|integer',
            'assigned_to' => 'nullable|integer|exists:users,id',
            
            // Programación
            'scheduled_start' => 'nullable|date|after_or_equal:today',
            'scheduled_end' => 'nullable|date|after:scheduled_start',
            
            // Costos estimados (requeridos con default 0 para evitar errores SQL)
            'estimated_duration_hours' => 'required|numeric|min:0|max:99999.99',
            'estimated_labor_cost' => 'required|numeric|min:0|max:999999999.99',
            'estimated_material_cost' => 'required|numeric|min:0|max:999999999.99',
            'estimated_other_cost' => 'required|numeric|min:0|max:999999999.99',
            
            // Clasificación adicional
            'failure_type' => 'nullable|string|max:100',
            'is_emergency' => 'nullable|boolean',
            'requires_shutdown' => 'nullable|boolean',
            
            // Checklist items (opcional al crear)
            'checklist_items' => 'nullable|array',
            'checklist_items.*.item_text' => 'required|string|max:500',
            'checklist_items.*.is_required' => 'nullable|boolean',
            'checklist_items.*.display_order' => 'nullable|integer|min:0',
            
            // Team assignments (opcional al crear)
            'team_members' => 'nullable|array',
            'team_members.*.user_id' => 'required|integer|exists:users,id',
            'team_members.*.role' => 'required|in:technician,supervisor,helper,specialist',
            'team_members.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'asset_id.required' => 'El activo es requerido',
            'asset_id.exists' => 'El activo especificado no existe',
            'title.required' => 'El título es requerido',
            'title.max' => 'El título no puede exceder 255 caracteres',
            'description.required' => 'La descripción es requerida',
            'work_order_type.required' => 'El tipo de orden es requerido',
            'work_order_type.in' => 'El tipo de orden debe ser: correctivo, preventivo, predictivo, inspección, emergencia, proyecto o mejora',
            'priority.required' => 'La prioridad es requerida',
            'priority.in' => 'La prioridad debe ser: low, medium, high o critical',
            'work_request_id.exists' => 'La solicitud de trabajo especificada no existe',
            'assigned_to.exists' => 'El usuario asignado no existe',
            'scheduled_start.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy',
            'scheduled_end.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'estimated_duration_hours.min' => 'La duración estimada no puede ser negativa',
            'estimated_labor_cost.min' => 'El costo de mano de obra no puede ser negativo',
            'estimated_material_cost.min' => 'El costo de materiales no puede ser negativo',
            'estimated_other_cost.min' => 'Otros costos no pueden ser negativos',
            'is_emergency.boolean' => 'El campo emergencia debe ser verdadero o falso',
            'requires_shutdown.boolean' => 'El campo requiere paro debe ser verdadero o falso',
            'team_members.*.user_id.exists' => 'Uno o más miembros del equipo no existen',
            'team_members.*.role.in' => 'El rol debe ser: technician, supervisor, helper o specialist',
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
        // Agregar company_id del header o del usuario autenticado
        if (!$this->has('company_id')) {
            // Intentar obtener del header primero
            $companyId = $this->header('x-company-id');
            
            // Si no viene en el header, intentar obtener de la primera empresa del usuario
            if (!$companyId) {
                $user = auth()->user();
                if ($user) {
                    $userCompany = $user->companies()->first();
                    $companyId = $userCompany ? $userCompany->id : null;
                }
            }
            
            if ($companyId) {
                $this->merge(['company_id' => $companyId]);
            }
        }

        // Si no se especifica status, usar 'pending' por defecto
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'pending',
            ]);
        }

        // Asegurar que los campos de costos y duración nunca sean null
        // Esto previene errores SQL "Column cannot be null"
        $defaults = [
            'estimated_labor_cost' => 0,
            'estimated_material_cost' => 0,
            'estimated_other_cost' => 0,
            'estimated_duration_hours' => 0,
        ];

        foreach ($defaults as $field => $defaultValue) {
            $value = $this->input($field);
            
            // Solo aplicar default si:
            // 1. El campo no existe en el request, O
            // 2. El valor es null, O
            // 3. El valor es string vacío Y no es un número válido
            if (!$this->has($field) || 
                $value === null || 
                ($value === '' && !is_numeric($value))) {
                $this->merge([$field => $defaultValue]);
            }
        }

        // Mapear 'order' a 'display_order' en checklist_items si existe
        if ($this->has('checklist_items') && is_array($this->checklist_items)) {
            $items = $this->checklist_items;
            foreach ($items as $index => &$item) {
                if (isset($item['order']) && !isset($item['display_order'])) {
                    $item['display_order'] = $item['order'];
                }
            }
            $this->merge(['checklist_items' => $items]);
        }
    }
}
