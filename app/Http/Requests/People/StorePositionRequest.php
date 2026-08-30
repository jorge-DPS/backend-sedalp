<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePositionRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user('api')
        ?->can('positions.create') ?? false;
  }

  public function rules(): array
  {
    return [
      'name' => [
        'required',
        'string',
        'max:100',
        Rule::unique('positions', 'name'),
      ],

      'description' => [
        'nullable',
        'string',
        'max:150',
      ],

      'active' => [
        'sometimes',
        'boolean',
      ],
    ];
  }

  protected function prepareForValidation(): void
  {
    if ($this->has('name')) {
      $this->merge([
        'name' => trim((string) $this->input('name')),
      ]);
    }
  }
}
