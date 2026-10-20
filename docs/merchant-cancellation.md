# Merchant delivery cancellation

POST `/api/v1/deliveries/{delivery}/cancel` requires an authenticated active merchant owning the delivery, plus a trimmed reason of 3..500 characters. Rate limit: 10 requests/minute. Other merchants receive 404. No actor/merchant/commission data is accepted from the client.

Self-service cancellation is limited to Draft, PendingPricing, Priced, Matching and Expired, with no pickup/delivery timestamp and no pending/accepted driver assignment. Assigned/Accepted and physical delivery states require administrative coordination and cannot use this endpoint. Failed/rescheduled states are intentionally excluded from merchant self-service in this first version.

The delivery row is locked and all checks are repeated in the transaction. Cancellation uses DeliveryTransitionService, which creates the audit history with the merchant actor/reason and changes an existing pending commission to cancelled. No commission is created for drafts/legacy deliveries. Repeating a successful cancellation returns the cancelled state without another history or replacing the original reason. No driver task is silently closed.

The merchant detail screen offers Cancel delivery → enter reason → Confirm delivery cancellation, with Keep delivery as the back-out action. Confirmation and errors are inline for native/web. A rapid repeat tap is guarded. Server state remains authoritative if a driver is assigned while the screen is open.

Tests: five Laravel tests cover history/commission/retry, allowed states, ownership/guest access, reason validation/protected states and inconsistent active assignments/previous pickup. Two mobile tests cover endpoint/payload, invalid inputs and UI state policy. PHP tests need Docker execution; packaging environment has syntax parsing only.

Manual test: approve a fresh delivery price, cancel while Matching with a reason, verify Cancelled on the order and commission ledger. Retry the request and verify only one history. Open an Assigned order and verify no cancel action. Verify a stale Matching screen cannot cancel a now-assigned delivery.
