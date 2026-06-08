<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRolePermissionsRequest extends FormRequest
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
            'permissions' => [
                'required',
                'array',
                'min:1',
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
            'permissions.required' => 'Debe seleccionar al menos un permiso.',
            'permissions.array' => 'Los permisos deben ser un arreglo.',
            'permissions.min' => 'Debe seleccionar al menos un permiso.',
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
            'permissions' => 'permisos',
        ];
    }
}
