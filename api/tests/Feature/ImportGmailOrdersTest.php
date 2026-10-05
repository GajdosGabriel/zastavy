<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ImportGmailOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['db']->connection()->getDriverName() === 'sqlite') {
            $pdo = $app['db']->connection()->getPdo();
            $pdo->sqliteCreateFunction('REGEXP', fn ($pattern, $value) => preg_match('~'.$pattern.'~', $value ?? ''));
            $pdo->sqliteCreateFunction('CHAR_LENGTH', fn ($s) => mb_strlen($s));
            $pdo->sqliteCreateFunction('CONCAT', fn (...$parts) => implode('', $parts));
            $pdo->sqliteCreateFunction('SUBSTRING', fn ($s, $start, $length) => mb_substr($s, $start - 1, $length));
        }

        return $app;
    }

    private function manifest(array $changes = []): string
    {
        $record = array_replace([
            'source_id' => '1234567890abcdef', 'source_ids' => ['1234567890abcdef'],
            'source_date' => '2014-07-01T10:15:16+02:00', 'source_subject' => 'Priama objednávka',
            'source_body' => 'Objednávame 2 ks vlajky SR.', 'status' => 'archived', 'customer_id' => null,
            'company' => 'Testovacia škola', 'name' => null, 'email' => 'school@example.test',
            'phone' => null, 'street' => null, 'city' => null, 'postcode' => null, 'ico' => null, 'dic' => null,
            'missing' => ['Cena a telefón nie sú známe'],
            'items' => [['name' => 'Vlajka SR', 'quantity' => 2, 'price' => null]],
        ], $changes);
        $path = tempnam(sys_get_temp_dir(), 'gmail-import-');
        file_put_contents($path, json_encode(['account' => 'owner@example.test', 'records' => [$record]], JSON_THROW_ON_ERROR));

        return $path;
    }

    public function test_dry_run_then_backdated_idempotent_import_without_notifications(): void
    {
        Mail::fake();
        Notification::fake();
        $path = $this->manifest();
        try {
            $this->artisan('orders:import-gmail', ['manifest' => $path])->assertSuccessful();
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('customers', 0);
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('customers', 1);
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('order_products', 1);
            $order = DB::table('orders')->first();
            $this->assertSame('2014-07-01 10:15:16', $order->created_at);
            $this->assertSame('archived', $order->status);
            $this->assertNull($order->delivery_token);
            $this->assertNull(DB::table('order_products')->value('price'));
            $this->assertNull(DB::table('order_products')->value('product_id'));
            $this->assertFalse((bool) DB::table('users')->value('active'));
            Mail::assertNothingSent();
            Mail::assertNothingQueued();
            Notification::assertNothingSent();
        } finally {
            unlink($path);
        }
    }

    public function test_customer_identity_failure_rolls_back_the_entire_batch(): void
    {
        $path = $this->manifest();
        $manifest = json_decode(file_get_contents($path), true);
        $bad = $manifest['records'][0];
        $bad['source_id'] = 'abcdef1234567890';
        $bad['source_ids'] = [$bad['source_id']];
        $bad['customer_id'] = 999999;
        $manifest['records'][] = $bad;
        file_put_contents($path, json_encode($manifest));
        try {
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertFailed();
            $this->assertDatabaseCount('orders', 0);
            $this->assertDatabaseCount('customers', 0);
            $this->assertDatabaseCount('users', 0);
        } finally {
            unlink($path);
        }
    }

    public function test_existing_contact_details_are_preserved(): void
    {
        $path = $this->manifest();
        try {
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $customer = DB::table('customers')->first();
            DB::table('customers')->where('id', $customer->id)->update(['phone' => '+421900123456']);
            $manifest = json_decode(file_get_contents($path), true);
            $manifest['records'][0]['source_id'] = 'abcdef1234567890';
            $manifest['records'][0]['source_ids'] = ['abcdef1234567890'];
            $manifest['records'][0]['phone'] = '+421911222333';
            file_put_contents($path, json_encode($manifest));
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $this->assertDatabaseCount('customers', 1);
            $this->assertDatabaseCount('users', 1);
            $this->assertSame('+421900123456', DB::table('customers')->value('phone'));
            $this->assertSame('+421911222333', DB::table('orders')->orderByDesc('id')->value('phone'));
        } finally {
            unlink($path);
        }
    }

    public function test_prices_and_totals_follow_a_checked_historical_reference(): void
    {
        $path = $this->manifest(['items' => [['name' => 'Vlajka SR', 'quantity' => 2, 'price' => 18, 'price_origin' => 'email']]]);
        try {
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $original = DB::table('order_products')->first();
            $this->assertEquals(18, $original->price);
            $this->assertEquals(36, $original->total);
            $manifest = json_decode(file_get_contents($path), true);
            $r = &$manifest['records'][0];
            $r['source_id'] = 'abcdef1234567890';
            $r['source_ids'] = [$r['source_id']];
            $r['items'][0]['price_origin'] = 'historical_order';
            $r['items'][0]['price_reference'] = ['order_product_id' => $original->id, 'order_id' => $original->order_id, 'product_id' => null];
            file_put_contents($path, json_encode($manifest));
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $this->assertEquals(36, DB::table('order_products')->orderByDesc('id')->value('total'));
            $r['source_id'] = 'abcdef1234567891';
            $r['source_ids'] = [$r['source_id']];
            $r['items'][0]['price'] = 99;
            file_put_contents($path, json_encode($manifest));
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertFailed();
            $this->assertDatabaseCount('orders', 2);
        } finally {
            unlink($path);
        }
    }

    public function test_empty_archive_is_supported_and_price_guesses_are_rejected(): void
    {
        $path = $this->manifest(['items' => []]);
        try {
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertSuccessful();
            $this->assertDatabaseCount('orders', 1);
            $this->assertDatabaseCount('order_products', 0);
        } finally {
            unlink($path);
        }
        $path = $this->manifest(['source_id' => 'abcdef1234567890', 'items' => [['name' => 'Vlajka', 'quantity' => 1, 'price' => 18]]]);
        try {
            $this->artisan('orders:import-gmail', ['manifest' => $path, '--apply' => true])->assertFailed();
            $this->assertDatabaseCount('orders', 1);
        } finally {
            unlink($path);
        }
    }
}
