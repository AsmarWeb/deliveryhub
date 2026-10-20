<?php

namespace App\Http\Controllers\Billing;

use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController;

class StripeWebhookController extends WebhookController
{
    public function handleWebhook(Request $request)
    {
        // Fail closed rather than accepting unsigned billing events.
        abort_unless(config('cashier.webhook.secret'), 503, 'Stripe webhook is not configured.');

        return parent::handleWebhook($request);
    }

    protected function handleInvoicePaid(array $payload)
    {
        // Cashier now dispatches WebhookHandled for invoice.paid too.
        // The synchronous listener must complete before we acknowledge Stripe.
        return $this->successMethod();
    }
}
