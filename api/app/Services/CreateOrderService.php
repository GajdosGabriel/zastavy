<?php

namespace App\Services;

use App\Actions\StoreCheckout;
use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateOrderService
{
    public function handle(CreateOrderRequest $request): Order
    {
        $key = $request->validated('idempotency_key');
        $scope = $request->user('sanctum') ? 'user:'.$request->user('sanctum')->id : 'guest';
        $hash = hash('sha256', json_encode($this->canonical($request->safe()->except('idempotency_key')), JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($key, $scope, $hash, $request) {
            DB::table('checkout_submissions')->insertOrIgnore(['key' => $key, 'actor_scope' => $scope, 'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $submission = DB::table('checkout_submissions')->where('key', $key)->lockForUpdate()->first();
            abort_unless($submission && $submission->actor_scope === $scope && hash_equals($submission->request_hash, $hash), 409,
                'Tento identifikátor odoslania už patrí inej objednávke. Obnovte formulár.');
            if ($submission->order_id) {
                return Order::withTrashed()->findOrFail($submission->order_id);
            }
            $order = (new StoreCheckout($request))->getOrder();
            DB::table('checkout_submissions')->where('key', $key)->update(['order_id' => $order->id, 'updated_at' => now()]);

            return $order;
        }, 3);
    }

    private function canonical(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return ['name' => $value->getClientOriginalName(), 'sha256' => hash_file('sha256', $value->getRealPath())];
        }
        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn ($item) => $this->canonical($item), $value);
        }

        return $value === null ? null : (string) $value;
    }
}
