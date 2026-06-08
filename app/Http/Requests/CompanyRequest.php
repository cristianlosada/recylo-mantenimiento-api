<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
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
            'legal_name' => 'required|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'tax_id' => [
                'required',
                'string',
                'max:50',
                Rule::unique('companies')->ignore($this->route('id'))
            ],
            'country_id' => 'required|integer|exists:countries,id',
            'status' => 'in:active,inactive'
        ];

        // Reglas adicionales para creación
        if ($this->isMethod('post')) {
            $rules['subscription'] = 'required_if:status,active|array';
            $rules['subscription.plan_id'] = 'required_if:status,active|exists:plans,id';
            $rules['subscription.start_date'] = 'required_if:status,active|date';
            $rules['subscription.end_date'] = 'nullable|date|after:subscription.start_date';
            $rules['subscription.amount'] = 'nullable|numeric|min:0';
            $rules['modules'] = 'array';
            $rules['modules.*'] = 'integer|exists:modules,id';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'legal_name.required' => 'La razón social es requerida',
            'tax_id.required' => 'El NIT/RUT es requerido',
            'tax_id.unique' => 'Este NIT/RUT ya está registrado',
            'country_id.required' => 'El país es requerido',
            'country_id.exists' => 'El país seleccionado no existe',
            'subscription.required_if' => 'La suscripción es requerida para empresas activas',
            'subscription.plan_id.required_if' => 'El plan es requerido para empresas activas',
            'subscription.plan_id.exists' => 'El plan seleccionado no existe',
            'subscription.start_date.required_if' => 'La fecha de inicio es requerida para empresas activas',
            'subscription.end_date.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'subscription.amount.numeric' => 'El monto debe ser un número',
            'subscription.amount.min' => 'El monto no puede ser negativo',
            'modules.*.exists' => 'Uno o más módulos seleccionados no existen'
        ];
    }
}