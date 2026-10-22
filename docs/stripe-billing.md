# Stripe billing integration

## Setup

Configure the backend `.env` locally. Never commit actual key values:

```
APP_URL=http://YOUR_MAC_LAN_IP:8080
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_PRICE_GROWTH=price_...
STRIPE_PRICE_BUSINESS=price_...
STRIPE_PRICE_ENTERPRISE=price_...
CASHIER_CURRENCY=aed
```

Use test-mode keys and test-mode monthly recurring prices for development. Growth and Business must have distinct Price IDs. Their currency, fixed amount and monthly interval must match the stored plans. The backend checks these before opening Checkout. APP_URL must be reachable from the phone's browser. Use your server's public HTTPS origin in production.

```
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan billing:check
docker compose exec app php artisan test
```

`billing:check` checks local configuration without printing secrets or contacting Stripe. This update requires no new database migration.

## Local webhook forwarding

Install/authenticate the Stripe CLI on the Mac. In another terminal:

```
stripe listen --events invoice.paid,customer.subscription.created,customer.subscription.updated,customer.subscription.deleted,invoice.payment_action_required,invoice.payment_succeeded --forward-to http://localhost:8080/stripe/webhook
```

Copy the signing secret emitted by this listener into the backend `.env` as `STRIPE_WEBHOOK_SECRET`, then clear configuration again. The CLI listener's secret is different from the secret for a Dashboard endpoint. Leave the listener running while testing.

In production register `/stripe/webhook` on the backend's HTTPS origin as a snapshot event endpoint and configure its own signing secret. Preserve Cashier's normal subscription/customer events as well as `invoice.paid`.

## Behavior

- A custom Cashier controller handles `invoice.paid`, retains Cashier's signature verification and queues the existing subscription synchronization rules through a durable inbox. Missing signing configuration returns 503; unsigned events are rejected when configured.
- Verified paid invoices are saved to an encrypted durable inbox before acknowledgment. If a subscription is not available yet, Raslo retries in the background and wakes pending invoices after related subscription events. Monitor `billing:webhooks`; see [automatic recovery](stripe-webhook-recovery.md).
- Only configured plan prices and matching customer/currency can grant access. Access dates come from the paid invoice's subscription line, rather than a later potentially unpaid remote period. Replays cannot duplicate the local subscription or shorten a newer confirmed period.
- Periods already confirmed as paid remain available until their end when Stripe reports `past_due` or `canceled`. No new paid period is created by a failed renewal, a redirect, a trial or a subscription status event alone. `unpaid`, expired and incomplete subscriptions have no access. Manual subscriptions can grant ordinary plan access; automation still requires Stripe.
- API display, commissions and automation share the same access service. The billing API returns `has_access`, `stripe_status`, automation availability, usage and allowance.
- Success, cancel and portal-return pages are informational only. The fixed `deliveryhubmobile://merchant/subscription?billing=return` link opens an installed development/production build. In Expo Go, return manually to the app and refresh instead.
- The mobile app refreshes on focus, foreground return and deep-link return, with brief bounded polling for delayed webhooks. A server-provided paid-access flag determines the label. Existing subscribers are directed to the Stripe portal; expired cancellations may create a new subscription.

## Acceptance in Stripe test mode

1. Run all backend tests and `billing:check`; keep the CLI webhook listener running.
2. Sign in as an approved merchant and subscribe to Growth from the phone.
3. Complete Stripe's test checkout. Confirm the webhook returns 2xx and one matching merchant subscription appears with the paid period.
4. Return to the phone. Confirm Growth, access, dates and automation allowance update without restarting the app.
5. Repeat the webhook delivery. Verify the local subscription is not duplicated.
6. Cancel a checkout. Confirm no access was created by the return page.
7. Manage renewal/cancellation through the portal. Verify paid access remains until its confirmed period ends and does not extend without a new paid invoice.
8. Test a failed renewal, expired access and a delayed/replayed old invoice and an invoice that arrives before its subscription. Confirm no unpaid renewal is unlocked.
9. Verify the webhook rejects invalid signatures; do not disable verification to make a test succeed.

Backend tests include signed routing, signatures, configuration, invoice-before-subscription retry, duplicate delivery, unpaid and delayed invoices, legacy/new invoice structures, price validation, shared access and non-activating return pages. They were syntax-checked here; PHP/PHPUnit must be run in the project's Docker environment. Mobile tests/typecheck/lint and platform export are checked separately.

## WooCommerce catalog connector

The catalog connector is separate from Stripe billing. See [WooCommerce setup](../integrations/woocommerce/README.md). Only paid Enterprise access permits Raslo to synchronize/publish the connected catalog.
