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
        Event::listen(WebhookHandled::class, SyncStripeMerchantSubscription::class);
        //
    }
}
