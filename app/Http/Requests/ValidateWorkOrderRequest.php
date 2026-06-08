<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class ValidateWorkOrderRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para hacer esta petición
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para validar orden de trabajo
     */
    public function rules(): array
    {
        return [
            'validation_notes' => 'required|string',
            'is_approved' => 'required|boolean',
            'rejection_reason' => 'required_if:is_approved,false|string',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'validation_notes.required' => 'Las notas de validación son requeridas',
            'is_approved.required' => 'Debe indicar si aprueba o rechaza la validación',
            'rejection_reason.required_if' => 'El motivo de rechazo es requerido cuando no se aprueba',
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
