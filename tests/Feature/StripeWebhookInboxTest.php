<?php

namespace Tests\Feature;

use App\Jobs\ProcessStripeInvoice;
use App\Listeners\SyncStripeMerchantSubscription;
use App\Services\Billing\StripeWebhookInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StripeWebhookInboxTest extends TestCase
{
    use RefreshDatabase;

    private function payload(string $id = 'evt_pending'): array
    {
        return ['id' => $id, 'type' => 'invoice.paid', 'data' => ['object' => [
            'id' => 'in_test', 'status' => 'paid', 'subscription' => 'sub_pending', 'customer' => 'cus_private',
        ]]];
    }

    public function test_duplicate_verified_events_are_encrypted_deduplicated_and_queued_once(): void
    {
        Queue::fake();
        $inbox = app(StripeWebhookInbox::class);
        $event = $inbox->accept($this->payload());
        $modified = $this->payload();
        $modified['data']['object']['customer'] = 'another_customer';
        $inbox->accept($modified);
        Queue::assertPushed(ProcessStripeInvoice::class, 1);
        $this->assertDatabaseCount('stripe_webhook_inbox', 1);
        $this->assertSame('cus_private', $event->fresh()->payload['data']['object']['customer']);
        $this->assertStringNotContainsString('cus_private', $event->getRawOriginal('payload'));
        $this->assertArrayNotHasKey('payload', $event->toArray());
    }

    public function test_signature_failure_never_enters_inbox(): void
    {
        config(['cashier.webhook.secret' => 'whsec_private']);
        Queue::fake();
        $this->postJson('/stripe/webhook', $this->payload())->assertForbidden();
        $this->assertDatabaseCount('stripe_webhook_inbox', 0);
        Queue::assertNothingPushed();
    }

    public function test_scheduler_recovers_early_invoice_without_stripe_cli_redelivery(): void
    {
        $event = app(StripeWebhookInbox::class)->accept($this->payload());
        $this->assertSame('retry', $event->status);
        $listener = $this->mock(SyncStripeMerchantSubscription::class);
        $listener->shouldReceive('handle')->once();
        $this->travel(10)->seconds();
        $this->artisan('billing:retry-webhooks')->assertSuccessful();
        $this->assertSame('processed', $event->fresh()->status);
        $this->artisan('billing:retry-webhooks')->assertSuccessful();
    }

    public function test_failures_have_bounded_retries_and_do_not_expose_provider_errors(): void
    {
        config(['billing_webhooks.max_attempts' => 2]);
        $listener = $this->mock(SyncStripeMerchantSubscription::class);
        $listener->shouldReceive('handle')->twice()->andThrow(new \RuntimeException('sk_secret_private'));
        $event = app(StripeWebhookInbox::class)->accept($this->payload());
        $this->assertSame('retry', $event->status);
        $this->travel(10)->seconds();
        app(StripeWebhookInbox::class)->retryDue();
        $event->refresh();
        $this->assertSame('failed', $event->status);
        $this->assertSame(2, $event->attempts);
        $this->assertSame('SYNC_ERROR', $event->error_code);
        $this->assertStringNotContainsString('sk_secret_private', $event->toJson());
        app(StripeWebhookInbox::class)->accept($this->payload());
        $this->assertSame(2, $event->fresh()->attempts);
    }

    public function test_queued_and_crashed_worker_leases_are_recovered(): void
    {
        Queue::fake();
        $inbox = app(StripeWebhookInbox::class);
        $event = $inbox->accept($this->payload());
        $this->travel(121)->seconds();
        $inbox->retryDue();
        Queue::assertPushed(ProcessStripeInvoice::class, 2);
        $event->update(['status' => 'processing', 'processing_started_at' => now()->subMinutes(3), 'attempts' => 1]);
        $inbox->retryDue();
        $this->assertSame('retry', $event->fresh()->status);
        Queue::assertPushed(ProcessStripeInvoice::class, 3);
    }

    public function test_manual_retry_does_not_reprocess_successful_events(): void
    {
        $listener = $this->mock(SyncStripeMerchantSubscription::class);
        $listener->shouldReceive('handle')->once();
        $event = app(StripeWebhookInbox::class)->accept($this->payload());
        $this->assertSame('processed', $event->status);
        $this->artisan('billing:retry-webhook', ['event' => $event->id])->assertFailed();
        $event->update(['status' => 'failed']);
        $listener->shouldReceive('handle')->once();
        $this->artisan('billing:retry-webhook', ['event' => $event->id])->assertSuccessful();
        $this->assertSame('processed', $event->fresh()->status);
    }

    public function test_event_without_id_is_rejected_instead_of_acknowledged(): void
    {
        config(['cashier.webhook.secret' => 'whsec_test']);
        $data = $this->payload();
        unset($data['id']);
        $body = json_encode($data);
        $time = time();
        $this->call('POST', '/stripe/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, 'whsec_test')], $body)->assertStatus(400);
        $this->assertDatabaseCount('stripe_webhook_inbox', 0);
    }

    public function test_queue_outage_leaves_a_durable_event_for_the_scheduler(): void
    {
        Bus::shouldReceive('dispatch')->once()->andThrow(new \RuntimeException('private broker details'));
        $event = app(StripeWebhookInbox::class)->accept($this->payload());
        $this->assertSame('pending', $event->status);
        $this->assertSame('QUEUE_UNAVAILABLE', $event->error_code);
        $this->assertNull($event->queued_at);
        $this->assertDatabaseCount('stripe_webhook_inbox', 1);
    }
}
