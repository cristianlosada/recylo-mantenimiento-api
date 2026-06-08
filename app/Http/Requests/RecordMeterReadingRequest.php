<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class RecordMeterReadingRequest extends FormRequest
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
     * Reglas de validación para registrar lectura de medidor
     */
    public function rules(): array
    {
        return [
            // Campos requeridos
            'reading_value' => 'required|numeric|min:0|max:999999999.99',
            'reading_date' => 'required|date|before_or_equal:now',
            
            // Campos opcionales
            'reading_source' => 'nullable|in:manual,work_order,automatic,maintenance_plan',
            'notes' => 'nullable|string|max:1000',
            'work_order_id' => 'nullable|integer|exists:work_orders,id',
            'maintenance_plan_id' => 'nullable|integer|exists:maintenance_plans,id',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'reading_value.required' => 'El valor de lectura es requerido',
            'reading_value.numeric' => 'El valor de lectura debe ser numérico',
            'reading_value.min' => 'El valor de lectura no puede ser negativo',
            'reading_date.required' => 'La fecha de lectura es requerida',
            'reading_date.date' => 'La fecha de lectura debe ser una fecha válida',
            'reading_date.before_or_equal' => 'La fecha de lectura no puede ser futura',
            'reading_source.in' => 'El origen de lectura debe ser: manual, work_order, automatic o maintenance_plan',
            'notes.max' => 'Las notas no pueden exceder 1000 caracteres',
            'work_order_id.exists' => 'La orden de trabajo especificada no existe',
            'maintenance_plan_id.exists' => 'El plan de mantenimiento especificado no existe',
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
        // Si no se especifica reading_source, usar 'manual' por defecto
        if (!$this->has('reading_source')) {
            $this->merge([
                'reading_source' => 'manual',
            ]);
        }

        // Si no se especifica reading_date, usar fecha actual
        if (!$this->has('reading_date')) {
            $this->merge([
                'reading_date' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        // Agregar recorded_by con el usuario autenticado
        $user = auth()->user();
        if ($user) {
            $this->merge([
                'recorded_by' => $user->id,
            ]);
        }
    }
}
