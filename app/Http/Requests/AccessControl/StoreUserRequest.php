<?php

namespace App\Http\Requests\AccessControl;

use App\Models\People\StaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
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
                'required_without:staff_member',
                'prohibits:staff_member',
                'integer',

                Rule::exists('staff_members', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('active', true)
                            ->whereNull('deleted_at')
                    ),

                Rule::unique('users', 'staff_member_id'),
            ],

            'staff_member' => [
                'required_without:staff_member_id',
                'prohibits:staff_member_id',
                'array:first_names,paternal_surname,maternal_surname,birth_date,ci,ci_complement,phone,email,organizational_unit_id,position_id,profession_id',
            ],

            'staff_member.first_names' => [
                'required_with:staff_member',
                'string',
                'max:100',
            ],

            'staff_member.paternal_surname' => [
                'nullable',
                'string',
                'max:80',
            ],

            'staff_member.maternal_surname' => [
                'nullable',
                'string',
                'max:80',
            ],

            'staff_member.birth_date' => [
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'staff_member.ci' => [
                'required_with:staff_member',
                'string',
                'max:15',
                'regex:/^[0-9]{1,15}$/',
            ],

            'staff_member.ci_complement' => [
                'nullable',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
            ],

            'staff_member.phone' => [
                'nullable',
                'string',
                'max:20',
            ],

            'staff_member.email' => [
                'nullable',
                'email:rfc',
                'max:254',
            ],

            'staff_member.organizational_unit_id' => [
                'required_with:staff_member',
                'integer',
                $this->activeCatalogExistsRule('organizational_units'),
            ],

            'staff_member.position_id' => [
                'required_with:staff_member',
                'integer',
                $this->activeCatalogExistsRule('positions'),
            ],

            'staff_member.profession_id' => [
                'required_with:staff_member',
                'integer',
                $this->activeCatalogExistsRule('professions'),
            ],

            'staff_member.active' => [
                'prohibited',
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
            function (Validator $validator): void {
                $staffMember = $this->input('staff_member');

                if (
                    ! is_array($staffMember)
                    || blank($staffMember['ci'] ?? null)
                ) {
                    return;
                }

                $query = StaffMember::withTrashed()
                    ->where('ci', $staffMember['ci']);

                if (filled($staffMember['ci_complement'] ?? null)) {
                    $query->where(
                        'ci_complement',
                        $staffMember['ci_complement']
                    );
                } else {
                    $query->whereNull('ci_complement');
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'staff_member.ci',
                        'Ya existe personal con este CI y complemento.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge([
                'email' => Str::lower(
                    trim((string) $this->input('email'))
                ),
            ]);
        }

        $staffMember = $this->input('staff_member');

        if (! is_array($staffMember)) {
            return;
        }

        foreach (
            [
                'first_names',
                'paternal_surname',
                'maternal_surname',
                'ci',
                'phone',
            ] as $field
        ) {
            if (array_key_exists($field, $staffMember)) {
                $staffMember[$field] = filled($staffMember[$field])
                    ? trim((string) $staffMember[$field])
                    : null;
            }
        }

        if (array_key_exists('ci_complement', $staffMember)) {
            $staffMember['ci_complement'] = filled(
                $staffMember['ci_complement']
            )
                ? Str::upper(trim(
                    (string) $staffMember['ci_complement']
                ))
                : null;
        }

        if (array_key_exists('email', $staffMember)) {
            $staffMember['email'] = filled($staffMember['email'])
                ? Str::lower(trim((string) $staffMember['email']))
                : null;
        }

        $this->merge([
            'staff_member' => $staffMember,
        ]);
    }

    private function activeCatalogExistsRule(string $table): Exists
    {
        return Rule::exists($table, 'id')
            ->where(
                fn ($query) => $query
                    ->where('active', true)
                    ->whereNull('deleted_at')
            );
    }
}
