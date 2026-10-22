# Raslo for WooCommerce — connector 0.2.0

Installable WordPress plugin exposing a private, provider-normalized catalog Feed for the existing paid Raslo Enterprise integration. Checkout and customer payment remain in WooCommerce. Optional prepaid delivery-order forwarding is available with a dedicated delivery key; see `docs/store-delivery-api.md` in the backend. Inventory reservation, COD and automatic cancellations/refunds are not supported.

## Requirements and scope

- WordPress 6.5+, PHP 8.1+, WooCommerce 8.2+ and its initialized Action Scheduler.
- MySQL/MariaDB with InnoDB transactions and named locks (`GET_LOCK`).
- AED store currency and HTTPS store URLs with pretty permalinks.
- Simple physical products and physical variations are published. Variable parent records are archived; variants have independent stable IDs `wc:ID`. Grouped/external/digital products are not published in this release.
- Draft, private, hidden and password-protected products do not publish. Out-of-stock records remain in the feed with zero stock and disappear from Raslo discovery after synchronization.
- Stock-managed quantities follow WooCommerce, including parent-managed variation stock. An in-stock product without managed quantity uses stock 1 only as an availability flag, not as an inventory guarantee.
- Prices use the store's display-price helper and are converted to integer fils. Product detail/checkout at the source remains authoritative for tax, price and availability.

The connector uses WooCommerce's product CRUD/query APIs for source reads. Its own projection table supports bounded feed pages and retains deletion tombstones. Product changes and stock events queue background indexing. A recurring six-hour reconciliation scans source products and previously indexed IDs to recover missed events. Whole-second source versions remain stable on retries and defer rapid changes rather than writing a future timestamp. Indexing locks serialize product reads/writes.

## Install

1. Apply the accompanying Raslo backend update and keep its queue worker/scheduler running.
2. WordPress admin → Plugins → Add New → Upload Plugin → choose `raslo-woocommerce.zip` and activate. Or copy the `raslo-woocommerce` folder to `wp-content/plugins` and activate.
3. WooCommerce → Raslo → **Enable / issue new token**. Copy the displayed feed token immediately. Only its hash is retained in WordPress. Keep the key in your own server configuration; it is not a public browser key.
4. Let Scheduled Actions in group `raslo-catalog` complete the initial product index. The Raslo settings page shows the indexed record count. A low-traffic site needs a reliable WP-Cron runner or scheduled Action Scheduler processing; indexing does not run just because a Feed URL is opened.
5. The page provides the actual feed URL, normally `https://store.example/wp-json/raslo/v1/catalog`. A plain-permalink `?rest_route=...` URL is not supported by Raslo's feed policy; enable pretty permalinks.
6. In the Raslo backend `.env`, append the exact source hostname to `CATALOG_FEED_HOSTS`, clear configuration and restart queue workers.
7. As the paid Enterprise merchant, create a **Feed** connection with the displayed URL and token. Its initial import is queued automatically. Approve store publication in Raslo administration, then inspect website/app discovery.

No WordPress administrator password, customer list, Raslo account password or Stripe key is needed by this plugin. The plugin serves a read-only feed after explicit enabling; Raslo independently enforces merchant subscription and publication approval.

## Updates, rotation and disabling

Update product price, inventory, visibility or status through WooCommerce; let Scheduled Actions finish. Raslo reads the updated projection on its next poll (normally within 15 minutes) or a manual sync. The feed is not a real-time stock reservation system.

Use **Rebuild product index** for reconciliation. Deleted products/variations are archived, and previous imported records can be hidden safely rather than disappearing from a partial feed. Do not delete the projection table to resolve sync errors; deleting tombstones can leave stale public products upstream.

After **Rotate feed token**, the old token stops working immediately. Edit the existing Feed connection in Raslo and save the new token under “تحديث رابط أو رمز التغذية”. You can also update a connection's approved feed URL through the API or web form without creating another store. The new token is encrypted in Raslo and excluded from responses.

Disabling/uninstalling this plugin stops feed access/indexing. It does **not** remove products already imported into Raslo. Disable the corresponding Raslo connection too, which hides its products immediately. Preserve the projection data for reinstalls. Changing store currency away from AED stops feeds; disable the Raslo connection before doing so.

Do not place a CDN full-page cache in front of the authenticated feed. The endpoint returns private/no-store headers. Ensure the web server forwards the Authorization header to WordPress. A token-less request returning 401 is expected.

## Feed format

The endpoint returns the existing Raslo schema with up to 100 records per response and opaque cursor pagination. Pages are also bounded by their encoded payload size to stay under Raslo's response limit. The token is sent in an Authorization Bearer header; it is never a URL parameter. Source versions, external IDs, AED amounts, positive availability and tombstones are mapped automatically.

