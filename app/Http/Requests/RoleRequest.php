<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('roles')->ignore($this->route('id'))
            ],
            'description' => 'nullable|string'
        ];

        // Reglas adicionales para creación
        if ($this->isMethod('post')) {
            $rules['company_id'] = 'required|integer|exists:companies,id';
            $rules['permissions'] = 'array';
            $rules['permissions.*'] = 'integer|exists:permissions,id';
            $rules['delegated_roles'] = 'array';
            $rules['delegated_roles.*'] = [
                'integer',
                'exists:roles,id',
                function ($attribute, $value, $fail) {
                    $delegatedRole = \App\Models\Role::find($value);
                    if ($delegatedRole && $delegatedRole->company_id != $this->company_id) {
                        $fail('El rol delegado debe pertenecer a la misma empresa.');
                    }
                }
            ];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre es requerido',
            'code.required' => 'El código es requerido',
            'code.unique' => 'Este código ya está registrado',
            'company_id.required' => 'La empresa es requerida',
            'company_id.exists' => 'La empresa seleccionada no existe',
            'permissions.*.exists' => 'Uno o más permisos seleccionados no existen',
            'delegated_roles.*.exists' => 'Uno o más roles delegados no existen'
        ];
    }
}