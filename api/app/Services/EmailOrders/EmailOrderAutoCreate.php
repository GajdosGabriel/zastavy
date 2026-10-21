<?php

namespace App\Services\EmailOrders;

use App\Actions\StoreOrder;
use App\Models\Customer;
use App\Models\EmailOrderDraft;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\ShippingMethod;
use App\Services\CustomerService;
use App\Services\OpenAI\EmailOrderAI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Automatické vytvorenie objednávky z e-mailu.
 *
 * Pravidlá sú zámerne prísne: objednávka vznikne sama, len keď nie je čo
 * domýšľať. Inak ostane návrh a dôvody sa uložia (`reasons`) – aj pri
 * vypnutom auto_create, aby bolo vidieť, ako často by automatika obstála.
 */
class EmailOrderAutoCreate
{
    private const REQUIRED = [
        'company' => 'názov odberateľa', 'name' => 'kontaktné meno', 'email' => 'e-mail', 'phone' => 'telefón',
        'street' => 'ulica', 'postcode' => 'PSČ', 'city' => 'mesto',
    ];

    /**
     * @param  array<string, mixed>  $preview  výstup EmailOrderImport::preview()
     * @return array<int, string>  prázdne = objednávka môže vzniknúť automaticky
     */
    public function reasons(EmailOrderDraft $draft, array $preview): array
    {
        $reasons = [];
        $customer = $preview['customer'] ?? [];

        if (! config('account.email_orders.auto_create')) {
            $reasons[] = 'Automatické vytváranie objednávok je vypnuté.';
        }

        if (($preview['customer_status'] ?? null) === 'ambiguous') {
            $reasons[] = 'Zákazník nie je jednoznačný.';
        } elseif (($preview['customer_status'] ?? null) === 'existing') {
            // Cudzí odosielateľ nesmie objednávať na účet existujúcej firmy.
            if (! $this->senderBelongsTo($draft->from_email, (int) ($customer['id'] ?? 0))) {
                $reasons[] = 'Odosielateľ nie je kontaktom nájdeného zákazníka.';
            }
        } elseif (empty($customer['ico'])) {
            $reasons[] = 'Nový zákazník bez IČO.';
        }

        foreach (self::REQUIRED as $field => $label) {
            if (trim((string) ($customer[$field] ?? '')) === '') {
                $reasons[] = 'Chýba '.$label.'.';
            }
        }

        $items = $preview['items'] ?? [];

        if ($items === []) {
            $reasons[] = 'Bez položiek.';
        }

        foreach ($items as $item) {
            if (empty($item['cart'])) {
                $reasons[] = 'Nespárovaná položka: '.$item['description'];
            } elseif ((int) ($item['quantity'] ?? 0) !== (int) $item['cart']['input_order']) {
                $reasons[] = 'Množstvo pod minimálnym odberom: '.$item['description'];
            }
        }

        foreach ($preview['ai_warnings'] ?? [] as $warning) {
            $reasons[] = 'AI: '.$warning;
        }

        $shipping = config('account.email_orders.shipping_method_id');

        if (ShippingMethod::where('active', true)->exists()
            && ! ($shipping && ShippingMethod::where('active', true)->whereKey($shipping)->exists())) {
            $reasons[] = 'Nie je nastavená predvolená doprava (EMAIL_ORDERS_SHIPPING_METHOD_ID).';
        }

        $payment = config('account.email_orders.payment_method_id');

        if ($payment && ! PaymentMethod::where('active', true)->whereKey($payment)->exists()) {
            $reasons[] = 'Predvolená platba nie je aktívna.';
        }

        return array_values(array_unique($reasons));
    }

    /** Vytvorí objednávku bežnou cestou e-shopu (ceny, minimá, číslo, potvrdenie zákazníkovi). */
    public function create(EmailOrderDraft $draft): Order
    {
        $preview = $draft->preview;
        $input = array_intersect_key($preview['customer'], array_flip(EmailOrderAI::FIELDS));

        $request = Request::create('/', 'POST', [
            'customer' => $input,
            'orderProducts' => collect($preview['items'])->map(fn ($item) => [
                'id' => $item['cart']['product_id'],
                'variant_id' => $item['cart']['variant_id'],
                'input_order' => $item['cart']['input_order'],
            ])->all(),
            'shipping_method_id' => config('account.email_orders.shipping_method_id'),
            'payment_method_id' => config('account.email_orders.payment_method_id'),
            'note' => mb_substr(trim((string) ($preview['note'] ?? '')), 0, 900) ?: null,
        ]);

        return DB::transaction(function () use ($draft, $preview, $input, $request) {
            $customers = new CustomerService;
            // Existujúca firma sa nemení – ako pri anonymnom nákupe.
            $customer = ! empty($preview['customer']['id'])
                ? Customer::findOrFail($preview['customer']['id'])
                : $customers->handle($input);
            $contact = $customers->storeUser($customer, $input);

            $order = (new StoreOrder($request))->handle($customer, $contact);
            app(EmailOrderInbox::class)->attach($draft, $order, auto: true);

            return $order;
        });
    }

    private function senderBelongsTo(?string $email, int $customerId): bool
    {
        if (! $email || ! $customerId) {
            return false;
        }

        return Customer::whereKey($customerId)->where(fn ($q) => $q->where('email', $email)
            ->orWhereHas('users', fn ($u) => $u->where('email', $email)))->exists();
    }
}
