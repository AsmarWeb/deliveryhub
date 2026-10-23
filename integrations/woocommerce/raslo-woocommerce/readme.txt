=== Raslo for WooCommerce ===
Contributors: asmarweb
Requires at least: 6.5
Requires PHP: 8.1
Requires Plugins: woocommerce
Stable tag: 0.2.1
License: MIT

Private WooCommerce product Feed for Raslo Enterprise. Physical simple products and variations; AED only. Checkout remains in WooCommerce.

After activation, open WooCommerce > Raslo, enable indexing and copy the one-time feed token. Create a paid Enterprise Feed connection in Raslo using the endpoint and token. See the accompanying connector README for requirements and acceptance testing.

Delivery orders: optional prepaid AED/UAE orders with Raslo shipping. Uses WooCommerce CRUD, signed status events and polling. Raslo pricing approval stays in the merchant dashboard. COD/refunds/automatic cancellations and multi-shipment orders are unsupported.
