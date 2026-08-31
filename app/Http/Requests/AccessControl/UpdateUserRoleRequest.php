<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('roles.assign') ?? false;
    }

    public function rules(): array
    {
        return [
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
}
