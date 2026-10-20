# Runtime commission snapshots

The merchant price-approval API now calls CommissionService inside its existing delivery lock/transaction before moving Priced → Matching. The current valid subscription supplies the plan rate and currency. No valid subscription, invalid/mismatching currency/rate, or a mismatched existing snapshot returns validation failure and rolls back approval/history. Existing successful lifecycle fixtures now include a valid subscription.

A snapshot is unique by delivery_id. Repeating approval fails the existing state validation without creating a second record. The approved history includes commission_id. Existing snapshots must match the price and currency and be pending. Rates and monetary amounts are never recomputed by completion or by finance GET requests.

Shared DeliveryTransitionService marks an existing pending snapshot earned (earned_at set) when the delivery reaches Completed, and cancelled (earned_at cleared) when it reaches Cancelled. It runs in the delivery transition transaction and applies to both manual completion and the existing automatic proof-of-delivery completion path. The amounts are retained for audit. Other states, including delivery failure, expiry and rescheduling, keep the snapshot pending until final completion or cancellation. Legacy orders without a snapshot are not backfilled at completion.

A zero delivery fee is valid under the existing pricing API and now produces a zero commission; negative amounts remain invalid. Existing integer-cent arithmetic and half-up rounding are preserved. This is not COD collection, proof of payment, Stripe payout or a settlement ledger. Earned does not mean paid.

Tests added: approval/idempotency, rollback without valid subscription, preserved rate after plan change/expiry, completion, cancellation, free delivery, cent rounding, legacy missing snapshot, mismatched snapshot. PHP tests require Docker execution; packaging environment provides syntax parsing only.

Manual acceptance: approve a newly priced order on a valid subscription; open the merchant finance ledger and see pending commission; complete the delivery through the proof workflow and see earned; cancel another approved order and see cancelled. Verify that an expired account subscription blocks a new approval with a clear validation message.
