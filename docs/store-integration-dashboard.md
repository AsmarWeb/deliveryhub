# Store integration workspace

Catalog administration and merchant integrations now extend `layouts.dashboard`. The sidebar exposes Store review to administrators and My stores & integrations / Connectors & plugins to merchants, with active-link states and shared RTL/mobile navigation. The former header-only shortcuts have been removed.

## Merchant flow

1. Open **Connectors & plugins**. All active merchants may read setup instructions; current paid Enterprise access is required for downloads, creation, credential updates, checks and synchronization.
2. Choose **WooCommerce**, download the installable ZIP, then activate it after WooCommerce in WordPress.
3. Enable its feed, save the one-time Feed token and paste the actual HTTPS feed URL into the setup form. Include any store subdirectory. WordPress administrator credentials and Stripe keys are never requested.
4. Ask the operator to approve the exact source hostname in `CATALOG_FEED_HOSTS` if needed. The setup wizard does not weaken this allowlist or allow private-network addresses.
5. Save the connection. Initial Feed synchronization is queued through the existing scheduler.
6. In **My stores & integrations**, inspect connection status, publication approval, imported-product count and recent sync runs. Times are displayed in Asia/Dubai. Use **Check connection** to read/validate the first page without importing or approving anything, or **Sync now** to queue the existing importer.
7. After admin publication approval, eligible in-stock products can appear in discovery. A current paid subscription and enabled connection remain required.

Zero records after a successful check means the first feed page was empty, not that the source store was fully imported. Check WordPress indexing, scheduled actions and product visibility/stock. Checks never expose upstream exception messages or saved credentials. They use the existing HTTPS/allowlist/public-IP-pinned HTTP client.

## Other platforms

The API guide includes the existing Push endpoint, request shape and an authenticated OpenAPI download. Laravel/custom systems and other platforms need a compatible adapter or feed. Shopify has no native installable connector in this release; its card states that explicitly. No checkout, order forwarding or delivery booking has been introduced.

## Downloads

The bundled `resources/integrations/raslo-woocommerce.zip` is intentionally outside `public/`. Only the controller download route serves it after authentication, active-account and paid Enterprise checks, with no-store headers. The ZIP has the required `raslo-woocommerce/` root and is built from the existing plugin 0.1.0 sources. The UI shows version and SHA-256.

To rebuild after an intentional plugin-source change:

```sh
python3 scripts/build-woocommerce-plugin.py
```

Keep the displayed version in `CatalogDashboardController::guide` aligned with the plugin header when publishing a new plugin version. No Python or PHP ZIP extension is needed to serve the prebuilt download.

## Administration

Store review supports name/merchant search and pending/approved filters, with eager-loaded product previews. Approval/revocation uses the existing admin-only action and applies to current/future products on the connection. It does not grant billing access or guarantee stock. Neither feed tokens nor integration key hashes are included in the page.

## Validation

New feature coverage exercises dashboard composition, role separation, paid download access, expiry/free-plan rejection, ZIP structure, JSON contract download, filters, merchant ownership, read-only checks, invalid feed records and secret-free failure reporting. The former same-second indexing test now uses a test-only clock rather than wall-clock sleep to remove a timing-dependent failure; production plugin behavior is unchanged.
