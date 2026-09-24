<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || ! preg_match('/(?:^|_)test(?:_|$)/', config('database.connections.mysql.database'))) {
    throw new RuntimeException('Isolated test DB required');
}
Notification::fake();
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
if (isset($job['user'])) {
    Sanctum::actingAs(User::findOrFail($job['user']));
}
file_put_contents($job['ready'], 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($job['release'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timeout');
    }usleep(10000);
    clearstatcache();
}
$request = Request::create($job['url'], 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_SALES_TOKEN' => $job['token'] ?? ''], json_encode($job['payload']));
$response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
file_put_contents($job['result'], json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)]));
