<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\CustomerMarkController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderDeliverySurveyController;
use App\Http\Controllers\Api\OrderMarkController;
use App\Http\Controllers\Api\OrderProductController;
use App\Http\Controllers\Api\OrderShippingController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\SanctumController;
use App\Http\Controllers\Api\ShippingNoticeController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [SanctumController::class, 'login'])->name('sanctum.login');
Route::post('/register', [RegisteredUserController::class, 'store'])->name('auth.register');

Route::get('/user', function (Request $request) {
    return new UserResource($request->user('sanctum'));
});

Route::apiResources([
    'checkouts' => CheckoutController::class,
    'homes' => HomeController::class,
    'orderDeliverySurvey' => OrderDeliverySurveyController::class,
]);

Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
    Route::apiResources([
        'categories' => CategoryController::class,
        'orders' => OrderController::class,
        'orders.shippings' => OrderShippingController::class,
        'orders.marks' => OrderMarkController::class,
        'customers' => CustomerController::class,
        'customers.marks' => CustomerMarkController::class,
        'stocks' => StockController::class,
        'customers.order' => CustomerOrderController::class,
        'product.image' => ProductImageController::class,
        'orders.orderProducts' => OrderProductController::class,
        'shippings.notices' => ShippingNoticeController::class,
    ]);

    Route::apiResource('products', ProductController::class)->except(['show']);

    Route::post('/logout', [SanctumController::class, 'logout'])->name('sanctum.logout');
});

Route::get('artisan/run', function () {
    \Artisan::call('cache:clear');
    \Artisan::call('view:clear');
    \Artisan::call('config:clear');
    \Artisan::call('optimize:clear');

    dd("All is cleared");
});
