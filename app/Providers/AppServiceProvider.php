<?php

namespace App\Providers;

use Laravel\Cashier\Cashier;
use App\Models\Merchant;

use Laravel\Cashier\Events\WebhookHandled;
use App\Listeners\SyncStripeMerchantSubscription;
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
        Cashier::useCustomerModel(Merchant::class);
        foreach (['billing-read' => 60, 'billing-write' => 5] as $name => $limit) {
            \Illuminate\Support\Facades\RateLimiter::for($name, fn ($request) =>
                \Illuminate\Cache\RateLimiting\Limit::perMinute($limit)->by((string) $request->user()?->id)
            );
        }
        Event::listen(WebhookHandled::class, SyncStripeMerchantSubscription::class);
        //
    }
}
