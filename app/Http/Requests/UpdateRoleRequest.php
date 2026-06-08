<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $roleId = $this->route('role'); // Obtener ID del role desde la ruta

        return [
            'name' => [
                'string',
                'max:255',
            ],
            'code' => [
                'string',
                'max:50',
                Rule::unique('roles', 'code')->ignore($roleId),
                'regex:/^[A-Z_]+$/', // Solo mayúsculas y guión bajo
            ],
            'description' => [
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
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'code.string' => 'El código debe ser texto.',
            'code.max' => 'El código no puede exceder 50 caracteres.',
            'code.unique' => 'Este código de rol ya existe.',
            'code.regex' => 'El código solo puede contener letras mayúsculas y guión bajo.',
            'description.string' => 'La descripción debe ser texto.',
            'description.max' => 'La descripción no puede exceder 500 caracteres.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'code' => 'código',
            'description' => 'descripción',
        ];
    }
}
