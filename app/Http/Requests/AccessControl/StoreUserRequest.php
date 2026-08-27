<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('api');

        return $user
            && $user->can('users.create')
            && $user->can('roles.assign');
    }

    public function rules(): array
    {
        return [
            'staff_member_id' => [
                'required',
                'integer',

                Rule::exists('staff_members', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('active', true)
                            ->whereNull('deleted_at')
                    ),

                Rule::unique('users', 'staff_member_id'),
            ],

            'email' => [
                'required',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'password' => [
                'required',
                'confirmed',

                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],

            'role' => [
                'required',
                'string',

                Rule::exists('roles', 'name')
                    ->where(
                        fn ($query) => $query->where(
                            'guard_name',
                            'api'
                        )
                    ),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->input('role') === 'super_admin'
                    && ! $this->user('api')?->hasRole('super_admin')
                ) {
                    $validator->errors()->add(
                        'role',
                        'No está autorizado para asignar super_admin.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => strtolower(
                    trim((string) $this->input('email'))
                ),
            ]);
        }
    }
}
