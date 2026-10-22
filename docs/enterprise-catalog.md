# Enterprise catalog integrations

Raslo now provides a provider-neutral product synchronization contract, a public product catalog on the web and mobile app, merchant connection management, and administrator store publication review.

## What is implemented

- A separate key for every store connection, scoped to `catalog:read` and/or `catalog:write`.
- Keys returned once, stored only as SHA-256 hashes, rotated or disabled by the owning merchant.
- Positive-price Enterprise access backed by a currently paid Stripe period. Free Enterprise placeholders, manual grants, trial/incomplete states, expired periods, disabled plans and inactive merchants cannot use this API.
- Automatic initial import for a compatible HTTPS feed; repeated imports every 15 minutes, plus manual sync.
- Push batches for custom stores and external adapters.
- Stable external IDs and source timestamps prevent duplicates and stale overwrites.
- Public product cards, search, pagination, images and store links. Only approved stores with an active connection, valid Enterprise entitlement, active products and positive stock are visible.
- Queue-backed sync runs with counters and safe failure codes. Missing products are not automatically deleted on a partial/failed import.

**A store URL alone cannot expose every provider's catalog.** The source must implement this contract, or an adapter must map that provider's products into it. This release includes a reference feed server and push client. An installable WooCommerce Feed connector (pilot 0.1.0) is now included under `integrations/woocommerce`; a Shopify OAuth app remains future work. Product display is implemented; carts, reservations, checkout, commissions and marketplace payouts are not.

## Setup

1. Apply migrations and start the queue worker and scheduler:

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan optimize:clear
docker compose up -d --build queue scheduler
docker compose exec app php artisan queue:restart
docker compose exec app php artisan schedule:list
```

Set `QUEUE_CONNECTION=database` (or Redis); do not use `sync` in a deployed web process. A synchronous queue can block connection creation while importing. The included worker has a 600-second timeout, and database/Redis `retry_after` defaults are raised to 720 seconds. If your existing `.env` overrides `DB_QUEUE_RETRY_AFTER` or `REDIS_QUEUE_RETRY_AFTER`, set them to at least 720. For multiple production nodes use a shared cache/queue and one scheduler.

2. Configure your agreed positive Enterprise monthly price:

```bash
# Example only: replace 999.00 with the actual contracted monthly AED price.
docker compose exec app php artisan catalog:configure-enterprise 999.00
```

Create a matching active, monthly AED Stripe Price and set `STRIPE_PRICE_ENTERPRISE=price_...` in `.env`. Its amount must match the plan exactly and its ID must be distinct from Growth/Business. Clear configuration caches. Enterprise checkout is now accepted by the billing API and mobile subscription screen. A checkout redirect alone never grants access; the existing signed paid-invoice workflow must establish the paid period. Existing unpaid/manual Enterprise rows are not converted to paid access.

3. For pull feeds, configure exact hostnames in `CATALOG_FEED_HOSTS`, for example `shop.example.com,catalog.another-store.example`. Do not use wildcards. HTTPS port 443, public DNS/IPs and certificate validation are required; redirects and URL credentials/query strings are rejected. Raslo pins the validated IP while fetching to prevent DNS rebinding. API/feed tokens stay on servers.

4. Merchant: open `/merchant/integrations` in the web dashboard or use the management API. Create a Push connection or a Feed connection. Save the returned API key immediately; it cannot be retrieved later.

5. Administrator: review `/admin/catalog` and approve the store. Approval covers current and future products from that connection. Unapproval or disabling the connection hides all of its products immediately. The mobile home screen has a catalog entry, and merchant profiles have an Enterprise integration entry.

## Management API (merchant Sanctum token)

All paths below include `/api`. The management API requires a logged-in active merchant and a paid Enterprise subscription.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/v1/merchant/integrations` | List your connections and product counts |
| POST | `/api/v1/merchant/integrations` | Create a store connection and return its key once |
| PATCH | `/api/v1/merchant/integrations/{id}` | Set `status` to `active` or `disabled` |
| POST | `/api/v1/merchant/integrations/{id}/rotate-key` | Immediately replace the integration key |
| POST | `/api/v1/merchant/integrations/{id}/sync` | Queue a feed import; 202, including when already running |
| GET | `/api/v1/merchant/integrations/{id}/runs` | Paginated synchronization history |

Create body:

```json
{
  "name": "My real store",
  "mode": "feed",
  "scopes": ["catalog:read", "catalog:write"],
  "feed_url": "https://shop.example.com/raslo/catalog",
  "feed_token": "server-generated-secret-at-least-16-characters"
}
```

