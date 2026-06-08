<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class ApproveWorkRequestRequest extends FormRequest
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
     * Reglas de validación para aprobar solicitud de trabajo
     */
    public function rules(): array
    {
        return [
            // Comentario opcional al aprobar
            'approval_comment' => 'nullable|string|max:1000',
            
            // Costos reales (opcional al aprobar, obligatorio al completar)
            'actual_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'actual_hours' => 'nullable|numeric|min:0|max:99999.99',
            
            // Opción para crear Work Order automáticamente
            'create_work_order' => 'boolean',
            'work_order_data' => 'nullable|array',
            'work_order_data.scheduled_date' => 'nullable|date',
            'work_order_data.assigned_to' => 'nullable|integer|exists:users,id',
            'work_order_data.notes' => 'nullable|string|max:1000',
            // Shorthand para asignación directa desde móvil
            'assigned_to' => 'nullable|integer|exists:users,id',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'approval_comment.max' => 'El comentario no puede exceder 1000 caracteres',
            'actual_cost.numeric' => 'El costo real debe ser numérico',
            'actual_cost.min' => 'El costo real no puede ser negativo',
            'actual_hours.numeric' => 'Las horas reales deben ser numéricas',
            'actual_hours.min' => 'Las horas reales no pueden ser negativas',
            'work_order_data.required_if' => 'Los datos de la orden de trabajo son requeridos si se desea crear automáticamente',
            'work_order_data.scheduled_date.required_if' => 'La fecha programada es requerida',
            'work_order_data.scheduled_date.after' => 'La fecha programada debe ser posterior a hoy',
            'work_order_data.assigned_to.required_if' => 'El técnico asignado es requerido',
            'work_order_data.assigned_to.exists' => 'El técnico asignado no existe',
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
        // Si no se especifica, no crear work order por defecto
        if (!$this->has('create_work_order')) {
            $this->merge([
                'create_work_order' => false,
            ]);
        }
    }
}
