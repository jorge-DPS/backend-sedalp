<?php

namespace App\Http\Requests\AccessControl;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserAccessRequest extends FormRequest
{
  public function authorize(): bool
  {
    $user = $this->user('api');

    if (! $user) {
      return false;
    }

    if (! $user->can('roles.assign')) {
      return false;
    }

    if (
      $this->filled('permissions')
      && ! $user->can('permissions.assign')
    ) {
      return false;
    }

    return true;
  }

  public function rules(): array
  {
    return [
      'role' => [
        'required',
        'string',

        Rule::exists('roles', 'name')
          ->where(
            fn($query) => $query->where('guard_name', 'api')
          ),
      ],

      'permissions' => [
        'sometimes',
        'array',
      ],

      'permissions.*' => [
        'string',
        'distinct',

        Rule::exists('permissions', 'name')
          ->where(
            fn($query) => $query->where('guard_name', 'api')
          ),
      ],
    ];
  }

  public function after(): array
  {
    return [
      function (Validator $validator): void {
        $authenticatedUser = $this->user('api');

        if (
          $this->input('role') === 'super_admin'
          && ! $authenticatedUser?->hasRole('super_admin')
        ) {
          $validator->errors()->add(
            'role',
            'No está autorizado para asignar el rol super_admin.'
          );
        }
      },
    ];
  }
}
