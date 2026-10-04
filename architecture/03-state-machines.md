# State machines and release conditions

Canonical lifecycle vocabulary used by API, database, and UI. Separate entities have separate states; one generic `status` cannot describe the entire visit.

| Entity | States | Transition rules |
|---|---|---|
| Visit | open, closing, closed | Staff requests closure; lock visit, verify no unpaid balances/unresolved checkouts, revoke bindings on commit. Failure returns to open with reason. |
| GuestBill | open, checkout_locked, settled | Lock selected guest bill for checkout; cancel safely to reopen; successful full payment settles. Further ordering for settled guest requires staff reopen/new charge cycle with history. |
| OrderSubmission | awaiting_review, awaiting_payment, released, cancelled, exception | Table order can release immediately unless review needed. Kiosk requires approved review plus full payment and valid availability before release. |
| Charge | proposed, posted, cancelled | Proposals do not count as sales. Release posts charges; expired unpaid proposals cancel. Posted corrections use new adjustment records. |
| StationTask | queued, acknowledged, preparing, ready, served, cancelled | Only assigned station/staff may advance; cancelled work requires reason and permitted preparation state. |
| Checkout | open, payment_pending, paid, cancelled, expired, exception | Amount fixed; pending electronic attempt prevents blind cancel/reallocation. Query provider before releasing uncertainty. |
| PaymentAttempt | created, pending, succeeded, failed, unknown | Timeout alone means unknown, not proof of failure. Late success is reconciled against the checkout. |
| Refund | requested, approved, pending, succeeded, failed, unknown | Approval does not prove money returned. External outcome must be verified. |
| CashHandover | proposed, accepted, disputed | Cashier acknowledgment moves custody; disputed amount remains attributable. |
| FiscalDocument | queued, submitting, accepted, rejected, unknown | Submission timeout must be queried before retrying with a new identity. |
| PrintJob | queued, sending, sent, failed, unknown | Sent may mean handed to driver, not proof paper emerged. Reprint is a tracked copy. |

## Kiosk release guard

Release only if: all required reviews approved; verified/authorized recorded money covers the checkout amount; availability/stock reservation is valid; submission has not already been released/cancelled; and installation is authorised for live operation. Evaluate guards, post proposed charges, apply money, and create station tasks/outbox entries in one database transaction. Unfulfillable late-paid orders retain money in an unapplied exception for staff resolution.

## Table release guard

Require active visit, authorized guest binding, open bill, accepted current price/recipe, and availability. Table payment is not required before release. Review-required submissions wait for staff and do not appear as ordinary preparation tasks.

## Guest departure

A settled guest may leave while others remain under D02. Their binding is revoked or becomes read-only for a brief receipt view. Shared allocations involving them cannot be silently redistributed. Closing the table requires every bill to be settled or explicitly resolved through approved credits; no 'force paid' shortcut.

## Late events

- Success after UI timeout: update the original payment attempt; do not create a second payment.
- Success after checkout cancelled/expired or stock released: record money in exception state and route to cashier fulfil/refund review; do not prepare automatically.
- Duplicate callback: acknowledge and return existing processing outcome.
- Out-of-order event: compare authoritative record/version; never roll succeeded back to pending.
- Reconnection after visit closed: reject stale commands and clear local bindings.

## Orthogonal statuses

A table order may be preparing while unpaid. A kiosk order may be paid while waiting on a printer. A fully paid bill may still have a pending fiscal document. Staff interfaces must show these facts separately. The fiscal failure policy is configured with the chosen integrator before production; no assumption that offline tax submission is universally allowed.
