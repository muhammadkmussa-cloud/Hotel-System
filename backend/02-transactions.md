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

## Implemented foundation wrapper

P02.08 provides App\Support\DatabaseTransaction::run for top-level MySQL work. It retries the whole database-only callback at most three times only for confirmed 40001/1213 deadlock diagnostics after rollback, with 10ms/20ms delays. Nested transactions, manual transaction control and DDL are outside its callback contract. Re-read models within each attempt; commit durable outbox/idempotency records with the business writes in later workflows. External effects inside a retried callback are prohibited. Other errors and ambiguous commits propagate without replay; future API/CLI boundaries must redact private driver details. This foundation is tested locally, but the business transactions described above remain unimplemented.

## P03.08 implemented durable command replay

IdempotentCommand.run uses the existing top-level MySQL transaction wrapper. The caller must first authenticate and authorize the active principal/resource, then supply a server-derived principal scope and operation (including target identity), a validated Idempotency-Key, and the already bounded raw JSON bytes. Scope/operation are not accepted from client fields. A primary-key hash scopes the key to principal and operation; the separate body hash is byte-exact. Whitespace/key-order changes therefore conflict when a key is reused. Clients must retain the original body bytes for retries.

A unique insert claims the identity; a concurrent caller waits for the transaction and reads the saved result under a row lock. Same identity/body returns stored domain data/status without rerunning work. Changed body raises 409. Work and result persist together; callback failures or oversized/unserializable results roll back both. A committed but damaged/incomplete result fails closed and never reruns work. Confirmed deadlocks follow the bounded P02.08 retry policy; ambiguous commit failures propagate, and clients recover using the same key.

CommandResult contains only array/scalar JSON data and status200/201/202; each HTTP response adds its own fresh envelope/request ID. Do not store tokens, full request bodies or unrelated private data in a result. The callback is trusted internal database-only code using the supplied connection, with no DDL, manual transaction control or external effects. It must write future outbox records in this transaction. The helper cannot sandbox arbitrary callback code. No business route invokes it yet.

Transport records have no automatic expiry/deletion. Permanent provider/financial uniqueness still needs its own domain constraints. Guarded demo reset explicitly clears idempotent_commands only in the separately marked demo schema, with the same transaction/rollback protections as settings. Existing demo schemas must receive the new migration before reset.
