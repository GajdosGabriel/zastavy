<?php

namespace App\Http\Requests;

use App\Models\ShippingMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class CreateOrderRequest extends OrderRequest
{
    public function rules(): array
    {
        $rules = [
            'idempotency_key' => ['required', 'uuid'],
            'customer' => ['required', 'array'],
            'customer.id' => $this->user('sanctum')?->isStaff()
                ? ['nullable', 'integer', 'exists:customers,id'] : ['prohibited'],
            'customer.company' => ['required', 'string', 'min:2', 'max:200'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.email' => ['required', 'email', 'max:150'],
            'customer.phone' => ['required', 'string', 'max:40'],
            'customer.street' => ['required', 'string', 'max:250'],
            'customer.postcode' => ['required', 'string', 'max:20'],
            'customer.city' => ['required', 'string', 'max:100'],
            'customer.ico' => ['nullable', 'string', 'max:30'],
            'customer.dic' => ['nullable', 'string', 'max:30'],
            'customer.ic_dic' => ['nullable', 'string', 'max:30'],
            ...self::deliveryRules(),
            'customer_address_id' => $this->user('sanctum')?->isStaff()
                ? ['nullable', 'integer'] : ['prohibited'],
            'orderProducts' => ['required', 'array', 'min:1'],
            'orderProducts.*.id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'orderProducts.*.variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->whereNull('deleted_at')],
            'orderProducts.*.input_order' => ['required', 'integer', 'min:1', 'max:100000'],
            'note' => ['nullable', 'string', 'max:1000'],
            'wants_coupon' => ['boolean'],
            'notify_customer' => ['sometimes', 'boolean'],
            // Povinná len ak je v admine aspoň jedna aktívna doprava, inak by sa
            // objednávka nedala odoslať vôbec.
            'shipping_method_id' => [ShippingMethod::where('active', true)->exists() ? 'required' : 'nullable', 'integer', Rule::exists('shipping_methods', 'id')->where('active', true)->whereNull('deleted_at')],
            'payment_method_id' => ['nullable', 'integer', Rule::exists('payment_methods', 'id')->where('active', true)->whereNull('deleted_at')],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'attachments' => ['nullable', 'array', 'max:'.config('media.attachments.max_files')],
            'attachments.*' => self::attachmentRules(),
        ];
        $key = $this->input('idempotency_key');
        $scope = $this->user('sanctum') ? 'user:'.$this->user('sanctum')->id : 'guest';
        $replay = is_string($key) && Str::isUuid($key)
            && DB::table('checkout_submissions')->where('key', $key)->where('actor_scope', $scope)->whereNotNull('order_id')->exists();
        if ($replay) {
            // Úspešné odoslanie sa musí dať zopakovať aj po vyradení dopravy/tovaru.
            // Obsah aj identitu opäť overí CreateOrderService pred vrátením UUID.
            foreach ($rules as $field => $checks) {
                $rules[$field] = array_values(array_filter($checks, fn ($rule) => ! $rule instanceof Exists));
            }
        }

        return $rules;
    }
}
