<?php

use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\CustomerCheckController;
use App\Http\Controllers\Api\PublicOrderController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\Dashboard\OrderAttachmentController;
use App\Http\Controllers\Api\Dashboard\OrderController;
use App\Http\Controllers\Api\Dashboard\OrderMarkController;
use App\Http\Controllers\Api\Dashboard\OrderProductController;
use App\Http\Controllers\Api\Dashboard\OrderShippingController;
use App\Http\Controllers\Api\Dashboard\OrderReturnController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AttributeFacetController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\SanctumController;
use App\Http\Controllers\Api\ShippingMethodController;
use App\Http\Controllers\Api\SuperAdmin\AnnouncementController as SuperAdminAnnouncementController;
use App\Http\Controllers\Api\SuperAdmin\AttributeController;
use App\Http\Controllers\Api\SuperAdmin\AttributeValueController;
use App\Http\Controllers\Api\SuperAdmin\CategoryController;
use App\Http\Controllers\Api\SuperAdmin\CouponController as AdminCouponController;
use App\Http\Controllers\Api\SuperAdmin\CustomerController;
use App\Http\Controllers\Api\SuperAdmin\CustomerExportController;
use App\Http\Controllers\Api\SuperAdmin\CustomerMarkController;
use App\Http\Controllers\Api\SuperAdmin\CustomerOrderController;
use App\Http\Controllers\Api\SuperAdmin\CustomerDuplicateController;
use App\Http\Controllers\Api\SuperAdmin\CustomerReviewController;
use App\Http\Controllers\Api\SuperAdmin\PaymentMethodController as AdminPaymentMethodController;
use App\Http\Controllers\Api\SuperAdmin\ProductController;
use App\Http\Controllers\Api\SuperAdmin\ProductImageController;
use App\Http\Controllers\Api\SuperAdmin\ProductVariantController;
use App\Http\Controllers\Api\SuperAdmin\ShippingMethodController as AdminShippingMethodController;
use App\Http\Controllers\Api\SuperAdmin\ShippingNoticeController;
use App\Http\Controllers\Api\SuperAdmin\CouponSettingsController;
use App\Http\Controllers\Api\SuperAdmin\StockController;
use App\Http\Controllers\Api\SuperAdmin\UserController;
use App\Http\Controllers\Api\SuperAdmin\UserExportController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\DashboardMiddleware;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/verify-email/{uuid}', [UserController::class, 'verifyEmail'])->name('users.verifyEmail');

// Autentifikačné endpointy — throttle proti brute-force útokom.
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/login', [SanctumController::class, 'login'])->name('sanctum.login');
    Route::post('/register', [RegisteredUserController::class, 'store'])->name('auth.register');
    Route::post('/forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('password.forgot');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.reset');
});

Route::get('/user', function (Request $request) {
    return new UserResource($request->user('sanctum'));
});

Route::apiResource('homes', HomeController::class);

// Verejný checkout a IČO lookup — throttle proti spamu objednávok a scrapingu kontaktov.
Route::apiResource('checkouts', CheckoutController::class)->middleware('throttle:30,1');

// Kontrola vyplnených údajov ešte pred odoslaním formulára. Tie isté pravidlá,
// aké po uložení použije post-kontrola — len tu stihnú chybu zastaviť.
// Nič nezapisuje a nič nečíta z databázy, iba posudzuje poslané hodnoty.
Route::post('customer-check', CustomerCheckController::class)
    ->middleware('throttle:60,1')
    ->name('customer-check');

Route::get('/public-orders/{uuid}', [PublicOrderController::class, 'show'])->name('public-orders.show');
// Prílohy verejného detailu — prístup chráni len ťažko uhádnuteľné uuid objednávky,
// rovnako ako samotný detail. Throttle proti hádaniu uuid.
Route::get('/public-orders/{uuid}/attachments/{attachment}', [PublicOrderController::class, 'downloadAttachment'])
    ->middleware('throttle:60,1')
    ->whereNumber('attachment')
    ->name('public-orders.attachments.show');

Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
Route::get('/attribute-facets', [AttributeFacetController::class, 'index'])->name('attribute-facets.index');
Route::get('/announcements/active', [AnnouncementController::class, 'active'])->name('announcements.active');
Route::get('/shipping-methods', [ShippingMethodController::class, 'index'])->name('shipping-methods.index');
Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
Route::post('/coupons/validate', [CouponController::class, 'validate'])
    ->middleware('throttle:20,1')
    ->name('coupons.validate');

