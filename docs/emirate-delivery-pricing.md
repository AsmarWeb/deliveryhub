# Automatic delivery pricing by emirate

Administrators configure directed pickup-emirate → destination-emirate rates in **Delivery Pricing Settings**. No rates are prefilled and the fallback is disabled by default. Existing distance rates remain unchanged.

1. Run the new migration, clear caches and restart queue/scheduler.
2. Enable automatic pricing and the emirate fallback checkbox.
3. Enter the full route charge in AED for each supported direction. Dubai → Sharjah is independent of Sharjah → Dubai. Blank cells require manual pricing. An explicit zero is accepted; the global minimum still applies.
4. Ensure both the merchant pickup address and the recipient address have an emirate selected.
5. Create a new store order to test. Existing completed/priced deliveries are not recalculated; saving settings does not bulk-reprice pending orders.

When all coordinates are present, the existing straight-line distance calculation takes priority, including its maximum distance limit. Invalid coordinates or a distance above that limit require manual pricing and do not fall back to an emirate rate. When any coordinates are missing, the configured emirate route is used. The maximum distance setting applies only to the distance method; emirate rates explicitly authorize their configured routes.

Both methods respect the global minimum. The quoted fee and pricing method/rates are recorded in status history; later setting changes do not alter an accepted snapshot. A priced delivery still needs merchant approval before assignment. WooCommerce checkout shipping fees are separate from Raslo quotes; this update does not change what the buyer is charged or mark WooCommerce orders completed.

Scope: seven UAE emirates, with a separate rate per direction. Neighborhood/geofenced pricing is not implemented. Unknown or absent emirates keep the delivery pending pricing; no emirate is guessed from a city name. Existing WooCommerce emirate-name fixes remain separate and must be retained.
