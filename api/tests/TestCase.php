<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        // Pred RefreshDatabase/migrate:fresh, nie až po parent::setUp().
        $connection = $app['db']->connection();
        $database = $connection->getDatabaseName();
        $safe = $connection->getDriverName() === 'sqlite'
            ? $database === ':memory:'
            : ($connection->getDriverName() === 'mysql' && preg_match('/(?:^|_)test(?:_|$)/', $database));
        if (! $app->environment('testing') || ! $safe) {
            throw new \RuntimeException('Testy vyžadujú APP_ENV=testing a samostatnú MySQL databázu s _test v názve alebo SQLite :memory:.');
        }
        return $app;
    }

    /** Platná doprava a nové odoslanie pre bežné scenáre. Negatívne testy používajú postJson priamo. */
    protected function postCheckout(array $payload)
    {
        $payload += [
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'shipping_method_id' => \App\Models\ShippingMethod::firstOrCreate(['name' => 'Test delivery'], ['price' => 0, 'active' => true])->id,
        ];
        return $this->postJson('/api/checkouts', $payload);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Izolované SQLite behy potrebujú MySQL funkciu použitú filtrom stavov.
        if (config('database.default') === 'sqlite') {
            app('db')->connection()->getPdo()->sqliteCreateFunction('greatest',
                fn (...$values) => in_array(null, $values, true) ? null : max($values));
        }
    }
}
