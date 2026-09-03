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
            'name' => 'required',
            'postcode' => 'required',
            'street' => 'required',
            'city' => 'required',
            'email' => 'required|email',
            // Kontrolná číslica IČO a tvar daňových čísel — tie isté pravidlá,
            // aké po uložení použije post-kontrola. V administrácii sedí za
            // formulárom personál, takže novú chybu má zmysel zastaviť hneď.
            'ico' => $this->taxRules('ico'),
            'dic' => $this->taxRules('dic'),
            'ic_dic' => $this->taxRules('ic_dic'),
            'status' => ['required', Rule::in(ModelStatus::allowedValuesForUser($this->user()))],
        ];
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
