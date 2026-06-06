<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placement' => ['required', Rule::in(['top', 'bottom'])],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'style_class' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_from' => ['nullable', 'date'],
            'published_until' => ['nullable', 'date', 'after_or_equal:published_from'],
            'status' => ['required', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],
        ];
    }
}
