<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\OrderReturn;
use App\Models\SalesQuote;
use App\Models\Shipping;
use App\Models\Stock;
use App\Models\User;
use App\Services\SystemLog\Activity;
use Illuminate\Database\Eloquent\Model;

/** Records inside the business transaction: rollback removes the log too. */
class BusinessActivityObserver
{
    private const FIELDS = [
        OrderProduct::class => ['product_id', 'product_variant_id', 'quantity', 'price', 'total', 'storno', 'status', 'variant_label', 'product_snapshot'],
        OrderReturn::class => ['reason', 'note', 'status', 'restocked', 'processed_by'],
        Stock::class => ['product_id', 'product_variant_id', 'order_product_id', 'quantity', 'inventory_delta', 'price', 'received_at'],
        User::class => ['active', 'status'],
    ];

    public function created(Model $model): void
    {
        if ($model instanceof OrderProduct) {
            $this->write($model, 'item_added', 'Pridaná položka objednávky', $this->values($model));
        } elseif ($model instanceof Shipping && $model->prepared_items && ! $model->dispatched_at) {
            $this->write($model, 'prepared', 'Zásielka pripravená', ['items' => $model->prepared_items]);
        } elseif ($model instanceof Stock) {
            $this->write($model, 'created', 'Vytvorený skladový pohyb', $this->values($model));
        }
    }

    public function updated(Model $model): void
    {
        if ($model->wasChanged('deleted_at')) {
            return;
        }
        if ($model instanceof Order) {
            $this->orderUpdated($model);

        } elseif ($model instanceof SalesQuote) {
            if ($model->wasChanged('status') && $model->status === 'accepted') {
                Activity::record('quote', 'accepted', 'Prijatá cenová ponuka #'.$model->id,
                    ['quote_id' => $model->id, 'version' => $model->version, 'accepted_by' => $model->accepted_by], $model->order);
            }
        } else {
            $changes = Activity::changes($model, self::FIELDS[$model::class] ?? []);
            if (! $changes) {
                return;
            }
            [$event, $message] = match (true) {
                $model instanceof OrderProduct => ['item_updated', 'Upravená položka objednávky'],
                $model instanceof OrderReturn => match ($model->wasChanged('status') ? $model->status : null) {
                    'processed' => ['return_processed', 'Vratka vybavená'],
                    'cancelled' => ['return_cancelled', 'Vratka zrušená'],
                    default => ['return_updated', 'Vratka upravená'],
                },
                $model instanceof Stock => ['updated', 'Upravený skladový pohyb'],
                $model instanceof User => $this->userEvent($model),
            };
            $this->write($model, $event, $message, ['changes' => $changes]);
        }
    }

    public function deleted(Model $model): void
    {
        [$event, $message] = match (true) {
            $model instanceof Order => ['deleted', 'Objednávka odstránená'],
            $model instanceof OrderProduct => ['item_deleted', 'Položka objednávky odstránená'],
            $model instanceof Shipping => ['preparation_cancelled', 'Príprava zásielky zrušená'],
            $model instanceof OrderReturn => ['return_deleted', 'Vratka odstránená'],
            $model instanceof Stock => ['deleted', 'Skladový pohyb odstránený'],
            $model instanceof User => ['deleted', 'Používateľ odstránený'],
            default => [null, null],
        };
        if ($event) {
            $this->write($model, $event, $message, $this->values($model));
        }
    }

    public function restored(Model $model): void
    {
        [$event, $message] = match (true) {
            $model instanceof Order => ['restored', 'Objednávka obnovená'],
            $model instanceof OrderProduct => ['item_restored', 'Položka objednávky obnovená'],
            $model instanceof OrderReturn => ['return_restored', 'Vratka obnovená'],
            $model instanceof Stock => ['restored', 'Skladový pohyb obnovený'],
            $model instanceof User => ['restored', 'Používateľ obnovený'],
            default => [null, null],
        };
        if ($event) {
            $this->write($model, $event, $message);
        }
    }

    private function orderUpdated(Order $order): void
    {
        $groups = [
            'delivery_changed' => ['Zmenená doručovacia adresa', ['delivery_name', 'delivery_company', 'delivery_street', 'delivery_city', 'delivery_postcode', 'delivery_country', 'delivery_phone', 'delivery_note', 'customer_address_id']],
            'price_changed' => ['Zmenená cena alebo zľava objednávky', ['price_adjustment', 'discount_amount', 'coupon_id', 'shipping_price', 'payment_fee']],
            'updated' => ['Upravená objednávka', ['note', 'status', 'isOpened', 'wants_coupon', 'shipping_method_id', 'payment_method_id']],
        ];
        foreach ($groups as $event => [$message, $fields]) {
            $changes = Activity::changes($order, $fields);
            if (! $changes) {
                continue;
            }
            if ($event === 'updated' && $order->wasChanged('status') && $order->getAttributes()['status'] === 'cancelled') {
                $event = 'cancelled';
                $message = 'Objednávka stornovaná';
            }
            Activity::record('order', $event, $message.' #'.($order->serial_number ?: $order->id),
                ['changes' => $changes, 'changed_by' => $event === 'delivery_changed' ? $order->delivery_changed_by : null], $order);
        }
    }

    private function userEvent(User $user): array
    {
        $wasActive = (bool) $user->getRawOriginal('active')
            && ! in_array($user->getRawOriginal('status'), ['blocked', 'cancelled', 'archived'], true);
        if ($wasActive === $user->isActive()) {
            return ['status_changed', 'Zmenený stav používateľa'];
        }

        return $user->isActive() ? ['activated', 'Používateľ aktivovaný'] : ['deactivated', 'Používateľ deaktivovaný'];
    }

    private function values(Model $model): array
    {
        return array_intersect_key($model->getAttributes(), array_flip(self::FIELDS[$model::class] ?? []));
    }

    private function write(Model $model, string $event, string $message, array $context = []): void
    {
        $order = $model instanceof Order ? $model : (method_exists($model, 'order') ? $model->order : null);
        $channel = match (true) {
            $model instanceof Stock => 'stock',
            $model instanceof User => 'user',
            default => 'order',
        };
        if ($model instanceof OrderProduct) {
            $context += ['product_name' => $model->productSnapshot()['name'] ?? null];
        }
        if ($model instanceof OrderReturn) {
            $context += ['items' => $model->items()->get(['order_product_id', 'quantity'])->toArray()];
        }
        if ($model instanceof Stock) {
            $context += ['shipping_id' => $model->shipping_id, 'order_return_id' => $model->order_return_id];
        }
        $context = ['entity' => class_basename($model), 'entity_id' => $model->id] + $context;
        if ($model instanceof User) {
            $context += ['user_id' => $model->id, 'email' => $model->email];
        }
        Activity::record($channel, $event, $message.' #'.($order?->serial_number ?: $order?->id ?: $model->id), $context, $order);
    }
}
