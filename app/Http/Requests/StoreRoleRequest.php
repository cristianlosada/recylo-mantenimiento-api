<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'code' => [
                'required',
                'string',
                'max:50',
                'unique:roles,code',
                'regex:/^[A-Z_]+$/', // Solo mayúsculas y guión bajo
            ],
            'description' => [
                'nullable',
                'string',
                'max:500',
            ],
            'company_id' => [
                'nullable',
                'integer',
                'exists:companies,id',
            ],
            'permissions' => [
                'array',
            ],
            'permissions.*' => [
                'integer',
                'exists:permissions,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del rol es obligatorio.',
            'name.string' => 'El nombre debe ser texto.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'code.required' => 'El código del rol es obligatorio.',
            'code.string' => 'El código debe ser texto.',
            'code.max' => 'El código no puede exceder 50 caracteres.',
            'code.unique' => 'Este código de rol ya existe.',
            'code.regex' => 'El código solo puede contener letras mayúsculas y guión bajo.',
            'description.string' => 'La descripción debe ser texto.',
            'description.max' => 'La descripción no puede exceder 500 caracteres.',
            'company_id.integer' => 'El ID de empresa debe ser un número.',
            'company_id.exists' => 'La empresa seleccionada no existe.',
            'permissions.array' => 'Los permisos deben ser un arreglo.',
            'permissions.*.integer' => 'Cada permiso debe ser un ID válido.',
            'permissions.*.exists' => 'Uno o más permisos seleccionados no existen.',
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
            'company_id' => 'empresa',
            'permissions' => 'permisos',
        ];
    }
}
