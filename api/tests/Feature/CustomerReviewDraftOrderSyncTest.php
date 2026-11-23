<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\CustomerReview;
use App\Models\Order;
use App\Services\Companies\CompanyRegistry;
use App\Services\Customers\CustomerReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Oprava zákazníka po checkoute (napr. „Maria" v poli Firma → názov podľa
 * registra) sa má dostať aj do ešte nespracovanej objednávky, inak ju výroba
 * a faktúra prevezmú so zlým názvom.
 */
class CustomerReviewDraftOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    private const OFFICIAL = 'Regionálny úrad verejného zdravotníctva so sídlom v Komárne';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['customer_review.ai' => false, 'customer_review.registry' => true]);

        $this->mock(CompanyRegistry::class)->shouldReceive('find')
            ->andReturn(['ico' => '17335655', 'company' => self::OFFICIAL, 'dic' => '2021015150', 'ic_dic' => 'SK2021015150']);
    }

    private function customer(): Customer
    {
        return Customer::create(['company' => 'Maria', 'email' => 'maria@example.test', 'street' => 'Mederčská 39',
            'city' => 'Komárno', 'postcode' => '94501', 'ico' => '17335655', 'dic' => '2021015150', 'ic_dic' => 'SK2021015150']);
    }

    private function order(Customer $customer, OrderStatus $status = OrderStatus::Draft): Order
    {
        $order = Order::create(['customer_id' => $customer->id, 'name' => 'Mária Bohosová', 'email' => 'maria@example.test']);
        $order->forceFill(['status' => $status])->saveQuietly();

        return $order;
    }

    private function review(Customer $customer): CustomerReview
    {
        $review = app(CustomerReviewService::class)->reviewFor($customer)
            ?? CustomerReview::create(['customer_id' => $customer->id]);
        app(CustomerReviewService::class)->run($review);

        return $review->fresh();
    }

    public function test_company_fix_is_copied_into_draft_order_only(): void
    {
        $customer = $this->customer();
        $draft = $this->order($customer);
        $processing = $this->order($customer, OrderStatus::Processing);

        $this->review($customer);

        $this->assertSame(self::OFFICIAL, $customer->fresh()->company);
        $this->assertSame(self::OFFICIAL, $draft->fresh()->billing_snapshot['company']);
        $this->assertSame('Maria', $processing->fresh()->billing_snapshot['company']);
    }

    public function test_value_changed_by_hand_in_order_is_kept(): void
    {
        $customer = $this->customer();
        $draft = $this->order($customer);
        $draft->forceFill(['billing_snapshot' => ['company' => 'Ručne opravené'] + $draft->billing_snapshot])->saveQuietly();

        $this->review($customer);

        $this->assertSame('Ručne opravené', $draft->fresh()->billing_snapshot['company']);
    }

    public function test_revert_restores_draft_order_as_well(): void
    {
        $customer = $this->customer();
        $draft = $this->order($customer);
        $review = $this->review($customer);

        $index = collect($review->applied)->search(fn ($c) => $c['field'] === 'company');
        $this->assertNotFalse($index);

        app(CustomerReviewService::class)->revertApplied($review, [$index]);

        $this->assertSame('Maria', $customer->fresh()->company);
        $this->assertSame('Maria', $draft->fresh()->billing_snapshot['company']);
    }
}
