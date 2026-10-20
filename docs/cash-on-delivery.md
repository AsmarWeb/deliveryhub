# Cash on delivery: full goods amount confirmations

This first version records manual confirmations of the **goods value**, separate from delivery fees and commissions. All COD amounts are AED. It does not execute a payment, debit, remittance, Stripe charge or payout. It does not collect delivery fees from recipients. No cash balance or combined goods/fee/commission total is inferred.

## Creation and immutable amount

POST `/api/v1/deliveries` accepts optional cod_amount (nonnegative decimal, maximum 9999999999.99, at most two decimal places). Omitted/null/zero means prepaid/no COD record. Positive values create exactly one delivery_cod_records row in the same creation transaction. Goods amount/currency are snapshots; confirmation endpoints accept no amount change. Existing orders and recurring templates are not backfilled or changed. The mobile input normalizes Arabic/Persian digits and decimal separators to an exact decimal string without floating-point calculations; grouping separators are not accepted.

## Confirmation states

- expected: amount requested by merchant; no claim that cash was collected.
- collected: the assigned accepted driver explicitly confirms full cash receipt from the recipient, after confirmed delivery proof from that same driver and Delivered/Completed order status.
- received: the owning merchant explicitly confirms receipt of the full amount from the driver, after driver collection was recorded.
- cancelled: delivery cancellation closes an expected (uncollected) record.

Each confirmed phase preserves the actor user ID and timestamp. Repeated confirmation returns the same record without replacing those fields or duplicating records. Completion/proof alone does not confirm cash. Existing completed orders without a COD snapshot are not given invented balances. The confirmation endpoints are full-amount only: no partial payments, refunds, adjustments, reversals, bank-transfer reconciliation or dispute resolution. Do not manually edit the database to simulate reconciliation; those need a separately audited workflow.

## API and access

- GET `/api/v1/deliveries/{delivery}/cod`: owning merchant or driver with pending/accepted assignment; null for a non-COD order.
- POST `.../cod/collect`: assigned accepted driver, `confirmed: true`, matching confirmed proof, Delivered or Completed.
- POST `.../cod/receive`: owning merchant, `confirmed: true`, previously collected.

All require authenticated active accounts. Changes lock the delivery then COD record and revalidate assignment/proof. Read/write endpoints are rate limited. Another merchant/driver cannot access the record. The ordinary delivery status/commission transitions are preserved.

## App workflow

Merchant: create delivery → optional goods COD amount in package details → review amount → create. Order details show the COD record. Driver: assigned delivery details show the amount → record delivery proof → return to details/refresh → explicitly tick full cash receipt and confirm. Merchant: refresh order details → tick actual receipt from driver and confirm. Confirmation times display in Asia/Dubai. Merchant finance rows show the goods amount and manual confirmation state separately from the fee/commission.

## Validation

Eight Laravel tests cover creation/backwards compatibility, amount bounds, explicit proof-gated confirmations, idempotent actor/time preservation, unchanged amount/fee, ownership/roles, receipt ordering, inactive/guest protection, cancellation and old orders. PHP files are syntax parsed in the packaging environment; execute all Laravel tests in Docker. Mobile amount tests, TypeScript/lint and platform exports run locally. Authenticated/device/real cash verification remains manual.
