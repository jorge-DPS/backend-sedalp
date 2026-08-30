<?php

namespace App\Http\Requests\People;

use App\Models\People\StaffMember;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStaffMemberRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user('api')
        ?->can('staff.create') ?? false;
  }

  public function rules(): array
  {
    return [
      'first_names' => [
        'required',
        'string',
        'max:100',
      ],

      'paternal_surname' => [
        'nullable',
        'string',
        'max:80',
      ],

      'maternal_surname' => [
        'nullable',
        'string',
        'max:80',
      ],

      'birth_date' => [
        'nullable',
        'date',
        'before_or_equal:today',
      ],

      'ci' => [
        'required',
        'string',
        'max:15',
        'regex:/^[0-9]{1,15}$/',
      ],

      'ci_complement' => [
        'nullable',
        'string',
        'size:2',
        'regex:/^[A-Z0-9]{2}$/',
      ],

      'phone' => [
        'nullable',
        'string',
        'max:20',
      ],

      'email' => [
        'nullable',
        'email:rfc',
        'max:254',
      ],

      'organizational_unit_id' => [
        'required',
        'integer',
        Rule::exists('organizational_units', 'id')
          ->where(
            fn($query) => $query
              ->where('active', true)
              ->whereNull('deleted_at')
          ),
      ],

      'position_id' => [
        'required',
        'integer',
        Rule::exists('positions', 'id')
          ->where(
            fn($query) => $query
              ->where('active', true)
              ->whereNull('deleted_at')
          ),
      ],

      'profession_id' => [
        'required',
        'integer',
        Rule::exists('professions', 'id')
          ->where(
            fn($query) => $query
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
        if (!$this->filled('ci')) {
          return;
        }

        $query = StaffMember::withTrashed()
          ->where('ci', $this->input('ci'));

        if ($this->filled('ci_complement')) {
          $query->where(
            'ci_complement',
            $this->input('ci_complement')
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
    $this->merge([
      'first_names' => trim(
        (string) $this->input('first_names')
      ),

      'paternal_surname' => $this->filled('paternal_surname')
        ? trim((string) $this->input('paternal_surname'))
        : null,

      'maternal_surname' => $this->filled('maternal_surname')
        ? trim((string) $this->input('maternal_surname'))
        : null,

      'ci' => trim(
        (string) $this->input('ci')
      ),

      'ci_complement' => $this->filled('ci_complement')
        ? strtoupper(trim(
          (string) $this->input('ci_complement')
        ))
        : null,

      'phone' => $this->filled('phone')
        ? trim((string) $this->input('phone'))
        : null,

      'email' => $this->filled('email')
        ? strtolower(trim(
          (string) $this->input('email')
        ))
        : null,
    ]);
  }
}
