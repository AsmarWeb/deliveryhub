<?php

namespace App\Services\Billing;

use App\Jobs\ProcessStripeInvoice;
use App\Models\StripeWebhookEvent;
use Illuminate\Support\Facades\DB;

class StripeWebhookInbox
{
    // Only call with a signature-verified webhook or an authenticated Stripe API event.
    public function accept(array $payload): StripeWebhookEvent
    {
        abort_unless(($payload['type'] ?? null) === 'invoice.paid'
            && is_string($payload['id'] ?? null)
            && preg_match('/^evt_[A-Za-z0-9_]{1,180}$/', $payload['id'])
            && is_array($payload['data']['object'] ?? null), 400, 'Invalid paid invoice event.');
        $invoice = $payload['data']['object'];
        $subscriptionId = $invoice['subscription'] ?? $invoice['parent']['subscription_details']['subscription'] ?? null;
        $event = StripeWebhookEvent::firstOrCreate(['id' => $payload['id']], [
            'type' => 'invoice.paid', 'payload' => $payload,
            'stripe_subscription_id' => is_string($subscriptionId) ? $subscriptionId : null,
            'status' => 'pending', 'next_attempt_at' => now(),
        ]);
        // Duplicate deliveries never replace the authenticated original payload.
        $this->enqueue($event->id);

        return $event->fresh();
    }

    public function enqueue(string $id): bool
    {
        $claimed = DB::transaction(function () use ($id) {
            $event = StripeWebhookEvent::whereKey($id)->lockForUpdate()->first();
            if (! $event || ! in_array($event->status, ['pending', 'retry'], true)
                || ($event->next_attempt_at && $event->next_attempt_at->isFuture())
                || ($event->queued_at && $event->queued_at->greaterThan(now()->subSeconds(config('billing_webhooks.queue_lease_seconds'))))) {
                return false;
            }
            $event->update(['queued_at' => now()]);

            return true;
        });
        if (! $claimed) {
            return false;
        }
        try {
            ProcessStripeInvoice::dispatch($id)->afterCommit();
        } catch (\Throwable $exception) {
            // The durable inbox remains recoverable by the scheduler if the broker is unavailable.
            StripeWebhookEvent::whereKey($id)->whereIn('status', ['pending', 'retry'])->update([
                'queued_at' => null, 'error_code' => 'QUEUE_UNAVAILABLE', 'next_attempt_at' => now()->addMinute(),
            ]);
        }

        return true;
    }

    public function wake(string $subscriptionId): void
    {
        StripeWebhookEvent::where('stripe_subscription_id', $subscriptionId)->whereIn('status', ['pending', 'retry'])
            ->update(['next_attempt_at' => now()]);
        StripeWebhookEvent::where('stripe_subscription_id', $subscriptionId)->whereIn('status', ['pending', 'retry'])
            ->pluck('id')->each(fn ($id) => $this->enqueue($id));
    }

    public function retryDue(): int
    {
        StripeWebhookEvent::where('status', 'processing')
            ->where('processing_started_at', '<', now()->subSeconds(config('billing_webhooks.processing_timeout_seconds')))
            ->update(['status' => 'retry', 'queued_at' => null, 'next_attempt_at' => now(), 'error_code' => 'WORKER_INTERRUPTED']);
        $count = 0;
        StripeWebhookEvent::whereIn('status', ['pending', 'retry'])->where('next_attempt_at', '<=', now())
            ->orderBy('id')->chunkById(100, function ($events) use (&$count) {
                foreach ($events as $event) {
                    if ($this->enqueue($event->id)) {
                        $count++;
                    }
                }
            });

        return $count;
    }
}
