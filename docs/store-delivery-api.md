# Raslo Enterprise Delivery API / WooCommerce 0.2.0 pilot

The first store-order bridge creates real Raslo delivery records from prepaid UAE store orders. It reuses `DeliveryCreationService`, `DeliveryTransitionService` and the existing optional automatic pricing service. Orders enter `pending_pricing`, or `priced` when existing automatic pricing succeeds. The merchant must still approve the final Raslo price in the existing dashboard before matching/dispatch. The WooCommerce checkout charge is a merchant-set customer charge, not a guaranteed Raslo quote. No new payment collection, COD or automatic price approval is introduced.

## Separate opt-in credentials

In My stores & integrations → Delivery-order integration, choose an owned pickup address and enable delivery access. The dedicated `raslo_delivery_…` key is shown once and stored as a hash; it grants create/read access only on that store connection. It does not grant catalog access. Catalog keys and Feed tokens cannot create deliveries. Paid Enterprise, an active merchant, active connection and explicit delivery enablement are required on every API call. Revocation/rotation prevents new calls; it does not cancel existing deliveries.

An optional approved HTTPS callback URL can be configured with an independently generated encrypted signing secret. The domain uses the same exact `CATALOG_FEED_HOSTS` operator allowlist and public-IP-pinned transport as catalog feeds; there is no redirect, private-IP or arbitrary query-string bypass. Configure the actual WordPress REST URL, including a store subdirectory if present. Plain permalink URLs are unsupported.

## API

Base: `/api/integrations/v1`. All requests use the dedicated bearer key; POST bodies are limited to 64 KiB and rate limits apply per connection.

- `GET /delivery-connection`: safe connection metadata and pickup readiness.
- `POST /deliveries`: create a prepaid delivery, with required `Idempotency-Key` (16–128 ASCII letters/digits/period/underscore/colon/hyphen).
- `GET /deliveries/{uuid}`: read current state for an order owned by this connection.

Download `docs/delivery-openapi.json` from the merchant API guide. Example request body:

```json
{
  "external_order_id": "wc:123",
  "currency": "AED",
  "payment_status": "paid",
  "recipient": {
    "contact_name": "Test recipient",
    "contact_phone": "+971500000000",
    "city": "Dubai",
    "emirate": "dubai",
    "address_line": "Test Tower 8, Apartment 2"
  },
  "package_description": "One physical parcel",
  "instructions": "Local test only"
}
```

The authenticated merchant asserts that payment has been confirmed at its store; Raslo does not independently charge or verify the store gateway. Store WooCommerce validates its paid status and rejects COD. Coordinates are optional UAE-bounded numeric pairs; without them, existing automatic distance pricing may not run and operator pricing is needed. Delivery fee, ownership and status from the source request are never trusted. The server chooses the configured pickup; the recipient address belongs to the merchant.

### Idempotency

Store the exact immutable validated payload and a stable idempotency key before sending. An exact retry returns the original UUID and state with HTTP 200; a first creation returns 201. Reusing a reference/key with another payload **or changing the key for an existing reference** returns 409. The database enforces unique hashes per connection and creation is serialized on the connection row. All address, delivery, history, order mapping and initial outbox inserts share a transaction. No network callback runs before commit.

When a request times out after the server commits, retry the same payload/key. Do not create another reference merely to bypass a conflict. Existing WooCommerce order snapshots are frozen/encrypted before network calls. Corrections after known validation rejection can be retried using the explicit order action; after a possible successful request, the original payload is retained to avoid accidental duplicate deliveries.

### Status and tracking

Responses expose UUID, external reference, internal delivery ID, tracking number, current status, sequence, AED fee (nullable), price-approval flag and optional tracking URL. Customer names, phones, addresses and signing secrets are not returned by the status endpoint.

Set `RASLO_INTEGRATION_PUBLIC_URL` to the public HTTPS origin for customer tracking and the setup page. Existing `APP_URL` is the fallback. Without HTTPS, `tracking_url` is null; status synchronization still works through a separately configured HTTPS API base. The 256-bit opaque tracking capability expires 90 days after creation and returns only tracking number, current state and time, without location/customer data. Treat the link as shareable private order information; no indexing/referrer/caching is allowed by its response.

## Signed status events

The transition service inserts `integration_delivery_webhooks` inside the status transaction before notifications run. This avoids losing an event when a broker is down after commit. The existing scheduler runs `integrations:send-webhooks` every minute; only event IDs are queued. Failed broker dispatches and stale processing/queue claims remain recoverable. HTTP delivery is at least once, with up to 12 attempts and bounded backoff, independent of queue-worker tries.

Headers:

```text
X-Raslo-Event-Id: <event UUID>
X-Raslo-Timestamp: <current Unix seconds>
X-Raslo-Signature: v1=<hex HMAC-SHA256(secret, timestamp + "." + raw_request_body)>
```

The JSON has `id`, `type=delivery.status_changed`, `created_at` and `data` with the delivery UUID, external reference, tracking number, status, sequence and optional tracking URL. It contains no recipient details. Verify the signature against the raw bytes before parsing, allow a five-minute timestamp window, match the event header/body, then match the bound delivery UUID/external order reference. Apply only a strictly newer sequence. Duplicate/older events can return 200 without mutation; a temporary lock or not-yet-bound order must return non-2xx for retry.

