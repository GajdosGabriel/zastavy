<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Stock;
use App\Observers\CustomerObserver;
use App\Observers\OrderObserver;
use App\Observers\StockObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Customer::observe(CustomerObserver::class);
        Order::observe(OrderObserver::class);
        Stock::observe(StockObserver::class);
    }
}
