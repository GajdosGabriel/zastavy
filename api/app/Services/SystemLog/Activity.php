<?php

namespace App\Services\SystemLog;

use App\Models\Order;
use Illuminate\Database\Eloquent\Model;

/** Business events use an explicit field allowlist; credentials never enter the log. */
class Activity
{
    public static function changes(Model $model, array $fields): array
    {
        $changes = [];
        foreach ($fields as $field) {
            if ($model->wasChanged($field)) {
                $changes[$field] = [
                    'before' => $model->getRawOriginal($field),
                    'after' => $model->getAttributes()[$field] ?? null,
                ];
            }
        }

        return $changes;
    }

    public static function record(string $channel, string $event, string $message, array $context = [], ?Order $order = null): void
    {
        Recorder::info($channel, $event, $message,
            status: 'ok',
            recipient: $order?->routeNotificationForMail(),
            userId: request()->user('sanctum')?->id,
            ip: app()->runningInConsole() ? null : request()->ip(),
            context: ($order ? [
                'order_id' => $order->id,
                'serial_number' => $order->serial_number,
                'order_uuid' => $order->uuid,
                'customer_id' => $order->customer_id,
            ] : []) + $context,
        );
    }
}
