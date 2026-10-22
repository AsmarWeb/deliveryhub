<?php

use App\Models\CatalogConnection;
use App\Models\SubscriptionPlan;
use App\Services\Catalog\CatalogSyncScheduler;
use App\Services\Catalog\EnterpriseCatalogAccess;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('deliveries:run-recurring')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('deliveries:auto-dispatch')->everyMinute()->withoutOverlapping(5);

Schedule::command('notifications:send-push')->everyMinute()->withoutOverlapping(5);

// Feed synchronization uses the queue; run a worker with timeout >600 and retry_after >650.
Artisan::command('catalog:sync-due', function () {
    $scheduled = 0;
    CatalogConnection::where('mode', 'feed')->where('status', 'active')
        ->where(fn ($q) => $q->whereNull('next_sync_at')->orWhere('next_sync_at', '<=', now()))
        ->chunkById(100, function ($connections) use (&$scheduled) {
            foreach ($connections as $connection) {
                if (! app(EnterpriseCatalogAccess::class)->allows($connection->merchant)) {
                    continue;
                }
                if (app(CatalogSyncScheduler::class)->request($connection)) {
                    $scheduled++;
                }
            }
        });
    $this->info('Catalog syncs queued: '.$scheduled);
});
Schedule::command('catalog:sync-due')->everyMinute()->withoutOverlapping();

Artisan::command('catalog:configure-enterprise {monthly_price}', function () {
    $price = $this->argument('monthly_price');
    if (! preg_match('/^(?:[1-9][0-9]{0,5}|0)\.[0-9]{2}$/', $price) || (float) $price <= 0) {
        $this->error('Enter a positive monthly AED price, such as 999.00.');

        return 1;
    }
    $plan = SubscriptionPlan::firstOrNew(['slug' => 'enterprise']);
    $plan->fill(['name' => 'Enterprise', 'monthly_price' => $price, 'currency' => 'AED', 'is_active' => true,
        'commission_rate' => $plan->exists ? $plan->commission_rate : '5.00', 'max_active_automations' => $plan->exists ? $plan->max_active_automations : 50]);
    $plan->save();
    $this->info('Enterprise monthly price updated. Configure its matching STRIPE_PRICE_ENTERPRISE before checkout.');
});
