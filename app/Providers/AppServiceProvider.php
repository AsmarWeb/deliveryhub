<?php

namespace App\Providers;

use App\Listeners\SyncStripeMerchantSubscription;
use App\Models\Merchant;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;

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
        RateLimiter::for('catalog-key', fn ($request) => Limit::perMinute(60)->by('catalog:'.$request->attributes->get('catalog_connection')?->id)
        );
        RateLimiter::for('delivery-key', fn ($request) => Limit::perMinute(120)->by('delivery:'.$request->attributes->get('delivery_connection')?->id));
        foreach (['billing-read' => 60, 'billing-write' => 5] as $name => $limit) {
            RateLimiter::for($name, fn ($request) => Limit::perMinute($limit)->by((string) $request->user()?->id)
            );
        }
        Event::listen(WebhookHandled::class, SyncStripeMerchantSubscription::class);
        //
    }
}
