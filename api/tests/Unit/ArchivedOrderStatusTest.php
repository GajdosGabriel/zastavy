<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Models\Order;
use PHPUnit\Framework\TestCase;

class ArchivedOrderStatusTest extends TestCase
{
    public function test_incomplete_archive_keeps_its_status_without_loading_items(): void
    {
        $order = new Order(['status' => OrderStatus::Archived]);
        $this->assertSame(OrderStatus::Archived, OrderStatus::fromOrder($order));
        $this->assertFalse($order->relationLoaded('orderProducts'));
    }
}
