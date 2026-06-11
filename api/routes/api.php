<?php

use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\Dashboard\OrderController;
use App\Http\Controllers\Api\Dashboard\OrderMarkController;
use App\Http\Controllers\Api\Dashboard\OrderProductController;
use App\Http\Controllers\Api\Dashboard\OrderShippingController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\OrderDeliverySurveyController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\SanctumController;
use App\Http\Controllers\Api\ShippingMethodController;
use App\Http\Controllers\Api\SuperAdmin\AnnouncementController as SuperAdminAnnouncementController;
use App\Http\Controllers\Api\SuperAdmin\CategoryController;
use App\Http\Controllers\Api\SuperAdmin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\SuperAdmin\CustomerController;
use App\Http\Controllers\Api\SuperAdmin\CustomerMarkController;
use App\Http\Controllers\Api\SuperAdmin\CustomerOrderController;
use App\Http\Controllers\Api\SuperAdmin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Api\SuperAdmin\ProductController;
use App\Http\Controllers\Api\SuperAdmin\ProductImageController;
use App\Http\Controllers\Api\SuperAdmin\ShippingMethodController as AdminShippingMethodController;
use App\Http\Controllers\Api\SuperAdmin\ShippingNoticeController;
use App\Http\Controllers\Api\SuperAdmin\StockController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\DashboardMiddleware;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/verify-email/{uuid}', [UserController::class, 'verifyEmail'])->name('users.verifyEmail');
Route::post('/login', [SanctumController::class, 'login'])->name('sanctum.login');
Route::post('/register', [RegisteredUserController::class, 'store'])->name('auth.register');
Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('password.forgot');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.reset');

Route::get('/user', function (Request $request) {
    return new UserResource($request->user('sanctum'));
});

Route::apiResources([
    'checkouts' => CheckoutController::class,
    'homes' => HomeController::class,
    'orderDeliverySurvey' => OrderDeliverySurveyController::class,
]);

Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/announcements/active', [AnnouncementController::class, 'active'])->name('announcements.active');
Route::get('/shipping-methods', [ShippingMethodController::class, 'index'])->name('shipping-methods.index');
Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
Route::post('/coupons/validate', [CouponController::class, 'validate'])->name('coupons.validate');

Route::middleware(['auth:sanctum', DashboardMiddleware::class])->group(function () {
    Route::get('/orders/statistics', [OrderController::class, 'statistics'])->name('orders.statistics');

    Route::apiResources([
        'orders' => OrderController::class,
        'orders.shippings' => OrderShippingController::class,
        'orders.marks' => OrderMarkController::class,
        'orders.orderProducts' => OrderProductController::class,
    ]);

    Route::post('/logout', [SanctumController::class, 'logout'])->name('sanctum.logout');
});

Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
    Route::apiResources([
        'categories' => CategoryController::class,
        'customers' => CustomerController::class,
        'customers.marks' => CustomerMarkController::class,
        'customers.order' => CustomerOrderController::class,
        'stocks' => StockController::class,
        'announcements' => SuperAdminAnnouncementController::class,
        'product.image' => ProductImageController::class,
        'shippings.notices' => ShippingNoticeController::class,
    ]);

    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'update', 'store']);
    Route::apiResource('products', ProductController::class)->except(['show']);
    Route::prefix('admin')->group(function () {
        Route::apiResource('shipping-methods', AdminShippingMethodController::class)->except(['show', 'create', 'edit']);
        Route::post('shipping-methods/{id}/restore', [AdminShippingMethodController::class, 'restore'])->name('admin.shipping-methods.restore');
        Route::apiResource('payment-methods', AdminPaymentMethodController::class)->except(['show', 'create', 'edit']);
        Route::post('payment-methods/{id}/restore', [AdminPaymentMethodController::class, 'restore'])->name('admin.payment-methods.restore');
        Route::apiResource('coupons', AdminCouponController::class)->except(['show', 'create', 'edit']);
        Route::post('coupons/{id}/restore', [AdminCouponController::class, 'restore'])->name('admin.coupons.restore');
    });
});

Route::get('artisan/run', function () {
    \Artisan::call('cache:clear');
    \Artisan::call('view:clear');
    \Artisan::call('config:clear');
    \Artisan::call('optimize:clear');

    dd("All is cleared");
});
