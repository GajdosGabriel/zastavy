<?php

namespace App\Providers;

use App\Listeners\SystemLogSubscriber;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Stock;
use App\Models\User;
use App\Observers\CustomerObserver;
use App\Observers\OrderObserver;
use App\Observers\StockObserver;
use App\Observers\UserObserver;
use Illuminate\Support\Facades\Event;
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
        User::observe(UserObserver::class);

        foreach ([Order::class, \App\Models\OrderProduct::class, \App\Models\Shipping::class,
            \App\Models\OrderReturn::class, Stock::class, User::class, \App\Models\SalesQuote::class] as $model) {
            $model::observe(\App\Observers\BusinessActivityObserver::class);
        }

        Event::subscribe(SystemLogSubscriber::class);
    }
}
