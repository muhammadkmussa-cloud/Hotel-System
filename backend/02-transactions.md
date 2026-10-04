# Transaction and idempotency rules

These are required engineering safeguards derived from the workflows, not optional performance improvements.

## Submit table order

1. Authenticate enrolled device and active guest binding; validate body structure.
2. Look up idempotency key scoped to principal and operation. Same body returns existing result; different body returns conflict.
3. Begin transaction; lock visit/guest bill and relevant availability in deterministic order.
4. Check open state, expected versions, published meal versions, valid removals, quantity, price acknowledgment, and availability.
5. Create immutable submission/items and charges. Charges remain proposed while review/payment is required and post on release. Reserve/decrement portions under the configured policy. Review-required work is held.
6. Create station work/outbox entries only if release guards pass. Save idempotent result in the transaction.
7. Commit; respond with saved IDs. Deliver live/print side effects afterwards.

If response transmission fails, the same key retrieves the committed result. Do not add a second submission because the UI did not receive the first response.

## Create checkout

Lock selected bills/charge allocations; verify staff/guest authority; ensure no overlapping active checkout; compute total using posted server entries; record immutable amount and currency; lock those allocations. Return available methods based on provider capability and amount granularity. Guest checkout does not lock unrelated guests.

## Apply payment

Store incoming evidence in a durable inbox. Verify provider-specific authenticity and successful transaction details against merchant, reference, currency, and amount. Lock attempt/checkout. Insert unique payment identity and allocate no more than the amount due. For an eligible kiosk order, post its proposed charges, allocate payment, release work, and update projections in the same transaction. If fulfilment guards fail, retain the money as unapplied and create an exception rather than posting an unfulfillable sale. Record exceptions for overpayment and stale checkout as well.

External card recording follows the same ledger rules but requires cashier role, external reference, and explicit confirmation. Cash records tendered amount, change, net applied amount, and who holds it. Neither route may claim an electronic provider verification that did not occur.

## Shared allocations and discounts

Staff-confirmed share changes lock every affected bill in sorted order. All must still be editable and free of unresolved payment attempts. Split the full item charge consistently, including its extras and applicable tax/service components. Sum must equal the source charge; allocate remainders deterministically. A discount creates a traceable adjustment; never rewrite the original price snapshot.

## Close visit

Lock visit and all bills; reject unpaid balance, pending/unknown payment, unreviewed adjustment, or unresolved ordering mutation. Verify staff assignment/permission. Commit closed state, revoke bindings, and issue session-ended event. A later stale submit must fail even if its tablet still shows the old menu. Existing idempotent retries return their old result without reopening the visit.

## Catalogue changes

Publish a new immutable version and event. A submitted order retains old version/price/options. Drafts referencing a changed version must be requoted and explicitly accepted. Availability can change independently of content publication.

## Failure requirements

Crash tests cover commit-before-event, provider-success-before-local-update, and printer-send-before-result. Use database constraints to enforce uniqueness even under simultaneous requests. Reconciliation jobs must find stranded inbox/outbox records. Do not claim universal exactly-once delivery; enforce at-most-once business application and durable recovery.
