<?php

namespace App\Http\Requests\AccessControl;

use App\Models\People\StaffMember;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can('users.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'staff_member' => [
                'sometimes',
                'array:first_names,paternal_surname,maternal_surname,birth_date,ci,ci_complement,phone,email,organizational_unit_id,position_id,profession_id',
            ],

            'staff_member.first_names' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'staff_member.paternal_surname' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
            ],

            'staff_member.maternal_surname' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
            ],

            'staff_member.birth_date' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'staff_member.ci' => [
                'sometimes',
                'required',
                'string',
                'max:15',
                'regex:/^[0-9]{1,15}$/',
            ],

            'staff_member.ci_complement' => [
                'sometimes',
                'nullable',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
            ],

            'staff_member.phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'staff_member.email' => [
                'sometimes',
                'nullable',
                'email:rfc',
                'max:254',
            ],

            'staff_member.organizational_unit_id' => [
                'sometimes',
                'required',
                'integer',
                $this->activeCatalogExistsRule('organizational_units'),
            ],

            'staff_member.position_id' => [
                'sometimes',
                'required',
                'integer',
                $this->activeCatalogExistsRule('positions'),
            ],

            'staff_member.profession_id' => [
                'sometimes',
                'required',
                'integer',
                $this->activeCatalogExistsRule('professions'),
            ],

            'staff_member.active' => [
                'prohibited',
            ],

            'email' => [
                'sometimes',
                'required',
                'string',
                'email:rfc',
                'max:255',

                Rule::unique('users', 'email')
                    ->ignore($this->route('user')),
            ],

            'password' => [
                'sometimes',
                'required',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->route('user');
                $staffData = $this->input('staff_member');

                if (
                    ! $user instanceof User
                    || ! is_array($staffData)
                ) {
                    return;
                }

                if ($user->staff_member_id === null) {
                    foreach (
                        [
                            'first_names',
                            'ci',
                            'organizational_unit_id',
                            'position_id',
                            'profession_id',
                        ] as $field
                    ) {
                        if (blank($staffData[$field] ?? null)) {
                            $validator->errors()->add(
                                "staff_member.{$field}",
                                'Este campo es obligatorio para crear el personal asociado.'
                            );
                        }
                    }
                }

                $staffMember = $user->staffMember()
                    ->withTrashed()
                    ->first();

                $ci = $staffData['ci']
                    ?? $staffMember?->ci;

                if (blank($ci)) {
                    return;
                }

                $complement = array_key_exists(
                    'ci_complement',
                    $staffData
                )
                    ? $staffData['ci_complement']
                    : $staffMember?->ci_complement;

                $query = StaffMember::withTrashed()
                    ->when(
                        $staffMember,
                        fn ($query) => $query->whereKeyNot(
                            $staffMember->id
                        )
                    )
                    ->where('ci', $ci);

                if (filled($complement)) {
                    $query->where('ci_complement', $complement);
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
