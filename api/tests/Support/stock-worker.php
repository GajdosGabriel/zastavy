<?php

use App\Models\Order;
use App\Models\Stock;
use App\Models\User;
use App\Services\ShippingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Samostatný proces pre skutočné transakcie a zámky MySQL.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || ! str_contains(config('database.connections.mysql.database'), '_test')) {
    throw new RuntimeException('Worker requires a dedicated test database.');
}
Notification::fake();
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
file_put_contents($job['ready'], 'ready');
$deadline = microtime(true) + 15;
while (! file_exists($job['release'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timed out.');
    }
    usleep(10000);
    clearstatcache();
}
ob_start();
try {
    if ($job['mode'] === 'receipt') {
        for ($i = 0; $i < 25; $i++) {
            DB::transaction(fn () => Stock::create([
                'product_variant_id' => $job['variant'], 'quantity' => 1,
            ]));
        }
        echo json_encode(['status' => 200]);
    } elseif ($job['mode'] === 'ship') {
        $shipping = (new ShippingService)->create(Order::findOrFail($job['order']), $job['items'] ?? null, $job['key'] ?? null);
        echo json_encode(['status' => 200, 'id' => $shipping?->id]);
    } else {
        Sanctum::actingAs(User::findOrFail($job['user']));
        $request = Request::create($job['url'], $job['method'] ?? 'POST', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($job['payload'] ?? []));
        $response = $app->make(Illuminate\Contracts\Http\Kernel::class)->handle($request);
        echo json_encode(['status' => $response->getStatusCode()]);
    }
} catch (ValidationException $e) {
    echo json_encode(['status' => 422]);
} catch (HttpException $e) {
    echo json_encode(['status' => $e->getStatusCode()]);
} catch (Throwable $e) {
    echo json_encode(['status' => 500, 'error' => $e->getMessage()]);
}

file_put_contents($job['result'], ob_get_clean());
