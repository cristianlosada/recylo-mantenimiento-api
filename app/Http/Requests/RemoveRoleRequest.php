<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RemoveRoleRequest extends FormRequest
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
            'role_id' => [
                'required',
                'exists:roles,id',
            ],
            'company_id' => [
                'required',
                'exists:companies,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'role_id.required' => 'El rol es obligatorio.',
            'role_id.exists' => 'El rol no existe.',
            'company_id.required' => 'La empresa es obligatoria.',
            'company_id.exists' => 'La empresa no existe.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'role_id' => 'rol',
            'company_id' => 'empresa',
        ];
    }
}
