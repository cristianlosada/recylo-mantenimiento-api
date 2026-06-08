<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('id'))
            ],
            'document_type' => 'required|string|max:50',
            'document_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('users')->ignore($this->route('id'))
            ],
            'phone' => 'nullable|string|max:50',
            'gender' => 'required|in:male,female,other,prefer_not_to_say',
            'birth_date' => 'required|date|before:today',
            'country_id' => 'required|exists:countries,id',
            'status' => 'in:active,inactive,suspended'
        ];

        // Reglas adicionales para creación
        if ($this->isMethod('post')) {
            $rules['password'] = 'required|string|min:8';
        }

        // Reglas adicionales para actualización
        if ($this->isMethod('put')) {
            $rules['password'] = 'nullable|string|min:8';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es requerido',
            'last_name.required' => 'El apellido es requerido',
            'email.required' => 'El correo electrónico es requerido',
            'email.email' => 'El correo electrónico debe ser válido',
            'email.unique' => 'Este correo electrónico ya está registrado',
            'document_type.required' => 'El tipo de documento es requerido',
            'document_number.required' => 'El número de documento es requerido',
            'document_number.unique' => 'Este número de documento ya está registrado',
            'gender.required' => 'El género es requerido',
            'gender.in' => 'El género debe ser M, F u O',
            'birth_date.required' => 'La fecha de nacimiento es requerida',
            'birth_date.before' => 'La fecha de nacimiento debe ser anterior a hoy',
            'country_id.required' => 'El país es requerido',
            'country_id.exists' => 'El país seleccionado no existe',
            'password.required' => 'La contraseña es requerida',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres'
        ];
    }
}