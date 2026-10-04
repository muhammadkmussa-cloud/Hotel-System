# Real-time updates and offline operation

## Authority

The on-site database is the sole authority for orders and balances. Tablets hold UI state, a published menu cache, and recoverable drafts. They cannot independently create an authoritative sale while disconnected from the hub.

Internet loss with a healthy local network is different from a tablet losing Wi-Fi or the hub failing. Only the first situation is targeted for continued normal local ordering under D05.

## Command and event flow

HTTP command carries authenticated scope, idempotency key, and expected resource version. Backend validates and commits records with an outbox event. Worker publishes to authorized SSE streams. Client updates its projections, or refetches on a detected gap. Event data is never a substitute for authorization on commands.

Each stream supports a resume cursor. Reconnect requests events after the last acknowledged cursor; expired replay range returns a signal to reload authoritative snapshots. Projected resources include versions, so duplicate and out-of-order events are safe to ignore. Polling is a fallback when the event channel fails.

## Caching policy

Cache versioned static assets and published menu media. Do not cache staff reports, card details, phone numbers, or authenticated financial responses in the service worker. Clear guest-specific IndexedDB/session state on reassignment, visit close, kiosk reset, and expiry. Keep locally preserved drafts bound to their session and invalidate them after closure.

Use HTTPS with a trusted certificate strategy on enrolled devices. Service workers require a secure context outside local development; simply serving a hub on an arbitrary LAN HTTP address is not a deployment plan. [MDN service-worker guidance](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API)

## Failure matrix

| Failure | Customer behaviour | Staff handling |
|---|---|---|
| Internet unavailable | Local table ordering/cash may continue; M-PESA verification unavailable | Show external services degraded; do not claim electronic success |
| Tablet cannot reach hub | Draft remains; no 'sent' confirmation | Reconnect or use another assigned device |
| Kitchen screen disconnects | Orders remain durable at hub | Printer may still work; alert on stale kitchen heartbeat |
| Printer unavailable | Display receipt/reference and kitchen work | Inspect printer; tracked copy, never new order |
| Hub unavailable | Ordering disabled with assistance message | Manual fallback and recovery procedure |
| Relay temporarily unavailable | Payment unknown/pending | Query through approved provider path after recovery |

Do not auto-submit old customer drafts on reconnect. A draft may be stale, priced differently, or no longer desired. Require explicit review and send. An already-submitted command with unknown response may be retried using its original idempotency key.

## Prototype proof

Use four tablets on one visit. Interrupt internet only, then one tablet's Wi-Fi, then printer connectivity. Verify expected differences. Demonstrate cursor recovery after hub restart, no duplicated bills, and the correct treatment of a payment success arriving after a kiosk reset.
