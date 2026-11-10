<?php

namespace App\Http\Requests\Investment;

use App\Enums\Auth\PermissionName;
use Illuminate\Foundation\Http\FormRequest;

class PublishProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('api')?->can(PermissionName::INVESTMENT_PROJECTS_PUBLISH->value) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'is_published' => ['required', 'boolean'],
            'published_at' => ['missing'],
        ];
    }
}
