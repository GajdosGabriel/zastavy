<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prefix' => ['nullable', 'string', 'max:40'],
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'postfix' => ['nullable', 'string', 'max:40'],
            'position' => ['nullable', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', Rule::in(config('app.supported_locales', []))],
            'note' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],
            'active' => ['sometimes', 'boolean'],
            'roles' => [
                Rule::prohibitedIf(! $this->user()?->hasAnyRole(['admin', 'super-admin'])),
                'sometimes',
                'array',
            ],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => [
                Rule::prohibitedIf(! $this->user()?->hasAnyRole(['admin', 'super-admin'])),
                'sometimes',
                'array',
            ],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
