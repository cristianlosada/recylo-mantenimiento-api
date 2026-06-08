<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class UpdateAssetMeterRequest extends FormRequest
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
     * Reglas de validación para actualizar medidor de activo
     */
    public function rules(): array
    {
        return [
            // Todos los campos opcionales (puede actualizar solo algunos campos)
            'description' => 'sometimes|nullable|string|max:500',
            'alert_threshold' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'max_reading' => 'sometimes|nullable|numeric|min:0|max:999999999.99',
            'is_active' => 'sometimes|boolean',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'description.max' => 'La descripción no puede exceder 500 caracteres',
            'alert_threshold.numeric' => 'El umbral de alerta debe ser numérico',
            'alert_threshold.min' => 'El umbral de alerta no puede ser negativo',
            'max_reading.numeric' => 'La lectura máxima debe ser numérica',
            'max_reading.min' => 'La lectura máxima no puede ser negativa',
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso',
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
