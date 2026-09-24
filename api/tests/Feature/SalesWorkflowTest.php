<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesQuote;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Notifications\OrderCreated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $variant;

    private ShippingMethod $shipping;

    private array $customer = ['company' => 'Test buyer', 'name' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '0900123456', 'street' => 'Street 1', 'city' => 'Nitra', 'postcode' => '94901'];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $p = Product::create(['name' => 'Flag', 'code' => 'FLAG', 'published' => true, 'vat' => 23, 'unit_value' => 'ks']);
        $this->variant = $p->variants()->create(['name' => 'Large', 'code' => 'FLAG-L', 'published' => true, 'price' => 12, 'is_default' => true, 'min_order' => 1]);
        $this->shipping = ShippingMethod::create(['name' => 'Courier', 'price' => 5, 'active' => true]);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->assignRole('super-admin');
        Sanctum::actingAs($u);

        return $u;
    }

    private function quote(): array
    {
        return $this->postJson('/api/quote-requests', ['customer' => $this->customer, 'brief' => '100 x 150 cm, 2 kusy', 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]])->assertCreated()->json();
    }

    private function offer(int $version = 0): array
    {
        return ['version' => $version, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2, 'price' => 9.50]], 'shipping_method_id' => $this->shipping->id, 'shipping_price' => 3, 'terms' => 'Dodanie do 10 dní', 'valid_until' => today()->addDays(7)->toDateString()];
    }

    private function offered(): array
    {
        $link = $this->quote();
        $this->staff();
        $q = SalesQuote::firstOrFail();
        $this->postJson('/api/sales-quotes/'.$q->id.'/offer', $this->offer())->assertOk()->assertJsonPath('versions.0.valid_until', today()->addDays(7)->toDateString());

        return $link;
    }

    private function accept(array $link, int $version = 1)
    {
        $q = SalesQuote::where('uuid', $link['uuid'])->first();
        $this->assertNotNull($q);
        $this->assertSame(hash('sha256', $link['token']), $q->token_hash);
        $this->assertTrue($q->token_expires_at->isFuture(), $q->token_expires_at.' vs '.now());

        return $this->postJson('/api/public-quotes/'.$link['uuid'].'/accept', ['version' => $version, 'name' => 'Buyer', 'confirm' => true], ['X-Sales-Token' => $link['token']]);
    }

    private function order(): Order
    {
        $this->postCheckout(['customer' => $this->customer, 'orderProducts' => [['id' => $this->variant->product_id, 'variant_id' => $this->variant->id, 'input_order' => 2]]])->assertOk();

        return Order::latest('id')->firstOrFail();
    }

    private function upload(Order $order)
    {
        return $this->postJson('/api/orders/'.$order->id.'/artworks', ['file' => UploadedFile::fake()->create('design.pdf', 2, 'application/pdf'), 'note' => 'Verzia na kontrolu'])->assertOk()->json();
    }

    public function test_quote_acceptance_uses_frozen_prices_once(): void
    {
        $link = $this->offered();
        $this->variant->update(['price' => 99, 'name' => 'Renamed']);
        $a = $this->accept($link)->assertOk()->json();
        $this->accept($link)->assertOk()->assertExactJson($a);
        $this->assertDatabaseCount('orders', 1);
        $o = Order::firstOrFail();
        $this->assertEquals(9.5, $o->orderProducts->first()->price);
        $this->assertSame('Large', $o->orderProducts->first()->variant_name);
        $this->assertEquals(3, $o->shipping_price);
        Notification::assertSentToTimes($o, OrderCreated::class, 1);
    }

    public function test_old_quote_version_and_expired_quote_cannot_be_accepted(): void
    {
        $link = $this->offered();
        $q = SalesQuote::firstOrFail();
        $this->postJson('/api/sales-quotes/'.$q->id.'/offer', $this->offer(1))->assertOk();
        $this->accept($link, 1)->assertConflict();
        $q->versions()->where('version', 2)->update(['valid_until' => today()->subDay()]);
        $this->accept($link, 2)->assertConflict();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_withdrawn_quote_and_removed_variant_are_blocked(): void
    {
        $link = $this->offered();
        $this->variant->delete();
        $this->accept($link)->assertConflict();
        $this->postJson('/api/sales-quotes/'.SalesQuote::first()->id.'/withdraw')->assertOk();
        $this->accept($link)->assertConflict();
    }

    public function test_quote_requires_secret_token_and_rotation_revokes_old_link(): void
    {
        $link = $this->offered();
        $url = '/api/public-quotes/'.$link['uuid'];
        $this->getJson($url)->assertNotFound();
        $this->getJson($url, ['X-Sales-Token' => $link['token']])->assertOk()->assertJsonMissingPath('token_hash');
        $new = $this->postJson('/api/sales-quotes/'.SalesQuote::first()->id.'/share')->assertOk()->json();
        $this->getJson($url, ['X-Sales-Token' => $link['token']])->assertNotFound();
        $this->getJson($url, ['X-Sales-Token' => $new['token']])->assertOk();
    }

    public function test_customer_cannot_price_quotes_or_access_internal_lists(): void
    {
        $link = $this->quote();
        $o = $this->order();
        $u = User::factory()->create(['customer_id' => $o->customer_id]);
        Sanctum::actingAs($u);
        $this->getJson('/api/sales-quotes')->assertForbidden();
        $this->postJson('/api/sales-quotes/'.SalesQuote::first()->id.'/offer', $this->offer())->assertForbidden();
        $this->getJson('/api/production')->assertForbidden();
        $this->putJson('/api/orders/'.$o->id.'/production', ['status' => 'in_progress'])->assertForbidden();
    }

    public function test_quote_files_are_private_and_scoped(): void
    {
        $link = $this->postJson('/api/quote-requests', ['customer' => $this->customer, 'brief' => 'Logo', 'attachments' => [UploadedFile::fake()->create('logo.pdf', 2, 'application/pdf')]])->assertCreated()->json();
        $q = SalesQuote::firstOrFail();
        $a = $q->attachments->first();
        Storage::disk('local')->assertExists($a->path);
        $this->assertSame('local', $a->disk);
        $url = '/api/public-quotes/'.$link['uuid'].'/files/'.$a->id;
        $this->getJson($url)->assertForbidden();
        $this->getJson($url, ['X-Sales-Token' => $link['token']])->assertOk();
        $other = $this->quote();
        $this->getJson('/api/public-quotes/'.$other['uuid'].'/files/'.$a->id, ['X-Sales-Token' => $other['token']])->assertNotFound();
    }

    public function test_artwork_approval_gates_production_and_records_version(): void
    {
        $o = $this->order();
        $this->staff();
        $this->putJson('/api/orders/'.$o->id.'/production', ['status' => 'in_progress'])->assertConflict();
        $link = $this->upload($o);
        $url = '/api/public-artworks/'.$o->uuid.'/decision';
        $this->postJson($url, ['action' => 'approve', 'version' => 1, 'name' => 'Buyer', 'confirm' => true])->assertNotFound();
        $this->postJson($url, ['action' => 'comment', 'version' => 1, 'name' => 'Buyer', 'comment' => 'Upraviť farbu'], ['X-Sales-Token' => $link['token']])->assertOk();
        $link = $this->upload($o);
        $this->postJson($url, ['action' => 'approve', 'version' => 1, 'name' => 'Buyer', 'confirm' => true], ['X-Sales-Token' => $link['token']])->assertConflict();
        $this->postJson($url, ['action' => 'approve', 'version' => 2, 'name' => 'Buyer', 'confirm' => true], ['X-Sales-Token' => $link['token']])->assertOk();
        $this->assertDatabaseHas('artwork_versions', ['order_id' => $o->id, 'version' => 2, 'status' => 'approved', 'approved_by' => 'Buyer', 'approved_email' => $this->customer['email']]);
        $this->putJson('/api/orders/'.$o->id.'/production', ['status' => 'in_progress', 'production_due_at' => today()->toDateString(), 'delivery_due_at' => today()->addDay()->toDateString()])->assertOk();
        $this->postJson('/api/orders/'.$o->id.'/artworks', ['file' => UploadedFile::fake()->create('new.pdf', 2, 'application/pdf')])->assertConflict();
        $this->putJson('/api/orders/'.$o->id.'/production', ['status' => 'completed'])->assertOk();
        $this->putJson('/api/orders/'.$o->id.'/production', ['status' => 'ready'])->assertConflict();
    }

    public function test_new_artwork_invalidates_ready_status_and_old_token(): void
    {
        $o = $this->order();
        $this->staff();
        $link = $this->upload($o);
        $this->postJson('/api/public-artworks/'.$o->uuid.'/decision', ['action' => 'approve', 'version' => 1, 'name' => 'Buyer', 'confirm' => true], ['X-Sales-Token' => $link['token']])->assertOk();
        $this->upload($o);
        $this->assertDatabaseHas('order_productions', ['order_id' => $o->id, 'status' => 'awaiting_artwork']);
        $this->getJson('/api/public-artworks/'.$o->uuid, ['X-Sales-Token' => $link['token']])->assertNotFound();
    }

    public function test_production_hides_internal_notes_and_filters_overdue(): void
    {
        $o = $this->order();
        $this->staff();
        $link = $this->upload($o);
        $this->putJson('/api/orders/'.$o->id.'/production', ['status' => 'awaiting_artwork', 'production_due_at' => today()->subDay()->toDateString(), 'materials' => 'Polyester', 'note' => 'Internal'])->assertOk();
        $this->getJson('/api/production?overdue=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.overdue', true)->assertJsonPath('data.0.production_due_at', today()->subDay()->toDateString());
        $this->getJson('/api/public-artworks/'.$o->uuid, ['X-Sales-Token' => $link['token']])->assertOk()->assertJsonMissingPath('production.note')->assertJsonMissingPath('versions.0.path');
    }

    public function test_cancelled_order_cannot_enter_production(): void
    {
        $o = $this->order();
        $o->update(['status' => 'cancelled']);
        $this->staff();
        $this->putJson('/api/orders/'.$o->id.'/production', ['status' => 'awaiting_artwork'])->assertConflict();
    }

    public function test_reorder_shows_current_price_minimum_and_unavailable_items(): void
    {
        $o = $this->order();
        $this->variant->update(['price' => 20, 'min_order' => 5]);
        $this->getJson('/api/public-orders/'.$o->uuid.'/reorder')->assertOk()->assertJsonPath('items.0.price_changed', true)->assertJsonPath('items.0.quantity_changed', true)->assertJsonPath('items.0.cart.input_order', 5);
        $this->variant->delete();
        $this->getJson('/api/public-orders/'.$o->uuid.'/reorder')->assertOk()->assertJsonPath('items.0.available', false)->assertJsonPath('items.0.cart', null);
    }

    public function test_catalog_paginates_searches_and_hides_deleted_products(): void
    {
        foreach (range(1, 17) as $i) {
            Product::create(['name' => 'Other '.$i, 'code' => 'OTHER-'.$i, 'published' => true, 'vat' => 23]);
        }
        $this->getJson('/api/homes?page=2')->assertOk()->assertJsonCount(3,'data');
        $this->getJson('/api/homes?bySearchInput=FLAG-L')->assertOk()->assertJsonCount(1,'data');
        $this->variant->product->delete();
        $this->getJson('/api/homes?isDeleted=1')->assertOk()->assertJsonMissing(['code' => 'FLAG']);
    }

    public function test_staff_bearer_can_download_private_quote_files_without_customer_token(): void
    {
        $this->postJson('/api/quote-requests', ['customer'=>$this->customer, 'brief'=>'Logo', 'attachments'=>[UploadedFile::fake()->create('logo.pdf',2,'application/pdf')]])->assertCreated();
        $quote=SalesQuote::firstOrFail();$file=$quote->attachments->first();
        $user=User::factory()->create();$user->assignRole('super-admin');
        $token=$user->createToken('download-test')->plainTextToken;
        $this->withToken($token)->getJson('/api/public-quotes/'.$quote->uuid.'/files/'.$file->id)->assertOk();
    }
}
