<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDelegationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controlador
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'expires_at' => [
                'nullable',
                'date',
                'after:' . $this->route('delegation')->delegated_at,
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'expires_at.date' => 'La fecha de expiración debe ser una fecha válida.',
            'expires_at.after' => 'La fecha de expiración debe ser posterior a la fecha de delegación.',
            'reason.string' => 'El motivo debe ser texto.',
            'reason.max' => 'El motivo no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'expires_at' => 'fecha de expiración',
            'reason' => 'motivo',
        ];
    }
}
