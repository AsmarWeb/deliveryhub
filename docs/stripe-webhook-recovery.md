# Durable Stripe invoice recovery

## Behavior

The existing Cashier signing middleware still verifies the original request body. Missing configuration returns 503 and an invalid/missing signature is rejected. Only verified `invoice.paid` events use the new durable inbox; Cashier continues to handle subscription/customer events normally.

For an invoice, Raslo commits an encrypted payload to `stripe_webhook_inbox` before acknowledging delivery. It then queues a job containing only the event ID. Duplicate IDs do not replace the original payload or duplicate activation. A successful HTTP response means the event was recorded, not that a paid plan was necessarily activated.

If the subscription has not arrived yet, the row becomes `retry` with `SUBSCRIPTION_PENDING`. Subscription-created and subscription-updated events wake matching pending invoice rows after Cashier has applied the subscription update. The scheduler also retries due rows every minute, so recovery does not depend on Stripe CLI re-sending the failed webhook.

Other pending reasons include `STATE_PENDING` and `PRICE_PENDING`. Unexpected errors are stored as safe `SYNC_ERROR`, without raw responses, credentials or customer details. Processing is limited to 12 attempts using configured backoff. Exhaustion becomes `failed`, visible to operators and recoverable through an explicit retry after fixing the cause. A lost queue dispatch is recoverable after its short lease expires; interrupted workers are recovered after two minutes. The per-event lock expires after 90 seconds and the processing job timeout is 60 seconds.

The existing subscription sync rules remain authoritative: only a confirmed paid invoice can grant a period; customer, configured price, currency and invoice line period are checked, and old or duplicate invoices cannot extend an unpaid renewal. `processed` can also mean an irrelevant/old invoice was safely ignored. Check the merchant's paid-access flags for the actual result.

## Apply and operate

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan optimize:clear
docker compose up -d queue scheduler
docker compose exec app php artisan queue:restart
docker compose restart scheduler
docker compose exec app php artisan schedule:list
```

Use a durable asynchronous `QUEUE_CONNECTION` such as database or Redis. The existing queue worker and scheduler must remain running. The `sync` driver works for unit tests but blocks HTTP requests and is not the deployment configuration for this feature. Keep the existing queue retry-after setting greater than the longest job timeout (720 seconds already configured for catalog jobs).

Diagnostics never print stored payloads:

```bash
docker compose exec app php artisan billing:webhooks
docker compose exec app php artisan billing:retry-webhooks
docker compose exec app php artisan billing:check
```

`billing:check` now reports Enterprise price configuration when configured, checks all configured price IDs for duplicates, and warns about unsuitable queue drivers. It does not contact Stripe or certify a payment.

For an exhausted event, fix the underlying configuration/state before:

```bash
docker compose exec app php artisan billing:retry-webhook evt_ACTUAL_STORED_EVENT_ID
```

For an old failed webhook from before this update (no inbox row exists), retrieve its authentic event from the configured Stripe account:

```bash
docker compose exec app php artisan billing:replay-stripe-event evt_ACTUAL_EVENT_ID
```

This performs an authenticated Stripe API read, verifies the event shape and submits it to the same inbox. It never creates a charge. Stripe event retrieval availability and account/mode must match the original event. Do not use `stripe trigger invoice.paid` as a substitute for the real paid event; synthetic unrelated fixtures should not activate a merchant.

## Local acceptance

Leave Stripe CLI running with the existing events and `/stripe/webhook` destination. Complete a test subscription once, inspect `billing:webhooks`, and refresh the application after processing. An early paid invoice should receive 200 after durable storage, enter retry if required, then become processed automatically when its subscription exists. An unsigned webhook must still fail. Replays must leave one paid local subscription period.

Invoice payloads are encrypted using the Laravel APP_KEY. Back up that key securely; changing it without a supported key rotation strategy prevents reading stored pending payloads. No external customer data or raw provider error is logged by the new jobs. Define operational monitoring, backup and retention for the inbox before sustained production volume; failed events require attention instead of silent indefinite retries.
