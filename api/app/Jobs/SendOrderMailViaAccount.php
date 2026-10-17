<?php

namespace App\Jobs;

use App\Listeners\SystemLogSubscriber;
use App\Models\Order;
use App\Services\Account\OrderMailer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * E-mail zákazníkovi k objednávke cez Account (Order::notifyCustomer).
 *
 * Výpadok Accountu sa rieši opakovaním; po poslednom pokuse sa neúspech
 * zapíše do histórie e-mailov objednávky, aby ho obsluha videla.
 */
class SendOrderMailViaAccount implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(
        public Order $order,
        public Notification $notification,
    ) {}

    public function handle(OrderMailer $mailer): void
    {
        $mailer->send($this->order, $this->notification);
    }

    public function failed(Throwable $e): void
    {
        SystemLogSubscriber::recordOrderEmail(null, class_basename($this->notification), 'E-mail k objednávke',
            $this->order->routeNotificationForMail(), 'failed', error: 'Account: '.$e->getMessage(), orderId: $this->order->id);
    }
}
