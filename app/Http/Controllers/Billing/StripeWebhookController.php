<?php

namespace App\Http\Controllers\Billing;

use App\Services\Billing\StripeWebhookInbox;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    public function handleWebhook(Request $request)
    {
        // Fail closed rather than accepting unsigned billing events.
        abort_unless(config('cashier.webhook.secret'), 503, 'Stripe webhook is not configured.');

        abort_if(strlen($request->getContent()) > 2097152, 413, 'Webhook payload is too large.');
        $payload = json_decode($request->getContent(), true);
        abort_unless(is_array($payload) && is_string($payload['type'] ?? null), 400, 'Invalid webhook payload.');
        if ($payload['type'] === 'invoice.paid') {
            // Cashier signature middleware has already authenticated the original request body.
            app(StripeWebhookInbox::class)->accept($payload);

            return $this->successMethod();
        }

        return parent::handleWebhook($request);
    }

    protected function handleInvoicePaid(array $payload)
    {
        // Paid invoices use the durable inbox path above.
        return $this->successMethod();
    }
}
