<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ProductVariant;
use App\Services\Companies\CompanyRegistry;
use App\Services\OpenAI\EmailOrderAI;
use Illuminate\Support\Facades\Validator;

class EmailOrderImport
{
    public function __construct(private EmailOrderAI $ai, private CompanyRegistry $registry) {}

    public function preview(string $text): array
    {
        $variants = ProductVariant::where('published', true)
            ->whereHas('product', fn ($q) => $q->where('published', true))
            ->with(['product.images', 'image'])->orderBy('id')->get();
        $catalog = $variants->map(fn ($v) => [
            'variant_id' => $v->id, 'product' => $v->product->name,
            'variant' => $v->name, 'code' => $v->code,
        ])->all();
        $draft = $this->ai->extract($text, $catalog);
        Validator::make($draft, [
            'customer' => 'required|array', 'items' => 'present|array|max:100',
            'items.*.description' => 'required|string|max:1000',
            'items.*.variant_id' => 'nullable|integer', 'items.*.quantity' => 'nullable|integer|min:1|max:100000',
            'warnings' => 'present|array|max:100', 'warnings.*' => 'string|max:2000', 'note' => 'present|nullable|string|max:5000',
        ])->validate();
        $customer = $this->cleanCustomer($draft['customer']);
        $warnings = $draft['warnings'];
        $sources = [];
        [$match, $ambiguous] = $this->findCustomer($customer);
        $source = 'email';
        if ($match) {
            $customer = $this->mergeDatabase($customer, $match);
            $source = 'database';
        }
        if ($ambiguous) {
            $warnings[] = 'V databáze je viac zhodných alebo protichodných zákazníkov. Zákazník nebol automaticky priradený.';
        }

        // Prefer the deterministic registry lookup when IČO is already known.
        $customer = $this->fillFromRegistry($customer, $sources);
        if ($this->needsResearch($customer)) {
            try {
                $research = $this->ai->research(array_intersect_key($customer, array_flip(EmailOrderAI::FIELDS)));
                $urls = array_values(array_filter($research['sources'] ?? [], fn ($url) => is_string($url) && preg_match('~^https?://~i', $url) && filter_var($url, FILTER_VALIDATE_URL)));
                if (($research['confirmed'] ?? false) === true && count($urls)) {
                    $found = $this->cleanCustomer($research['customer'] ?? []);
                    // Never combine two legal entities with conflicting IČO.
                    if ($customer['ico'] && $found['ico'] && $customer['ico'] !== $found['ico']) {
                        $warnings[] = 'Web našiel subjekt s iným IČO. Jeho údaje neboli použité.';
                    } else {
                        $customer = $this->fillMissing($customer, $found);
                        $sources = array_merge($sources, $urls);
                        $source = $match ? 'database_with_internet' : 'internet';
                        if (! $match && ! $ambiguous) {
                            [$match, $ambiguous] = $this->findCustomer($customer);
                            if ($match) {
                                $customer = $this->mergeDatabase($customer, $match);
                                $source = 'database_with_internet';
                            }
                            if ($ambiguous) $warnings[] = 'Dohľadané údaje zodpovedajú viacerým zákazníkom. Skontrolujte identitu.';
                        }
                        $customer = $this->fillFromRegistry($customer, $sources);
                    }
                } else {
                    $warnings[] = 'Identitu zákazníka sa na webe nepodarilo jednoznačne overiť.';
                }
                foreach ($research['warnings'] ?? [] as $warning) {
                    if (is_string($warning)) $warnings[] = mb_substr($warning, 0, 2000);
                }
            } catch (\Throwable $e) {
                $warnings[] = 'Webové dohľadávanie je nedostupné. Návrh obsahuje dostupné údaje z e-mailu, databázy a registra.';
            }
        }
        $items = [];
        foreach ($draft['items'] as $item) {
            $variant = $variants->firstWhere('id', $item['variant_id'] ?? null);
            $quantity = $item['quantity'] ?? null;
            if (! $variant || ! $quantity) {
                $warnings[] = 'Ručne doplňte položku: '.$item['description'];
                $items[] = ['description' => $item['description'], 'cart' => null];
                continue;
            }
            $min = max(1, (int) $variant->min_order);
            if ($quantity < $min) {
                $warnings[] = 'Množstvo položky '.$item['description'].' bolo zvýšené na minimálny odber '.$min.'.';
            }
            $items[] = ['description' => $item['description'], 'cart' => [
                'product_id' => $variant->product_id, 'variant_id' => $variant->id,
                'name' => $variant->product->name, 'variant_name' => $variant->name,
                'slug' => $variant->product->slug, 'thumb' => $variant->thumb,
                'unit_value' => $variant->product->unit_value, 'vat' => $variant->product->vat,
                'active_price' => (float) $variant->active_price, 'min_order' => $min,
                'input_order' => max($quantity, $min),
            ]];
        }
        if (! count($items)) $warnings[] = 'V texte neboli nájdené objednávané položky.';
        foreach (['company' => 'názov firmy (ak nejde o súkromnú osobu)', 'name' => 'kontaktné meno', 'email' => 'e-mail', 'phone' => 'telefón', 'street' => 'ulica', 'city' => 'mesto', 'postcode' => 'PSČ', 'ico' => 'IČO', 'dic' => 'DIČ'] as $key => $label) {
            if (! $customer[$key]) $warnings[] = 'Chýba '.$label.'.';
        }
        return [
            'customer' => $customer, 'source' => $source,
            'customer_status' => $match ? 'existing' : ($ambiguous ? 'ambiguous' : 'new'),
            'items' => $items, 'note' => $draft['note'] ?? '',
            'warnings' => array_values(array_unique($warnings)), 'sources' => array_values(array_unique($sources)),
        ];
    }

