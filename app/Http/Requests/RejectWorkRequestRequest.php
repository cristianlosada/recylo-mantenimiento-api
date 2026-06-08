<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class RejectWorkRequestRequest extends FormRequest
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
     * Reglas de validación para rechazar solicitud de trabajo
     */
    public function rules(): array
    {
        return [
            // Motivo de rechazo es obligatorio
            'rejection_reason' => 'required|string|min:10|max:1000',
            
            // Sugerencias opcionales
            'suggestions' => 'nullable|string|max:1000',
            
            // Indicar si se debe notificar al solicitante
            'notify_requester' => 'boolean',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'El motivo de rechazo es obligatorio',
            'rejection_reason.min' => 'El motivo de rechazo debe tener al menos 10 caracteres',
            'rejection_reason.max' => 'El motivo de rechazo no puede exceder 1000 caracteres',
            'suggestions.max' => 'Las sugerencias no pueden exceder 1000 caracteres',
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
        // Por defecto notificar al solicitante
        if (!$this->has('notify_requester')) {
            $this->merge([
                'notify_requester' => true,
            ]);
        }
    }
}