## Local lab without a real store

An optional isolated WordPress/MariaDB environment is included under `demo/`. Run from the Raslo backend repository:

```bash
docker compose -f integrations/woocommerce/demo/compose.yaml up -d db wordpress
```

Open `http://localhost:8092` and complete the local WordPress setup with credentials you choose for this test site. Then:

```bash
docker compose -f integrations/woocommerce/demo/compose.yaml run --rm cli plugin install woocommerce --activate
docker compose -f integrations/woocommerce/demo/compose.yaml run --rm cli plugin activate raslo-woocommerce
docker compose -f integrations/woocommerce/demo/compose.yaml run --rm cli rewrite structure '/%postname%/' --hard
docker compose -f integrations/woocommerce/demo/compose.yaml run --rm cli eval-file /tmp/raslo-seed.php
```

These commands operate on the separate `raslo-woo-lab` project and its own database, not your Raslo database. They create two clearly labeled test products. The source currency is set to AED. Enable the connector in the local WooCommerce dashboard and wait for its Scheduled Actions to run. To process pending actions manually in this lab:

```bash
docker compose -f integrations/woocommerce/demo/compose.yaml run --rm cli action-scheduler run --group=raslo-catalog
```

Run the source's HTTPS tunnel, reusing the Docker approach that worked for the earlier simulator:

```bash
docker run --rm -it \
  --network "container:$(docker compose -f integrations/woocommerce/demo/compose.yaml ps -q wordpress)" \
  cloudflare/cloudflared:latest tunnel --no-autoupdate --url http://127.0.0.1:80
```

Use its actual HTTPS origin plus `/wp-json/raslo/v1/catalog` as the Raslo feed URL and approve that exact hostname. Local product/image URLs may remain HTTP and are omitted from the normalized feed; this does not block the catalog test. A real HTTPS store supplies real HTTPS images/links.

Test price updates, stock zero/restoration, hidden/draft products, a simple→variable change, parent/variation deletion, token rotation, disabling the connection and expired Enterprise access. Stop the optional lab with:

```bash
docker compose -f integrations/woocommerce/demo/compose.yaml down
```

The command preserves lab volumes. Do not add `-v` unless you intentionally want to delete that test site's data.

## Validation boundary

Automated tests cover mapping, token checks, stock/visibility rules, stable versions, deferred updates, pagination, deletion tombstones and locking behavior using WordPress/WooCommerce boundary doubles and a SQLite projection test adapter. The Docker lab is supplied for acceptance against real WordPress/WooCommerce/MySQL; it has not been run in this execution environment, which has no Docker daemon. Treat version 0.2.0 as a pilot until those acceptance steps pass on your site.


## Delivery-order integration (0.2.0 pilot)

Catalog Feed settings remain unchanged. Delivery forwarding is a separate opt-in:

1. In Raslo → My stores & integrations → Delivery-order integration, choose a saved pickup address, enable delivery access and optionally configure the callback URL shown by WordPress.
2. Copy the dedicated delivery key and callback signing secret once. They are not the catalog Feed token. Configure WooCommerce → Raslo deliveries with a reachable HTTPS Raslo API base ending in `/api/integrations/v1`.
3. Add Raslo to a UAE WooCommerce shipping zone and enter your own explicit customer shipping charge. No price is supplied automatically. This charge is independent of the final Raslo price, which still needs your approval in Raslo.
4. New paid AED orders with physical items, a UAE recipient and exactly one Raslo shipping line are queued. COD, unpaid, refunded, cancelled, non-AED, non-UAE and mixed/multi-shipment orders are not automatically forwarded. Older orders require the explicit Raslo retry order action.
5. Status callbacks use HMAC-SHA256, timestamps and monotonic sequence IDs. Polling recovers missed updates. Neither WooCommerce payment/refund state nor its native order status is automatically changed. Delivery state is saved separately and displayed in the order.

Use WooCommerce order CRUD for HPOS compatibility; no direct order-post/meta SQL is used. Pending payloads and credentials are encrypted with the WordPress auth salt. Do not change salts or repoint a configured shop to another Raslo instance without reconciling pending orders.

Action Scheduler processing must include `raslo_wc_send_delivery`, `raslo_wc_poll_delivery`, `raslo_wc_reconcile_deliveries`, in addition to the catalog hooks. The demo Compose now contains an optional persistent `worker` service for both. A bounded recurring reconciliation scans up to 5,000 recent orders from the last 30 days, skipping terminal deliveries, to recover missing scheduled jobs. Older missing jobs require the explicit order action.

**The real WordPress/MySQL checkout acceptance test remains required.** Automated bridge tests use WordPress/WooCommerce boundary doubles, not a live store or payment gateway.
