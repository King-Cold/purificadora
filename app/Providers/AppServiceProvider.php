<?php

namespace App\Providers;

use App\Models\DeliveryRoute;
use App\Models\Order;
use App\Policies\DeliveryRoutePolicy;
use App\Policies\OrderPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
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
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(DeliveryRoute::class, DeliveryRoutePolicy::class);
        Vite::prefetch(concurrency: 3);
    }
}
