<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;


class OrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            'shipping_method_id' => ['sometimes', 'required', 'integer', \Illuminate\Validation\Rule::exists('shipping_methods', 'id')->where('active', true)->whereNull('deleted_at')],
            'payment_method_id' => ['sometimes', 'nullable', 'integer', \Illuminate\Validation\Rule::exists('payment_methods', 'id')->where('active', true)->whereNull('deleted_at')],
            'status' => ['sometimes', \Illuminate\Validation\Rule::enum(\App\Enums\OrderStatus::class)],
            'isOpened' => ['sometimes', 'boolean'],
            'wants_coupon' => ['sometimes', 'boolean'],
            'notify_customer' => ['sometimes', 'boolean'],
            'makeStorned' => ['sometimes', 'boolean'],
            'has_product_changes' => ['sometimes', 'boolean'],
            ...self::deliveryRules(),
        ];
    }

    /**
     * Doručovacia adresa je nepovinná — bez nej sa doručuje na sídlo zákazníka.
     * Keď však príde, musí byť adresou, na ktorú sa dá naozaj poslať balík:
     * pol adresy je horšie než žiadna, lebo vyzerá vyplnene.
     */
    public static function deliveryRules(): array
    {
        return [
            'customer_address_id'   => ['nullable', 'integer'],
            'delivery'              => ['nullable', 'array'],
            'delivery.company'      => ['nullable', 'string', 'max:200'],
            'delivery.name'         => ['nullable', 'string', 'max:150'],
            'delivery.street'       => ['required_with:delivery', 'string', 'max:250'],
            'delivery.postcode'     => ['required_with:delivery', 'string', 'max:20'],
            'delivery.city'         => ['required_with:delivery', 'string', 'max:100'],
            'delivery.country'      => ['nullable', 'string', 'size:2'],
            'delivery.phone'        => ['nullable', 'string', 'max:40'],
            'delivery.note'         => ['nullable', 'string', 'max:255'],
            'delivery.label'        => ['nullable', 'string', 'max:100'],
            'delivery.save_address' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Formulár posiela `delivery` aj vtedy, keď zákazník zaškrtnutie inej adresy
     * zase zrušil — prídu prázdne polia. Bez tohto by `required_with` vypýtal
     * ulicu k adrese, ktorú nikto nechce.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('delivery')) {
            return;
        }

        $delivery = $this->input('delivery');

        if (! is_array($delivery)) {
            $this->merge(['delivery' => null]);

            return;
        }

        $filled = collect($delivery)
            ->only(['company', 'name', 'street', 'postcode', 'city', 'phone', 'note'])
            ->contains(fn ($value) => filled($value));

        if (! $filled) {
            $this->merge(['delivery' => null]);
        }
    }

    /** Rovnaké hlášky pre košík aj pre verejnú zmenu adresy z e-mailu. */
    public static function deliveryMessages(): array
    {
        return [
            'delivery.street.required_with'   => 'Vyplňte ulicu a číslo doručovacej adresy.',
            'delivery.postcode.required_with' => 'Vyplňte PSČ doručovacej adresy.',
            'delivery.city.required_with'     => 'Vyplňte mesto doručovacej adresy.',
        ];
    }

    /** Pravidlá pre jednu prílohu košíka — zdieľané aj s dodatočným uploadom v detaile objednávky. */
    public static function attachmentRules(): array
    {
        return [
            'file',
            'max:' . config('media.attachments.max_size'),
            // Exotické grafické formáty (.ai, .cdr, .eps) sa cez pravidlo "mimes" nedajú
            // spoľahlivo rozpoznať podľa obsahu — validujeme príponu a súbor
            // vždy servírujeme ako download, nikdy sa neinterpretuje.
            'extensions:' . implode(',', config('media.attachments.extensions')),
        ];
    }

    public function messages()
    {
        return self::deliveryMessages() + [
            'attachments.max' => 'Naraz je možné priložiť najviac ' . config('media.attachments.max_files') . ' súborov.',
            'attachments.*.max' => 'Príloha môže mať najviac ' . round(config('media.attachments.max_size') / 1024) . ' MB.',
            'attachments.*.extensions' => 'Nepodporovaný typ prílohy.',
        ];
    }
}
