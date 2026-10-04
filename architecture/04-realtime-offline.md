# Updates, reconnection, and offline behaviour

## Authority

The MySQL database on DirectAdmin is the sole authority for orders and balances (C21/C23). Tablets retain UI state, permitted published assets, and session-bound drafts only. The previous on-site ordering-continuity proposal D05 is retired. Losing internet or access to the hosted application prevents new confirmed electronic orders, bill mutations, and cash recording in the system.

## Command and event flow

HTTP commands carry authenticated scope, idempotency keys, and expected versions. PHP validates and commits business records with outbox events. Short authenticated requests to `/api/v1/events` read committed events with a cursor and bounded page size; they do not wait for cron. The client refreshes affected authoritative resources. Suggested active-screen polling is every 2–5 seconds, with jitter, backoff on failures, and reduced activity on idle screens, subject to DirectAdmin load tests (D17).

Each response returns a next cursor and resource versions. Reconnect resumes from that cursor; an expired replay range requires fresh snapshots. Duplicate/out-of-order notifications cannot duplicate business actions. Recheck scope and revocation on every request. SSE or WebSockets require a later host-capability decision and are not first-release dependencies.

## Caching and recovery

A service worker may cache versioned public assets and published menu media over HTTPS. It must not cache private API responses, reports, phone numbers, or payment state. Clear guest-specific browser storage on reassignment, visit closure, kiosk reset, and expiry. Offline cached content shows a disconnected/stale state; it is not current availability or a live bill.

Do not auto-submit drafts on reconnect. Ask the guest to review and send. For an already-submitted command with an unknown result, query/retry using its original idempotency key. The payment provider may complete a transaction while a tablet or the hotel's internet is offline; cashier reconciliation uses the original attempt.

## Failure matrix

| Failure | Customer behaviour | Staff handling |
|---|---|---|
| Hotel internet unavailable | Cached draft/menu only; no confirmed submission or settlement | Manual incident process; restore connectivity and reconcile |
| One tablet loses Wi-Fi | Own draft remains; no sent confirmation | Other connected devices can continue; reassignment is staff-controlled |
| DirectAdmin application/database unavailable | Ordering/payment commands unavailable | Contact host, restore service, reconcile uncertain requests |
| Kitchen screen disconnects | Hosted orders remain durable | Alert stale screen; bridge may still print if independently connected |
| Printer/bridge unavailable | Order/reference remains visible online | Inspect bridge/printer; labelled copy when needed |
| Payment/fiscal provider unavailable | Local browser can reach app but external outcome is pending/unknown | Reconcile using approved provider procedure; other permitted methods follow payment guards |
| Cron late or stopped | Already committed orders remain visible | Alert stale heartbeat/backlog; resume leased jobs safely |

## Prototype proof

Use four tablets on one visit. Test hotel internet loss, one device's Wi-Fi loss, host failure, delayed cron, and print-bridge disconnection separately. Prove cursor recovery, private session clearing, no duplicate charges, and correct delayed payment handling after a kiosk reset. Validate measured polling/printing delays on the selected hosting plan.
