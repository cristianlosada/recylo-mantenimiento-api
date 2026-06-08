<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\UserRole;
use App\Models\UserCompany;
use App\Models\RoleDelegation;

class StoreDelegationRequest extends FormRequest
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
            'delegatee_user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    // Validar que el delegado no sea el mismo delegador
                    if ($value == Auth::id()) {
                        $fail('No puedes delegarte un rol a ti mismo.');
                    }

                    // Validar que el delegado esté en la misma empresa
                    $companyId = $this->input('company_id');
                    if ($companyId) {
                        $userCompany = UserCompany::where('user_id', $value)
                            ->where('company_id', $companyId)
                            ->where('status', 'active')
                            ->exists();

                        if (!$userCompany) {
                            $fail('El usuario delegado no pertenece a esta empresa.');
                        }
                    }
                },
            ],
            'role_id' => [
                'required',
                'exists:roles,id',
                function ($attribute, $value, $fail) {
                    // Validar que el delegador tenga el rol que quiere delegar
                    $companyId = $this->input('company_id');
                    $hasRole = UserRole::where('user_id', Auth::id())
                        ->where('role_id', $value)
                        ->where('company_id', $companyId)
                        ->where(function ($q) {
                            $q->whereNull('expires_at')
                              ->orWhere('expires_at', '>', now());
                        })
                        ->exists();

                    if (!$hasRole) {
                        $fail('No tienes este rol activo en tu perfil. Solo puedes delegar roles que posees.');
                    }

                    // Validar que no exista una delegación activa duplicada
                    $delegateUserId = $this->input('delegatee_user_id');
                    if ($delegateUserId) {
                        $activeDelegation = RoleDelegation::where('delegatee_user_id', $delegateUserId)
                            ->where('role_id', $value)
                            ->where('company_id', $companyId)
                            ->where('delegated_at', '<=', now())
                            ->whereNull('revoked_at')
                            ->where(function ($q) {
                                $q->whereNull('expires_at')
                                  ->orWhere('expires_at', '>=', now());
                            })
                            ->exists();

                        if ($activeDelegation) {
                            $fail('Ya existe una delegación activa de este rol para este usuario.');
                        }
                    }
                },
            ],
            'company_id' => [
                'required',
                'exists:companies,id',
                function ($attribute, $value, $fail) {
                    // Validar que el delegador pertenezca a la empresa
                    $userCompany = UserCompany::where('user_id', Auth::id())
                        ->where('company_id', $value)
                        ->where('status', 'active')
                        ->exists();

                    if (!$userCompany) {
                        $fail('No perteneces a esta empresa.');
                    }
                },
            ],
            'delegated_at' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:delegated_at',
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
            'delegatee_user_id.required' => 'El usuario delegado es obligatorio.',
            'delegatee_user_id.exists' => 'El usuario delegado no existe.',
            'role_id.required' => 'El rol es obligatorio.',
            'role_id.exists' => 'El rol no existe.',
            'company_id.required' => 'La empresa es obligatoria.',
            'company_id.exists' => 'La empresa no existe.',
            'delegated_at.date' => 'La fecha de delegación debe ser una fecha válida.',
            'delegated_at.after_or_equal' => 'La fecha de delegación no puede ser anterior a hoy.',
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
            'delegatee_user_id' => 'usuario delegado',
            'role_id' => 'rol',
            'company_id' => 'empresa',
            'delegated_at' => 'fecha de delegación',
            'expires_at' => 'fecha de expiración',
            'reason' => 'motivo',
        ];
    }
}
