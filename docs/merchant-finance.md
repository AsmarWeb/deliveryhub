# Merchant delivery fee ledger

GET `/api/v1/merchant/finance` requires an authenticated active merchant. The merchant is inferred from the signed-in user. Query parameters: page (1..100000), status (DeliveryStatus value), optional uppercase three-letter currency. Fixed page size 20, sorted newest delivery ID first.

Only deliveries with a non-null delivery_fee are listed, including zero-price deliveries and cancellations. Each row has delivery ID/tracking number/status, recorded fee/currency, creation/delivery timestamps, and an optional existing commission record. Missing commission is null, not a zero amount. The query does not create, recompute or settle commissions.

Merchant price approval now saves a pending commission snapshot inside the same transaction. It requires a valid subscription and matching currency. Completion marks that existing snapshot earned; cancellation marks it cancelled. Amounts and rates are preserved after approval. Legacy deliveries without snapshots remain explicitly missing; historical rates are not invented. Earned means operationally earned, not paid or remitted.

The mobile entry is Merchant account → Wallet & billing → Delivery fees & commissions. It supports all/completed/cancelled filters, pagination, retry, pull-to-refresh, currency-specific row formatting, Arabic/English and delivery detail links. Stripe subscription invoices remain in the Stripe portal.

This is a recorded-fee view, not a receivable balance, cash-collected confirmation, payout system or COD settlement ledger. No cross-currency totals are added. Cancellations are explicitly visible; a price on a canceled order is not declared payable.

Next COD implementation needs independent fields and events for goods amount, who collects the delivery fee, collection confirmation, remittance/receipt, exceptions and audit history. Goods proceeds must be separate from delivery fee commissions. Subscription Checkout must not be reused to imply COD money movement.

Five Laravel feature tests cover ownership/read-only behavior, preserved commission amounts, status/currency filters, pagination/validation and guest/pending-account access. Packaging environment has no PHP runtime; execute tests in Docker. Mobile TypeScript/lint/unit tests and platform exports are checked. Authenticated device testing remains necessary.
