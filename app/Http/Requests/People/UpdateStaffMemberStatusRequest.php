<?php

namespace App\Http\Requests\People;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStaffMemberStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')
            ?->can('staff.status.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'active' => [
                'required',
                'boolean',
            ],
            'reason' => [
                'required',
                'string',
                'max:1000',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('reason')) {
            $this->merge([
                'reason' => trim((string) $this->input('reason')),
            ]);
        }
    }
}
