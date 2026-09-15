<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
