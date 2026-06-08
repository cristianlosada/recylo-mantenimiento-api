<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class CompleteWorkOrderRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para hacer esta petición
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para completar orden de trabajo
     */
    public function rules(): array
    {
        return [
            'completion_notes' => 'required|string',
            'signature_data' => 'nullable|string', // Base64 de la firma digital
            'signature_name' => 'required_with:signature_data|string|max:255',
            'actual_labor_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'actual_material_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'actual_other_cost' => 'nullable|numeric|min:0|max:999999999.99',
            'downtime_hours' => 'nullable|numeric|min:0|max:99999.99',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'completion_notes.required' => 'Las notas de completación son requeridas',
            'signature_name.required_with' => 'El nombre del firmante es requerido cuando se proporciona firma',
            'actual_labor_cost.min' => 'El costo real de mano de obra no puede ser negativo',
            'actual_material_cost.min' => 'El costo real de materiales no puede ser negativo',
            'actual_other_cost.min' => 'Otros costos reales no pueden ser negativos',
            'downtime_hours.min' => 'Las horas de inactividad no pueden ser negativas',
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