    private function cleanCustomer(array $input): array
    {
        $data = [];
        foreach (EmailOrderAI::FIELDS as $field) {
            $data[$field] = is_string($input[$field] ?? null) ? mb_substr(trim($input[$field]), 0, 250) : '';
        }
        foreach (['ico' => 8, 'dic' => 10] as $field => $length) {
            $digits = preg_replace('/\s+/', '', $data[$field]);
            $data[$field] = preg_match('/^\d{'.$length.'}$/', $digits) ? $digits : '';
        }
        if ($data['email'] && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) $data['email'] = '';
        return $data;
    }

    private function findCustomer(array $data): array
    {
        if (! $data['ico'] && ! $data['email']) return [null, false];
        $matches = Customer::with('latestUser')->where(function ($q) use ($data) {
            if ($data['ico']) $q->orWhereIn('ico', [$data['ico'], ltrim($data['ico'], '0')]);
            if ($data['email']) {
                $q->orWhere('email', $data['email'])->orWhereHas('users', fn ($u) => $u->where('email', $data['email']));
            }
        })->limit(3)->get();
        if ($matches->count() !== 1) return [null, $matches->count() > 1];
        $match = $matches->first();
        if ($data['ico'] && $match->ico && str_pad(preg_replace('/\D/', '', $match->ico), 8, '0', STR_PAD_LEFT) !== $data['ico']) return [null, true];
        return [$match, false];
    }

    private function mergeDatabase(array $data, Customer $customer): array
    {
        $db = $this->cleanCustomer([
            ...$customer->only(EmailOrderAI::FIELDS),
            'name' => $customer->latestUser?->username ?? '',
            'email' => $customer->latestUser?->email ?: $customer->email,
            'phone' => $customer->latestUser?->phone ?: $customer->phone,
        ]);
        return $this->fillMissing($data, $db) + ['id' => $customer->id];
    }

    private function fillMissing(array $data, array $fallback): array
    {
        foreach (EmailOrderAI::FIELDS as $field) {
            if (! ($data[$field] ?? '')) $data[$field] = $fallback[$field] ?? '';
        }
        return $data;
    }

    private function fillFromRegistry(array $data, array &$sources): array
    {
        if ($data['ico'] && ($registry = $this->registry->find($data['ico']))) {
            $data = $this->fillMissing($data, $this->cleanCustomer($registry));
            $sources[] = 'https://api.orsf.sk/v1/companies/'.$data['ico'];
        }
        return $data;
    }

    private function needsResearch(array $data): bool
    {
        if (! $data['company'] && ! $data['email'] && ! $data['ico']) return false;
        foreach (['company', 'street', 'city', 'postcode', 'ico', 'dic', 'phone'] as $field) {
            if (! $data[$field]) return true;
        }
        return false;
    }
}
