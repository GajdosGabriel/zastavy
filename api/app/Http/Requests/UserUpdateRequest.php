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
            'firstName' => ['required', 'string', 'max:255'],
            'lastName' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            'phone' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],
            'roles' => [
                Rule::prohibitedIf(! $this->user()?->hasAnyRole(['admin', 'super-admin'])),
                'sometimes',
                'array',
            ],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }
}
