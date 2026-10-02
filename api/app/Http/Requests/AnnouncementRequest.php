<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    /** Oznam sa zobrazuje len ako Active; ostatné stavy dávajú zmysel iba ako koncept alebo skrytie. */
    public const STATUSES = [ModelStatus::Draft, ModelStatus::Active, ModelStatus::Hidden];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placement' => ['required', Rule::in(['top', 'bottom'])],
            'title' => ['required', 'string', 'max:255'],
            // HtmlEditor posiela aj prázdny odsek („<p></p>"), preto sa text
            // kontroluje po odstránení značiek.
            'body' => ['required', 'string', function ($attribute, $value, $fail) {
                if (trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0") === '') {
                    $fail(__('validation.required', ['attribute' => $attribute]));
                }
            }],
            'style_class' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_from' => ['nullable', 'date'],
            'published_until' => ['nullable', 'date', 'after_or_equal:published_from'],
            'status' => ['required', Rule::in(array_map(fn (ModelStatus $s) => $s->value, self::STATUSES))],
        ];
    }
}
