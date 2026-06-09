<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstName'   => ['required', 'string', 'max:255'],
            'lastName'    => ['nullable', 'string', 'max:255'],
            'email'       => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone'       => ['nullable', 'string', 'max:40'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'roles' => [
                Rule::prohibitedIf(! $this->user()?->hasRole('super-admin')),
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
