<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class CancelWorkOrderRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para hacer esta petición
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para cancelar orden de trabajo
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => 'required|string|min:3',
        ];
    }

    /**
     * Preparar datos para validación
     */
    protected function prepareForValidation(): void
    {
        // Normalizar nombres de campos en español/inglés
        if ($this->has('motivo_cancelacion') && !$this->has('cancellation_reason')) {
            $this->merge([
                'cancellation_reason' => $this->input('motivo_cancelacion'),
            ]);
        }
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'El motivo de cancelación es requerido',
            'cancellation_reason.min' => 'El motivo de cancelación debe tener al menos 3 caracteres',
        ];
    }

    /**
     * Atributos personalizados para mensajes de error
     */
    public function attributes(): array
    {
        return [
            'cancellation_reason' => 'motivo de cancelación',
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