Use `mode: "push"` and omit the feed fields for a push-only connection. Host approval is an operator configuration; merchants cannot approve their own arbitrary fetch destinations. Feed credentials are encrypted at rest and excluded from responses. A connection’s mode and scopes are fixed in this version. Feed URL/token can now be updated using PATCH or the merchant web form; URLs remain subject to the approved-host policy and tokens remain encrypted. Rotation of the Raslo integration key is separate from rotation of a source Feed token.

## Product API (store integration key)

Send `Authorization: Bearer raslo_live_...` and `Accept: application/json`. Never use a merchant login token here and never expose the key in browser/mobile code.

- `GET /api/integrations/v1/products`: paginated products owned by this connection; requires `catalog:read`.
- `POST /api/integrations/v1/products/batch`: 1–100 products per batch; requires `catalog:write`.

```json
{
  "products": [{
    "external_id": "store-product-42:variant-blue",
    "name": "Blue travel bag",
    "description": "Product description as plain text.",
    "sku": "BAG-BLUE",
    "category": "Travel",
    "price_minor": 12900,
    "currency": "AED",
    "stock_quantity": 8,
    "status": "active",
    "image_url": "https://shop.example.com/images/bag-blue.jpg",
    "product_url": "https://shop.example.com/products/bag?variant=blue",
    "source_updated_at": "2026-10-22T08:00:00+00:00"
  }]
}
```

Amounts are integer fils: 12900 is AED 129.00. This first release supports AED only. Variants can be separate catalog records with distinct stable external IDs. Product options and a parent/variant purchasing model are not implemented.

`external_id`, name, price, currency, stock, status and source timestamp are required. Description, SKU, category and HTTPS image/product URLs are optional. Each update is a full record; omitted optional fields are cleared. HTML is displayed as escaped plain text, never executed.

Use `active`, `draft` or `archived`. To remove a product from discovery, send the record with `archived` and a newer timestamp; retain this tombstone in the source feed. Repeated or older timestamps are skipped. Timestamps must be timezone-qualified, to the second, and not in the future. Adapters must keep the per-product version timestamp stable for retries and strictly increasing for real changes. Two different changes sharing an identical timestamp cannot be ordered; the second is skipped. Use one synchronization writer per connection to avoid conflicting sources.

A batch is transactional. An invalid record rejects the entire batch with 422. There is a default limit of 10,000 stored records per connection (including tombstones), five connections per merchant, 100 products per batch and 60 API requests/minute/key. Limits are operator-configurable in `config/catalog.php`.

Response:

```json
{"sync_run_id":"uuid","processed":1,"changed":1,"skipped":0}
```

## Pull-feed contract

Raslo calls the configured feed with `Authorization: Bearer <feed_token>`. Return:

```json
{"products":[],"next_cursor":null}
```

Populate the array with product objects using the schema above. The array may be empty, contains at most 100 records, and `next_cursor` must be explicitly null or a nonempty opaque string of at most 200 characters. Subsequent requests send `?cursor=...&limit=100` to the same endpoint; the first request has no cursor, so its default page size must be at most 100. Never return a URL as a next-page destination. The source should paginate consistently and include archived products/tombstones.

Up to 100 pages and 2 MiB per response are accepted by default. Successfully imported pages remain if a later page fails; the run is marked failed, and replay safely resumes by re-importing the feed from its first page at the next scheduled attempt. An incomplete import never removes absent records. Historical runs are retained; operators should define a retention policy before high-volume production use.

Jobs recheck Enterprise entitlement and connection state before each page and database write. Expiry/downgrade hides the catalog immediately without destroying stored products. An eligible renewal permits the next scheduled sync. A stuck job becomes recoverable after one hour. Monitor `catalog_sync_runs`, queue health and `last_sync_error`; safe codes intentionally omit provider response details.

## Public discovery

`GET /api/v1/catalog/products?q=bag&page=1&store=1&category=Travel` is public and returns 24 cards/page. It does not expose integration credentials, merchant contact details or private product records. The website uses `/catalog`, and the app uses `/catalog` navigation against the same API. Prices and availability reflect the last sync; checkout must revalidate at the source. No order or delivery is created by browsing.

## Adapter references and next improvements

- `examples/catalog/push.mjs`: upload mapped records from a file, suitable as a server-side adapter building block.
- `examples/catalog/feed.mjs`: a token-protected reference feed backed by an actual mapped product file; put it behind HTTPS on an approved store hostname.
- `docs/catalog-openapi.json`: machine-readable API contract.

Recommended next work: acceptance testing of the included WooCommerce connector and a Shopify adapter with authorized installation/uninstall handling; event-based incremental sync with periodic reconciliation; per-product moderation and image caching; then cart/checkout, stock reservation and marketplace settlement as a distinct phase.
