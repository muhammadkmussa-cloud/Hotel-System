# Events and external delivery

Live events are notifications of committed state. Subscribers must refetch on gaps. No event grants permission or authorizes an external money movement.

| Event | Audience | Minimal payload |
|---|---|---|
| menu.published | Enrolled devices | publication ID/version |
| availability.changed | Customer/staff menu sessions | meal ID, sellable flag, version |
| order.accepted | Owner guest, assigned waiter | order ID, state/version |
| order.review-required | Authorized staff; owner sees generic hold | order/review IDs |
| kitchen.task-changed | Assigned kitchen and waiter | task ID, state/version |
| bill.changed | Own guest, authorized cashier/waiter | bill ID/version, total/due |
| share.proposed | Affected guests and staff | proposal ID, own amount, state |
| payment.changed | Owning checkout session and cashier | attempt ID, redacted state |
| visit.closed | Visit devices and assigned staff | visit ID, revocation version |
| cash.handover-changed | Relevant staff/cashier | handover ID/state |
| print.job-changed | Relevant staff | job ID/destination/state |
| fiscal.document-changed | Fiscal-authorized staff | document ID/state |
| collection.changed | Public display projection | display number/state, approved optional name |

## Envelope

```json
{
  "eventId": "evt_demo_01",
  "type": "bill.changed",
  "occurredAt": "2026-10-04T09:00:00Z",
  "resourceId": "bill_guest2_demo",
  "resourceVersion": 6,
  "data": {"dueMinor": 85000, "currency": "KES"}
}
```

Event polling responses are filtered server-side by authenticated principal and currently valid bindings. Do not broadcast all bills and rely on browser filtering. Each request rechecks revocation. Kiosk screen reset clears the previous checkout cursor and access; staff can still reconcile the payment.

`GET /api/v1/events` returns immediately with a bounded event list and next cursor. Read committed events directly without waiting for cron. An expired cursor signals an authoritative snapshot reload. Proposed active polling is 2–5 seconds with jitter/backoff; prove capacity on the DirectAdmin host.

## Outbox/inbox discipline

Persist outbox event in the business transaction. Bounded cron jobs may deliver external work more than once; consumers deduplicate by stable event ID/resource version. Store external callback evidence durably before acknowledgment when the provider protocol allows. Apply money only after verification. Redact payloads in general logs and restrict raw provider evidence retention/access.

Retry queues expose age and last error to operations. A dead-letter item remains actionable and cannot be silently deleted by routine cleanup. Recovery reuses original IDs; it never creates a fresh order merely to resend a kitchen notification.
