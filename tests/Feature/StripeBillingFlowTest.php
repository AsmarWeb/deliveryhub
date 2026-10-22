<?php

namespace Tests\Feature;

use App\Http\Controllers\Billing\StripeWebhookController;
use App\Listeners\SyncStripeMerchantSubscription;
use App\Models\MerchantSubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Services\Billing\MerchantSubscriptionAccess;
use App\Services\Billing\StripeInvoicePeriod;
use App\Services\Billing\StripePlanPrice;
use App\Services\Billing\StripeSubscriptionState;
use App\Services\Billing\StripeWebhookInbox;
use App\Services\Catalog\EnterpriseCatalogAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookHandled;
use Tests\Support\CreatesRecurringDeliveries;
use Tests\TestCase;

class StripeBillingFlowTest extends TestCase
{
    use CreatesRecurringDeliveries, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cashier.webhook.secret' => 'whsec_test', 'services.stripe.prices.growth' => 'price_test']);
    }

    private function postEvent(array $event)
    {
        $event['id'] ??= 'evt_'.hash('sha256', json_encode($event));
        $payload = json_encode($event);
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        ], $payload);
    }

    private function invoice($subscription, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'in_paid_test', 'status' => 'paid', 'currency' => 'aed',
            'customer' => $subscription->merchant->stripe_id,
            'parent' => ['subscription_details' => ['subscription' => $subscription->cashierSubscription->stripe_id]],
            'lines' => ['has_more' => false, 'data' => [[
                'parent' => ['type' => 'subscription_item_details'],
                'pricing' => ['price_details' => ['price' => 'price_test']],
                'amount' => 9900,
                'period' => ['start' => now()->subHour()->timestamp, 'end' => now()->addMonth()->timestamp],
            ]]],
        ], $overrides);
    }

    private function paidSubscription(): MerchantSubscription
    {
        $schedule = $this->schedule();
        $schedule->merchant->forceFill(['stripe_id' => 'cus_test'])->save();
        $subscription = $this->subscribe($schedule->merchant);
        $subscription->plan->update(['slug' => 'growth']);

        return $subscription->fresh(['merchant', 'cashierSubscription', 'plan']);
    }

    public function test_signed_paid_invoice_reaches_custom_controller_and_grants_access_idempotently(): void
    {
        $subscription = $this->paidSubscription();
        $invoice = $this->invoice($subscription);
        $cashierId = $subscription->cashier_subscription_id;
        $subscription->delete();
        $event = ['id' => 'evt_paid', 'type' => 'invoice.paid', 'data' => ['object' => $invoice]];

        $route = app('router')->getRoutes()->getByName('cashier.webhook');
        $this->assertStringContainsString(StripeWebhookController::class, $route->getActionName());
        $this->postEvent($event)->assertOk();
        $this->postEvent($event)->assertOk();
        $this->assertDatabaseCount('merchant_subscriptions', 1);
        $this->assertDatabaseHas('merchant_subscriptions', ['cashier_subscription_id' => $cashierId, 'status' => 'active']);
    }

    public function test_unsigned_webhook_is_rejected(): void
    {
        $this->postJson('/stripe/webhook', ['type' => 'invoice.paid'])->assertStatus(403);
        $this->assertDatabaseCount('merchant_subscriptions', 0);
    }

    public function test_webhook_without_signing_secret_fails_closed(): void
    {
        config(['cashier.webhook.secret' => null]);
        $this->postJson('/stripe/webhook', ['type' => 'invoice.paid'])->assertStatus(503);
    }

    public function test_invoice_before_subscription_is_saved_and_retried_automatically(): void
    {
        $subscription = $this->paidSubscription();
        $invoice = $this->invoice($subscription);
        $cashier = $subscription->cashierSubscription;
        $stripeId = $cashier->stripe_id;
        $subscription->delete();
        $cashier->update(['stripe_id' => 'sub_temporarily_missing']);
        $event = ['id' => 'evt_early', 'type' => 'invoice.paid', 'data' => ['object' => $invoice]];
        $this->postEvent($event)->assertOk();
        $this->assertDatabaseHas('stripe_webhook_inbox', ['id' => 'evt_early', 'status' => 'retry', 'error_code' => 'SUBSCRIPTION_PENDING']);
        $this->assertDatabaseCount('merchant_subscriptions', 0);
        $cashier->update(['stripe_id' => $stripeId]);
        app(SyncStripeMerchantSubscription::class)->handle(new WebhookHandled([
            'type' => 'customer.subscription.created', 'data' => ['object' => ['id' => $stripeId]],
        ]));
        $this->assertDatabaseHas('stripe_webhook_inbox', ['id' => 'evt_early', 'status' => 'processed', 'attempts' => 2]);
        $this->assertDatabaseCount('merchant_subscriptions', 1);
    }

    public function test_unpaid_invoice_does_not_extend_access(): void
    {
        $subscription = $this->paidSubscription();
        $before = $subscription->ends_at->toISOString();
        $invoice = $this->invoice($subscription, ['status' => 'open']);
        $this->postEvent(['type' => 'invoice.paid', 'data' => ['object' => $invoice]])->assertOk();
        $this->assertSame($before, $subscription->fresh()->ends_at->toISOString());
    }

    public function test_delayed_invoice_does_not_shorten_or_extend_confirmed_newer_period(): void
    {
        $subscription = $this->paidSubscription();
        $before = $subscription->ends_at->toISOString();
        $invoice = $this->invoice($subscription, ['lines' => ['data' => [[
            'period' => ['start' => now()->subDays(10)->timestamp, 'end' => now()->addDay()->timestamp],
        ]]]]);
        $this->postEvent(['type' => 'invoice.paid', 'data' => ['object' => $invoice]])->assertOk();
        $this->assertSame($before, $subscription->fresh()->ends_at->toISOString());
    }

    public function test_price_and_credit_lines_cannot_grant_the_wrong_plan(): void
    {
        $period = app(StripeInvoicePeriod::class);
        $subscription = $this->paidSubscription();
        $invoice = $this->invoice($subscription);
        $this->assertNull($period->forPrice($invoice, 'price_wrong'));
        $invoice['lines']['data'][0]['amount'] = -100;
        $this->assertNull($period->forPrice($invoice, 'price_test'));
    }

    public function test_display_and_automation_access_agree_for_stripe_states(): void
    {
        $subscription = $this->paidSubscription();
        $access = app(MerchantSubscriptionAccess::class);
        foreach (['active' => true, 'past_due' => true, 'canceled' => true, 'unpaid' => false, 'incomplete' => false] as $state => $expected) {
            $subscription->cashierSubscription->update(['stripe_status' => $state]);
            $this->assertSame($expected, $access->allows($subscription->fresh(['plan', 'cashierSubscription']), true), $state);
        }
        $subscription->update(['ends_at' => now()->subMinute()]);
        $this->assertFalse($access->allows($subscription->fresh(['plan', 'cashierSubscription']), true));
    }

    public function test_return_pages_do_not_activate_subscription(): void
    {
        foreach (['success', 'cancel', 'return'] as $outcome) {
            $this->get('/billing/'.$outcome)->assertOk()->assertSee('deliveryhubmobile://merchant/subscription', false);
        }
        $this->assertDatabaseCount('merchant_subscriptions', 0);
    }

    public function test_legacy_invoice_fields_are_supported(): void
    {
        $subscription = $this->paidSubscription();
        $invoice = $this->invoice($subscription);
        unset($invoice['parent']);
        $invoice['subscription'] = $subscription->cashierSubscription->stripe_id;
        $invoice['lines']['data'][0]['type'] = 'subscription';
        $invoice['lines']['data'][0]['price'] = ['id' => 'price_test'];
        unset($invoice['lines']['data'][0]['parent'], $invoice['lines']['data'][0]['pricing']);
        $this->assertNotNull(app(StripeInvoicePeriod::class)->forPrice($invoice, 'price_test'));
        $this->postEvent(['type' => 'invoice.paid', 'data' => ['object' => $invoice]])->assertOk();
    }

    public function test_stripe_price_must_match_currency_amount_and_monthly_interval(): void
    {
        $subscription = $this->paidSubscription();
        $price = ['active' => true, 'currency' => 'aed', 'unit_amount' => 9900,
            'recurring' => ['interval' => 'month', 'interval_count' => 1]];
        $validator = app(StripePlanPrice::class);
        $this->assertTrue($validator->matches($subscription->plan, $price));
        foreach ([['currency' => 'usd'], ['unit_amount' => 990], ['active' => false],
            ['recurring' => ['interval' => 'year', 'interval_count' => 1]]] as $invalid) {
            $this->assertFalse($validator->matches($subscription->plan, array_replace($price, $invalid)));
        }
    }

    public function test_current_api_reports_confirmed_access_and_allows_return_refreshes(): void
    {
        $subscription = $this->paidSubscription();
        Role::firstOrCreate(['name' => 'merchant'], ['display_name' => 'Merchant']);
        $subscription->merchant->user->addRole('merchant');
        $this->actingAs($subscription->merchant->user, 'sanctum');
        for ($i = 0; $i < 8; $i++) {
            $this->getJson('/api/v1/merchant/billing/current')->assertOk()
                ->assertJsonPath('data.has_access', true)
                ->assertJsonPath('data.can_use_automations', true);
        }
        $this->postJson('/api/v1/merchant/billing/checkout', ['plan' => 'invalid'])
            ->assertUnprocessable(); // Reads must not exhaust the checkout limiter.
        $subscription->cashierSubscription->update(['stripe_status' => 'unpaid']);
        $this->getJson('/api/v1/merchant/billing/current')->assertOk()
            ->assertJsonPath('data.has_access', false)
            ->assertJsonPath('data.can_use_automations', false);
    }

    public function test_billing_configuration_check_does_not_expose_secret_values(): void
    {
        config(['cashier.secret' => 'sk_test_private', 'cashier.webhook.secret' => 'whsec_private',
            'services.stripe.prices.growth' => 'price_growth', 'services.stripe.prices.business' => 'price_business']);
        $this->artisan('billing:check')->expectsOutput('OK: Stripe secret key configured')
            ->doesntExpectOutput('sk_test_private')->assertSuccessful();
    }

    public function test_plan_change_invoice_retries_until_cashier_price_is_updated(): void
    {
        $subscription = $this->paidSubscription();
        config(['services.stripe.prices.business' => 'price_business']);
        SubscriptionPlan::create([
            'slug' => 'business', 'name' => 'Business', 'monthly_price' => 299,
            'currency' => 'AED', 'commission_rate' => 7, 'max_active_automations' => 20, 'is_active' => true,
        ]);
        $this->mock(StripeSubscriptionState::class)
            ->shouldReceive('currentPrice')->once()->andReturn('price_business');
        $invoice = $this->invoice($subscription, ['lines' => ['data' => [[
            'pricing' => ['price_details' => ['price' => 'price_business']],
        ]]]]);
        $event = ['type' => 'invoice.paid', 'data' => ['object' => $invoice]];
        $this->postEvent($event)->assertOk();
        $this->assertDatabaseHas('stripe_webhook_inbox', ['status' => 'retry', 'error_code' => 'PRICE_PENDING']);
        $subscription->cashierSubscription->update(['stripe_price' => 'price_business']);
        app(StripeWebhookInbox::class)->wake($subscription->cashierSubscription->stripe_id);
        $this->assertSame('business', $subscription->fresh()->plan->slug);
    }

    public function test_signed_paid_invoice_can_activate_the_enterprise_plan(): void
    {
        $subscription = $this->paidSubscription();
        $subscription->plan->update(['slug' => 'enterprise', 'monthly_price' => '999.00']);
        config(['services.stripe.prices.growth' => 'price_growth', 'services.stripe.prices.enterprise' => 'price_test']);
        $invoice = $this->invoice($subscription);
        $invoice['lines']['data'][0]['amount'] = 99900;
        $subscription->delete();
        $this->postEvent(['id' => 'evt_enterprise_paid', 'type' => 'invoice.paid', 'data' => ['object' => $invoice]])->assertOk();
        $this->assertTrue(app(EnterpriseCatalogAccess::class)->allows($subscription->merchant));
    }
}