Route::middleware(['auth:sanctum', DashboardMiddleware::class])->group(function () {
    Route::get('/orders/statistics', [OrderController::class, 'statistics'])->name('orders.statistics');

    Route::apiResources([
        'orders' => OrderController::class,
        'orders.shippings' => OrderShippingController::class,
        'orders.marks' => OrderMarkController::class,
        'orders.orderProducts' => OrderProductController::class,
    ]);

    Route::apiResource('orders.attachments', OrderAttachmentController::class)
        ->only(['index', 'store', 'show', 'destroy']);

    Route::apiResource('orders.returns', OrderReturnController::class)
        ->parameters(['returns' => 'orderReturn']);
    Route::post('orders/{order}/returns/{orderReturn}/process', [OrderReturnController::class, 'process'])->name('orders.returns.process');
    Route::post('orders/{order}/returns/{orderReturn}/cancel', [OrderReturnController::class, 'cancel'])->name('orders.returns.cancel');

    Route::post('/logout', [SanctumController::class, 'logout'])->name('sanctum.logout');
});

Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
    Route::post('/product/{product}/image/reorder', [ProductImageController::class, 'reorder'])->name('product.image.reorder');

    // Musí byť pred apiResource('stocks'), inak by "summary" pohltilo {stock}.
    Route::get('stocks/summary', [StockController::class, 'summary'])->name('stocks.summary');
    Route::get('stocks/variants', [StockController::class, 'variants'])->name('stocks.variants');
    Route::get('stocks/summary/{variantId}', [StockController::class, 'variantSummary'])
        ->whereNumber('variantId')
        ->name('stocks.summary.variant');

    // Musí byť pred apiResource('customers'), inak by "export" pohltilo {customer}.
    Route::get('customers/export/attributes', [CustomerExportController::class, 'attributes'])->name('customers.export.attributes');
    Route::get('customers/export', [CustomerExportController::class, 'export'])->name('customers.export');

    // Duplicity — musí byť pred apiResource('customers'), inak by "duplicates"
    // pohltilo {customer}.
    Route::get('customers/duplicates', [CustomerDuplicateController::class, 'index'])->name('customers.duplicates.index');
    Route::post('customers/{customer}/merge', [CustomerDuplicateController::class, 'store'])->name('customers.duplicates.store');

    // Post-kontrola údajov zákazníka (viď App\Services\Customers\CustomerReviewService).
    Route::get('customers/{customer}/review', [CustomerReviewController::class, 'show'])->name('customers.review.show');
    Route::post('customers/{customer}/review', [CustomerReviewController::class, 'store'])->name('customers.review.store');
    Route::put('customers/{customer}/review', [CustomerReviewController::class, 'update'])->name('customers.review.update');
    Route::post('customers/{customer}/review/revert', [CustomerReviewController::class, 'revert'])->name('customers.review.revert');
    Route::delete('customers/{customer}/review', [CustomerReviewController::class, 'destroy'])->name('customers.review.destroy');

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
    Route::get('users/export/attributes', [UserExportController::class, 'attributes'])->name('users.export.attributes');
    Route::get('users/export', [UserExportController::class, 'export'])->name('users.export');
    Route::apiResource('users', UserController::class)->only(['index', 'show', 'update', 'store']);
    Route::apiResource('products', ProductController::class)->except(['show']);

    // Taxonómia vlastností a varianty produktu.
    Route::apiResource('attributes', AttributeController::class);
    Route::apiResource('attributes.values', AttributeValueController::class)
        ->parameters(['values' => 'value'])
        ->except(['show']);
    Route::apiResource('products.variants', ProductVariantController::class)
        ->parameters(['variants' => 'variant']);

    Route::prefix('admin')->group(function () {
        Route::apiResource('shipping-methods', AdminShippingMethodController::class)->except(['show', 'create', 'edit'])->names('admin.shipping-methods');
        Route::post('shipping-methods/{id}/restore', [AdminShippingMethodController::class, 'restore'])->name('admin.shipping-methods.restore');
        Route::apiResource('payment-methods', AdminPaymentMethodController::class)->except(['show', 'create', 'edit'])->names('admin.payment-methods');
        Route::post('payment-methods/{id}/restore', [AdminPaymentMethodController::class, 'restore'])->name('admin.payment-methods.restore');
        Route::apiResource('coupons', AdminCouponController::class)->except(['show', 'create', 'edit'])->names('admin.coupons');
        Route::post('coupons/{id}/restore', [AdminCouponController::class, 'restore'])->name('admin.coupons.restore');
        Route::get('coupon-settings', [CouponSettingsController::class, 'show'])->name('admin.coupon-settings.show');
        Route::put('coupon-settings', [CouponSettingsController::class, 'update'])->name('admin.coupon-settings.update');
    });
});