Pending/failed/delivered status is visible in the merchant connection page. Operators can use:

```sh
php artisan integrations:webhooks
php artisan integrations:send-webhooks
php artisan integrations:retry-webhook EVENT_UUID
```

Already queued callbacks can continue for existing shipments after a subscription expires; expiry blocks new API access, not existing shipment status notifications. Explicitly disabling the connection/delivery feature stops queued callback attempts without cancelling the shipment. If callback settings are absent, the WooCommerce connector polls every five minutes while enabled and authorized.

## WooCommerce setup

1. Update the plugin to 0.2.0. In the bundled lab, plugin source files are mounted from the backend and update when the patch applies; **do not try uploading a replacement over the read-only lab mount**. On a normal store use the authenticated dashboard ZIP and WordPress's plugin replacement flow, preserving existing settings/data.
2. Open WooCommerce → Raslo deliveries. Copy its callback URL to the Raslo connection settings if callbacks are desired, then save/issue the dedicated key and signing secret.
3. Save a reachable HTTPS API base ending in `/api/integrations/v1`, the key and secret in the WordPress settings and explicitly enable forwarding. Credentials are never redisplayed; blank fields retain their protected values.
4. Add **Raslo / رسلو** to a UAE shipping zone and configure your own checkout shipping charge explicitly. AED, a physical order and exactly one Raslo shipping line are required. Shipping name/phone, city, emirate and a full address are required. Standard WooCommerce emirate codes map to the corresponding Raslo slug.
5. New paid orders are sent asynchronously. Old orders require the explicit **Raslo: retry/create delivery** order action. That action can create a real delivery: only select it for a shipment that should be fulfilled.
6. Review pricing/approve in Raslo. Delivery state and tracking appear separately on the WooCommerce order; native WooCommerce order status, payment and refund state remain unchanged. Cancelling/refunding a WooCommerce order does not cancel the Raslo delivery; use the existing Raslo cancellation workflow and its assignment restrictions.

## Isolated local lab

Both WordPress and Raslo need reachable HTTPS addresses for server-to-server calls. Keep the existing WordPress tunnel running. Start a **second** tunnel for the Raslo backend in another terminal:

```sh
docker run --rm -it --network container:deliveryhub-nginx \
  cloudflare/cloudflared:latest tunnel --no-autoupdate --url http://127.0.0.1:80
```

Use that actual backend HTTPS origin for `RASLO_INTEGRATION_PUBLIC_URL`, clear backend config, restart its queue/scheduler, and configure WordPress's API base as `https://BACKEND-TUNNEL/api/integrations/v1`. The callback uses the **WordPress** HTTPS origin plus `/wp-json/raslo/v1/delivery-events`. Only this source callback hostname belongs in `CATALOG_FEED_HOSTS`. Do not swap the two origins or expose signing secrets in URL parameters.

Start the Compose-managed lab worker (now handles both catalog and delivery actions):

```sh
docker compose -f integrations/woocommerce/demo/compose.yaml up -d wordpress worker
docker compose -f integrations/woocommerce/demo/compose.yaml logs --tail=50 worker
```

After it runs successfully, stop the former ad-hoc `raslo-woo-indexer` container if you still use it. Do not delete lab volumes. The worker uses `--hooks` to retain the working workaround for this lab's group-filter issue.

After configuring delivery access and WordPress settings, explicitly create one idempotent **synthetic local-only** order with no payment-provider call:

```sh
docker compose -f integrations/woocommerce/demo/compose.yaml run --rm cli eval-file /tmp/raslo-delivery-order.php
```

The script refuses non-local environments, names the order as lab data and marks a simulated prepaid status. Re-running returns the same stored demo order rather than creating another. Verify one new Raslo delivery, pending pricing/approval, then price/approve and exercise the existing driver lifecycle. Confirm the WooCommerce delivery metadata advances, old/duplicate callbacks do not regress it, and no extra delivery appears after retry. For real checkout acceptance, use a test payment gateway separately; the seed is not a real payment test.

## Validation boundary

Automated backend tests exercise real migrations/services/transactions with SQLite and fake outbound HTTP/queues. WooCommerce tests use boundary doubles for encryption, mapping, shipping-rate eligibility, retry snapshots, HMAC verification, reordered events, early/busy callback retries and manual old-order opt-in. There is no Docker daemon here, so real WordPress/MySQL/HPOS checkout and live queue/callback acceptance must be completed in the supplied lab before production.

## Checkout readiness and error guidance in 0.2.1

The classic WooCommerce checkout validates the selected shipping recipient before payment when Raslo shipping and forwarding are enabled. It uses the same emirate, phone and address mapper as order forwarding. Billing is used when no separate shipping address is selected; shipping phone falls back to billing phone. This is local field validation, not a live API/credential or serviceability check. Checkout Blocks are not covered by this classic hook; paid-order validation remains the fallback for Blocks and API-created orders.

Order administrators receive bilingual corrective guidance for recipient, authorization, conflict and network errors. Terminal rejections save a review state without scheduling a blind retry. After correcting a known validation rejection, use the explicit Raslo retry/create action. Uncertain network results retain the frozen payload and idempotency key. No automatic payment, price approval or WooCommerce status change is added.
