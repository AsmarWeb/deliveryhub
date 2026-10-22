<?php

namespace App\Jobs;

use App\Listeners\SyncStripeMerchantSubscription;
use App\Models\StripeWebhookEvent;
use App\Services\Billing\StripeSyncPending;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Events\WebhookHandled;
use Throwable;

class ProcessStripeInvoice implements ShouldQueue
{
    use Queueable;

    public int $tries = 1; // Retry policy is durable in the inbox, not tied to worker --tries.

    public int $timeout = 60;

    public function __construct(public string $eventId) {}

    public function handle(SyncStripeMerchantSubscription $listener): void
    {
        $lock = Cache::lock('billing-invoice:'.$this->eventId, 90);
        if (! $lock->get()) {
            return;
        }
        try {
            $event = DB::transaction(function () {
                $event = StripeWebhookEvent::whereKey($this->eventId)->lockForUpdate()->first();
                if (! $event || ! in_array($event->status, ['pending', 'retry'], true)
                    || ($event->next_attempt_at && $event->next_attempt_at->isFuture())) {
                    return null;
                }
                if ($event->attempts >= config('billing_webhooks.max_attempts')) {
                    $event->update(['status' => 'failed', 'queued_at' => null, 'next_attempt_at' => null]);

                    return null;
                }
                $event->update(['status' => 'processing', 'attempts' => $event->attempts + 1,
                    'processing_started_at' => now(), 'queued_at' => null]);

                return $event;
            });
            if (! $event) {
                return;
            }
            try {
                $listener->handle(new WebhookHandled($event->payload));
                $event->update(['status' => 'processed', 'processed_at' => now(), 'error_code' => null,
                    'processing_started_at' => null, 'next_attempt_at' => null]);
            } catch (Throwable $exception) {
                $this->defer($event, $exception instanceof StripeSyncPending ? $exception->reason : 'SYNC_ERROR');
            }
        } finally {
            $lock->release();
        }
    }

    private function defer(StripeWebhookEvent $event, string $reason): void
    {
        $failed = $event->attempts >= config('billing_webhooks.max_attempts');
        $delays = config('billing_webhooks.backoff_seconds');
        $delay = $delays[min(max($event->attempts - 1, 0), count($delays) - 1)];
        $event->update(['status' => $failed ? 'failed' : 'retry', 'error_code' => $reason,
            'next_attempt_at' => $failed ? null : now()->addSeconds($delay), 'queued_at' => null, 'processing_started_at' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $event = StripeWebhookEvent::find($this->eventId);
        if ($event && $event->status === 'processing') {
            $this->defer($event, 'WORKER_FAILED');
        }
    }
}
