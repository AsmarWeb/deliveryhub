<?php

use App\Models\CatalogConnection;
use App\Models\IntegrationDeliveryWebhook;
use App\Models\StripeWebhookEvent;
use App\Models\SubscriptionPlan;
use App\Services\Billing\StripeWebhookInbox;
use App\Services\Catalog\CatalogSyncScheduler;
use App\Services\Catalog\EnterpriseCatalogAccess;
use App\Services\Integrations\DeliveryWebhookOutbox;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Cashier\Cashier;

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

Artisan::command('billing:retry-webhooks', function () {
    $this->info('Invoice jobs queued: '.app(StripeWebhookInbox::class)->retryDue());
});
Schedule::command('billing:retry-webhooks')->everyMinute()->withoutOverlapping();

Artisan::command('billing:webhooks', function () {
    $this->table(['Event', 'Status', 'Attempts', 'Next attempt', 'Error'],
        StripeWebhookEvent::latest()->limit(30)->get()->map(fn ($event) => [
            $event->id, $event->status, $event->attempts, $event->next_attempt_at?->toIso8601String(), $event->error_code,
        ])->all());
});

Artisan::command('billing:retry-webhook {event}', function () {
    $event = StripeWebhookEvent::findOrFail($this->argument('event'));
    if ($event->status !== 'failed') {
        $this->error('Only failed events need an explicit retry.');

        return 1;
    }
    $event->update(['status' => 'retry', 'attempts' => 0, 'next_attempt_at' => now(), 'queued_at' => null]);
    app(StripeWebhookInbox::class)->enqueue($event->id);
    $this->info('Stored verified event queued.');
});

Artisan::command('billing:replay-stripe-event {event}', function () {
    // Recover an earlier failed webhook that predates the durable inbox, using Stripe's authenticated API.
    $id = $this->argument('event');
    if (! preg_match('/^evt_[A-Za-z0-9_]{1,180}$/', $id)) {
        $this->error('Invalid Stripe event ID.');

        return 1;
    }
    $payload = Cashier::stripe()->events->retrieve($id, [])->toArray();
    $event = app(StripeWebhookInbox::class)->accept($payload);
    $this->info('Event recorded: '.$event->id.'; status: '.$event->status);
});

Artisan::command('integrations:send-webhooks', function () {
    $this->info('Delivery webhook jobs queued: '.app(DeliveryWebhookOutbox::class)->retryDue());
});
Schedule::command('integrations:send-webhooks')->everyMinute()->withoutOverlapping();
Artisan::command('integrations:webhooks', function () {
    $this->table(['Event', 'Status', 'Attempts', 'Next', 'Error'], IntegrationDeliveryWebhook::latest()->limit(30)->get()->map(fn ($event) => [$event->id, $event->status, $event->attempts, $event->next_attempt_at?->toIso8601String(), $event->error_code])->all());
});
Artisan::command('integrations:retry-webhook {event}', function () {
    $event = IntegrationDeliveryWebhook::findOrFail($this->argument('event'));
    if ($event->status !== 'failed') {
        $this->error('Only failed events can be retried.');

        return 1;
    }
    $event->update(['status' => 'retry', 'attempts' => 0, 'queued_at' => null, 'next_attempt_at' => now()]);
    app(DeliveryWebhookOutbox::class)->enqueue($event->id);
    $this->info('Delivery webhook queued.');
});
