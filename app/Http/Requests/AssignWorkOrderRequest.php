<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Http\Responses\ApiResponse;

class AssignWorkOrderRequest extends FormRequest
{
    /**
     * Determinar si el usuario está autorizado para hacer esta petición
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Reglas de validación para asignar orden de trabajo
     */
    public function rules(): array
    {
        return [
            'assigned_to' => 'required|integer|exists:users,id',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date|after:scheduled_start',
            'team_members' => 'nullable|array',
            'team_members.*.user_id' => 'required|integer|exists:users,id',
            'team_members.*.role' => 'required|in:technician,supervisor,helper,specialist',
            'team_members.*.notes' => 'nullable|string|max:500',
        ];
    }

    /**
     * Mensajes de error personalizados
     */
    public function messages(): array
    {
        return [
            'assigned_to.required' => 'Debe asignar la orden a un usuario',
            'assigned_to.exists' => 'El usuario asignado no existe',
            'scheduled_start.date' => 'La fecha de inicio debe ser una fecha válida',
            'scheduled_end.date' => 'La fecha de fin debe ser una fecha válida',
            'scheduled_end.after' => 'La fecha de fin debe ser posterior a la fecha de inicio',
            'team_members.*.user_id.exists' => 'Uno o más miembros del equipo no existen',
            'team_members.*.role.in' => 'El rol debe ser: technician, supervisor, helper o specialist',
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
