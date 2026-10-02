<?php

namespace App\Http\Requests;

use App\Enums\ModelStatus;
use App\Rules\CustomerTaxId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends FormRequest
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
            // Dĺžky kopírujú stĺpce v databáze — dlhší text by skončil SQL chybou.
            'name' => 'required|string|max:150',
            'company' => 'nullable|string|max:200',
            'postcode' => $this->postcodeRules(),
            'street' => 'nullable|string|max:250',
            'city' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'note' => 'nullable|string|max:255',
            'phone' => $this->phoneRules(),
            // Kontrolná číslica IČO a tvar daňových čísel — tie isté pravidlá,
            // aké po uložení použije post-kontrola. V administrácii sedí za
            // formulárom personál, takže novú chybu má zmysel zastaviť hneď.
            'ico' => $this->taxRules('ico'),
            'dic' => $this->taxRules('dic'),
            'ic_dic' => $this->taxRules('ic_dic'),
            'status' => ['required', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],
        ];
    }

    public function messages()
    {
        return [
            'postcode.regex' => __('rules.postcode.invalid'),
        ];
    }

    public function attributes()
    {
        // Všeobecný preklad `name` je „názov" — tu ide o človeka.
        return [
            'name' => 'kontaktné meno',
            'note' => 'poznámka',
        ];
    }

    /** PSČ: päť číslic, medzera v zápise „811 01" je v poriadku. */
    protected function postcodeRules(): array
    {
        return ['required', 'regex:/^\d{3}\s?\d{2}$/'];
    }

    /** Telefón sa kontroluje len keď sa mení — staré záznamy nechávame post-kontrole. */
    protected function phoneRules(): array
    {
        $customer = $this->route('customer');

        if ($customer instanceof \App\Models\Customer) {
            $stored = str_replace(' ', '', (string) ($customer->getAttributes()['phone'] ?? ''));

            if (str_replace(' ', '', trim((string) $this->input('phone'))) === $stored) {
                return ['nullable'];
            }
        }

        return ['nullable', new \App\Rules\CustomerPhone()];
    }

    /**
     * Kontrola daňového čísla — len keď sa naozaj mení.
     *
     * V tabuľke je zhruba šesťdesiat starých záznamov s DIČ, ktoré nemá desať
     * číslic, a s IČO, ktoré nesedí na kontrolnú číslicu. Keby pravidlo platilo
     * vždy, nedal by sa takému zákazníkovi zmeniť ani status bez toho, aby sa
     * najprv dohľadalo správne číslo. Nové chyby zastavíme, staré necháme
     * post-kontrole — tá ich vypíše aj s návrhom z registra.
     *
     * @return array<int, mixed>
     */
    private function taxRules(string $field): array
    {
        $customer = $this->route('customer');
        $submitted = trim((string) $this->input($field));

        if ($customer instanceof \App\Models\Customer) {
            $stored = trim((string) ($customer->getAttributes()[$field] ?? ''));

            if ($submitted === $stored) {
                return ['nullable'];
            }
        }

        return ['nullable', new CustomerTaxId($field)];
    }
}
