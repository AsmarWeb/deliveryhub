<?php

namespace App\Jobs;

use App\Models\IntegrationDeliveryWebhook;
use App\Services\Integrations\DeliveryWebhookTransport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SendIntegrationDeliveryWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public string $eventId) {}

    public function handle(DeliveryWebhookTransport $transport): void
    {
        $lock = Cache::lock('integration-webhook:'.$this->eventId, 90);
        if (! $lock->get()) {
            return;
        }
        try {
            $event = DB::transaction(function () {
                $event = IntegrationDeliveryWebhook::whereKey($this->eventId)->lockForUpdate()->first();
                if (! $event || ! in_array($event->status, ['pending', 'retry'], true) || $event->next_attempt_at?->isFuture()) {
                    return null;
                }
                if ($event->attempts >= config('delivery_integrations.max_attempts')) {
                    $event->update(['status' => 'failed', 'queued_at' => null, 'next_attempt_at' => null]);

                    return null;
                }
                $event->update(['status' => 'processing', 'attempts' => $event->attempts + 1, 'processing_at' => now(), 'queued_at' => null]);

                return $event;
            });
            if (! $event) {
                return;
            }
            try {
                $connection = $event->order->connection;
                if (! $connection->delivery_enabled || $connection->status !== 'active' || ! $connection->delivery_webhook_url || ! $connection->delivery_webhook_secret) {
                    $event->update(['status' => 'skipped', 'processing_at' => null, 'next_attempt_at' => null, 'error_code' => 'CONNECTION_DISABLED']);

                    return;
                }
                $body = json_encode($event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $timestamp = (string) now()->timestamp;
                $headers = ['X-Raslo-Event-Id' => $event->id, 'X-Raslo-Timestamp' => $timestamp,
                    'X-Raslo-Signature' => 'v1='.hash_hmac('sha256', $timestamp.'.'.$body, $connection->delivery_webhook_secret)];
                $code = $transport->send($connection->delivery_webhook_url, $body, $headers);
                if ($code < 200 || $code >= 300) {
                    $this->defer($event, 'HTTP_'.$code);

                    return;
                }
                $event->update(['status' => 'delivered', 'delivered_at' => now(), 'processing_at' => null, 'next_attempt_at' => null, 'error_code' => null]);
            } catch (\Throwable $error) {
                $this->defer($event, 'CALLBACK_FAILED');
            }
        } finally {
            $lock->release();
        }
    }

    private function defer(IntegrationDeliveryWebhook $event, string $code): void
    {
        $failed = $event->attempts >= config('delivery_integrations.max_attempts');
        $delays = config('delivery_integrations.backoff_seconds');
        $event->update(['status' => $failed ? 'failed' : 'retry', 'error_code' => $code, 'processing_at' => null, 'queued_at' => null,
            'next_attempt_at' => $failed ? null : now()->addSeconds($delays[min(max(0, $event->attempts - 1), count($delays) - 1)])]);
    }

    public function failed(?\Throwable $exception): void
    {
        $event = IntegrationDeliveryWebhook::find($this->eventId);
        if ($event && $event->status === 'processing') {
            $this->defer($event, 'WORKER_FAILED');
        }
    }
}
