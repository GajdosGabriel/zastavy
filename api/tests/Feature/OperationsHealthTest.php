<?php

namespace Tests\Feature;

use App\Jobs\QueueHeartbeat;
use App\Services\OperationsHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['queue.default' => 'database', 'operations.heartbeat_max_age' => 900]);
        Log::spy();
    }

    private function heartbeat(): void
    {
        Cache::put('ops:scheduler-heartbeat', now()->timestamp);
        (new QueueHeartbeat)->handle();
    }

    public function test_healthy_dependencies_and_heartbeats_succeed(): void
    {
        $this->heartbeat();
        $this->artisan('ops:check', ['--json' => true])->assertExitCode(0);
        $this->assertNotContains('failed', app(OperationsHealth::class)->checks());
    }

    public function test_missing_or_stale_worker_is_reported_without_customer_payload(): void
    {
        Cache::put('ops:scheduler-heartbeat', now()->timestamp);
        Cache::put('ops:worker-heartbeat', now()->subMinutes(16)->timestamp);
        $this->artisan('ops:check', ['--json' => true])->assertExitCode(1);
        $this->assertSame('failed', app(OperationsHealth::class)->checks()['queue_worker']);
        Log::shouldHaveReceived('error')->with('operations.unhealthy', \Mockery::on(fn ($data) => $data['checks']['queue_worker'] === 'failed'));
    }

    public function test_missing_scheduler_is_not_masked_by_running_worker(): void
    {
        (new QueueHeartbeat)->handle();
        $this->assertSame('failed', app(OperationsHealth::class)->checks()['scheduler']);
    }

    public function test_queue_backlog_and_failed_jobs_are_detected(): void
    {
        $this->heartbeat();
        config(['operations.queue_max_size' => 0]);
        QueueHeartbeat::dispatch();
        $this->assertSame('failed', app(OperationsHealth::class)->checks()['queue_backlog']);
        app('queue.failer')->log('database', 'default', json_encode(['uuid' => (string) Str::uuid()]), 'Test failure');
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame('failed', app(OperationsHealth::class)->checks()['failed_jobs']);
    }

    public function test_synchronous_queue_is_not_a_healthy_background_worker(): void
    {
        $this->heartbeat();
        config(['queue.default' => 'sync']);
        $this->assertSame('failed', app(OperationsHealth::class)->checks()['queue_worker']);
    }

    public function test_heartbeat_job_is_consumed_by_database_worker(): void
    {
        $this->assertNull(Cache::get('ops:worker-heartbeat'));
        QueueHeartbeat::dispatch();
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1, '--sleep' => 0])->assertExitCode(0);
        $this->assertSame(now()->timestamp, Cache::get('ops:worker-heartbeat'));
        $this->assertDatabaseCount('jobs', 0);
    }
}
