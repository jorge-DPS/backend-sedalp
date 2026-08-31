<?php

namespace App\Http\Requests\People;

use App\Models\People\StaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('staff.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'first_names' => [
                'sometimes',
                'required',
                'string',
                'max:100',
            ],

            'paternal_surname' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
            ],

            'maternal_surname' => [
                'sometimes',
                'nullable',
                'string',
                'max:80',
            ],

            'birth_date' => [
                'sometimes',
                'nullable',
                'date',
                'before_or_equal:today',
            ],

            'ci' => [
                'sometimes',
                'required',
                'string',
                'max:15',
                'regex:/^[0-9]{1,15}$/',
            ],

            'ci_complement' => [
                'sometimes',
                'nullable',
                'string',
                'size:2',
                'regex:/^[A-Z0-9]{2}$/',
            ],

            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],

            'email' => [
                'sometimes',
                'nullable',
                'email:rfc',
                'max:254',
            ],

            'organizational_unit_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('organizational_units', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('active', true)
                            ->whereNull('deleted_at')
                    ),
            ],

            'position_id' => [
            'sometimes',
            'required',
            'integer',
            Rule::exists('positions', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('active', true)
                            ->whereNull('deleted_at')
                    ),
        ],

            'profession_id' => [
            'sometimes',
            'required',
            'integer',
            Rule::exists('professions', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('active', true)
                            ->whereNull('deleted_at')
                    ),
        ],

            'active' => [
            'sometimes',
            'boolean',
        ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var StaffMember $staffMember */
                $staffMember = $this->route('staffMember');

                $ci = $this->input(
                    'ci',
                    $staffMember->ci
                );

                $complement = $this->has('ci_complement')
                  ? $this->input('ci_complement')
                  : $staffMember->ci_complement;

                $query = StaffMember::withTrashed()
                    ->whereKeyNot($staffMember->getKey())
                    ->where('ci', $ci);

                if ($complement) {
                    $query->where(
                        'ci_complement',
                        $complement
                    );
                } else {
                    $query->whereNull('ci_complement');
                }

                if ($query->exists()) {
                    $validator->errors()->add(
                        'ci',
                        'Ya existe personal con este CI y complemento.'
                    );
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        foreach (
            ['first_names', 'paternal_surname', 'maternal_surname', 'ci', 'phone'] as $field
        ) {
            if ($this->has($field)) {
                $data[$field] = $this->filled($field)
                  ? trim((string) $this->input($field))
                  : null;
            }
        }

        if ($this->has('ci_complement')) {
            $data['ci_complement'] = $this->filled('ci_complement')
              ? strtoupper(trim(
                  (string) $this->input('ci_complement')
              ))
              : null;
        }

        if ($this->has('email')) {
            $data['email'] = $this->filled('email')
              ? strtolower(trim(
                  (string) $this->input('email')
              ))
              : null;
        }

        $this->merge($data);
    }
}
