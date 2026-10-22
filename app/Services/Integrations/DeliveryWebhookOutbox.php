<?php

namespace App\Services\Integrations;

use App\Jobs\SendIntegrationDeliveryWebhook;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\IntegrationDeliveryOrder;
use App\Models\IntegrationDeliveryWebhook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DeliveryWebhookOutbox
{
    public function record(Delivery $delivery, DeliveryStatusHistory $history): void
    {
        $order = IntegrationDeliveryOrder::with('connection')->where('delivery_id', $delivery->id)->first();
        if (! $order || ! $order->connection->delivery_webhook_url || ! $order->connection->delivery_webhook_secret) {
            return;
        }
        $id = (string) Str::uuid();
        $event = IntegrationDeliveryWebhook::firstOrCreate(['delivery_status_history_id' => $history->id], [
            'id' => $id, 'integration_delivery_order_id' => $order->id, 'status' => 'pending', 'next_attempt_at' => now(),
            'payload' => ['id' => $id, 'type' => 'delivery.status_changed', 'created_at' => now()->toIso8601String(),
                'data' => ['id' => $order->id, 'external_order_id' => $order->external_order_id, 'delivery_id' => $delivery->id,
                    'tracking_number' => $delivery->tracking_number, 'status' => $history->to_status->value,
                    'sequence' => (int) $history->id, 'tracking_url' => app(StoreDeliveryService::class)->trackingUrl($order)]],
        ]);
        DB::afterCommit(fn () => $this->enqueue($event->id));
    }

    public function enqueue(string $id): bool
    {
        $claimed = DB::transaction(function () use ($id) {
            $event = IntegrationDeliveryWebhook::whereKey($id)->lockForUpdate()->first();
            if (! $event || ! in_array($event->status, ['pending', 'retry'], true)
                || ($event->next_attempt_at && $event->next_attempt_at->isFuture())
                || ($event->queued_at && $event->queued_at->gt(now()->subMinutes(2)))) {
                return false;
            }
            $event->update(['queued_at' => now()]);

            return true;
        });
        if (! $claimed) {
            return false;
        }
        try {
            SendIntegrationDeliveryWebhook::dispatch($id)->afterCommit();
        } catch (\Throwable $e) {
            IntegrationDeliveryWebhook::whereKey($id)->whereIn('status', ['pending', 'retry'])->update(['queued_at' => null, 'next_attempt_at' => now()->addMinute(), 'error_code' => 'QUEUE_UNAVAILABLE']);
        }

        return true;
    }

    public function retryDue(): int
    {
        IntegrationDeliveryWebhook::where('status', 'processing')->where('processing_at', '<', now()->subMinutes(2))
            ->update(['status' => 'retry', 'queued_at' => null, 'next_attempt_at' => now(), 'error_code' => 'WORKER_INTERRUPTED']);
        $count = 0;
        IntegrationDeliveryWebhook::whereIn('status', ['pending', 'retry'])->where('next_attempt_at', '<=', now())
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
