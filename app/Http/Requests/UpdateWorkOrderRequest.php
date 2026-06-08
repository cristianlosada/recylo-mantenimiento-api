<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class UpdateWorkOrderRequest extends FormRequest
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
     * Reglas de validación para actualizar orden de trabajo
     */
    public function rules(): array
    {
        return [
            // Campos opcionales (puede actualizar solo algunos campos)
            'asset_id' => 'sometimes|nullable|integer|exists:assets,id',
            'project_id' => 'sometimes|nullable|integer|exists:projects,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'work_order_type' => 'sometimes|in:corrective,preventive,predictive,inspection,emergency,project,improvement',
            'priority' => 'sometimes|in:low,medium,high,critical',
            'status' => 'sometimes|in:pending,scheduled,in_progress,on_hold,completed,validated,cancelled',
            
            // Cambio de estado
            'status_change_reason' => 'nullable|string|max:500',
            'cancellation_reason' => 'nullable|string|max:500',
            
            // Asignación
            'assigned_to' => 'nullable|integer|exists:users,id',
            
            // Programación
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after:scheduled_start',
            'estimated_duration_hours' => 'nullable|numeric|min:0|max:99999.99',
            
            // Costos estimados
            'estimated_labor_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'estimated_material_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'estimated_other_cost' => 'nullable|numeric|min:0|max:999999999.99',
            
            // Clasificación adicional
            'failure_type' => 'nullable|string|max:100',
            'is_emergency' => 'nullable|boolean',
            'requires_shutdown' => 'nullable|boolean',
            
            // Costos reales (solo se pueden actualizar cuando está en ejecución o completada)
            'actual_labor_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'actual_material_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'actual_other_cost' => 'nullable|numeric|min:0|max:999999999.99',
            
            // Notas de completación
            'completion_notes' => 'nullable|string',
            
            // Checklist items
            'checklist_items' => 'nullable|array',
            'checklist_items.*.item_text' => 'required|string|max:500',
            'checklist_items.*.is_required' => 'nullable|boolean',
            'checklist_items.*.display_order' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'asset_id.exists' => 'El activo especificado no existe',
            'title.max' => 'El título no puede exceder 255 caracteres',
            'work_order_type.in' => 'El tipo de orden debe ser: correctivo, preventivo, predictivo, inspección, emergencia, proyecto o mejora',
            'priority.in' => 'La prioridad debe ser: low, medium, high o critical',
            'status.in' => 'El estado debe ser: pending, scheduled, in_progress, on_hold, completed, validated o cancelled',
            'status_change_reason.max' => 'La razón del cambio de estado no puede exceder 500 caracteres',
            'cancellation_reason.max' => 'La razón de cancelación no puede exceder 500 caracteres',
            'assigned_to.exists' => 'El usuario asignado no existe',
            'scheduled_end.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'estimated_duration_hours.min' => 'La duración estimada no puede ser negativa',
            'estimated_labor_cost.min' => 'El costo de mano de obra no puede ser negativo',
            'estimated_material_cost.min' => 'El costo de materiales no puede ser negativo',
            'estimated_other_cost.min' => 'Otros costos no pueden ser negativos',
            'actual_labor_cost.min' => 'El costo real de mano de obra no puede ser negativo',
            'actual_material_cost.min' => 'El costo real de materiales no puede ser negativo',
            'actual_other_cost.min' => 'Otros costos reales no pueden ser negativos',
            'is_emergency.boolean' => 'El campo emergencia debe ser verdadero o falso',
            'requires_shutdown.boolean' => 'El campo requiere paro debe ser verdadero o falso',
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
