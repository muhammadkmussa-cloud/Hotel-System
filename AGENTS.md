# Instructions for future implementation

Read [README](README.md), [decisions](product/01-decisions.md), [requirements](product/02-requirements.md), and the relevant surface specification before editing.

Use [developer navigation](docs/CODEX-NAVIGATION-GUIDE.md) for ownership and future review packets.

Local scaffolding has started under hotel-app/. Report only verified implementation; the business application is not built yet. Keep proposed defaults visibly distinct from confirmed requirements. User instructions remain authoritative.

## Product constraints

- One hotel per isolated installation. Do not introduce a shared SaaS tenancy model, subscriptions portal, room reservations, or cross-hotel data sharing without a scope change.
- Preserve independent per-guest submissions and distinct kitchen tickets.
- The backend owns prices, availability, financial balances, permission decisions, and payment state.
- Customer screens cannot select arbitrary table/guest IDs or confirm money received.
- Keep card terminal payments external and cashier-confirmed in the first version.
- Do not convert an ingredient removal into an allergy-safe claim.
- Use original, hotel-approved meal and ingredient images. Demo assets must be labelled and not represented as actual menu photography.

## Implementation discipline

Follow [build plan](delivery/01-build-plan.md). Lock compatible dependency versions at implementation start. Generate client contracts from the eventual machine-readable API schema; these Markdown contracts are the starting specification, not generated OpenAPI.

Use database transactions for order submission, bill allocation, payment application, and closure. Use durable inbox/outbox records and idempotency for external side effects. Do not promise exactly-once physical printing.

Test the financial and multi-tablet invariants in [test strategy](delivery/03-testing.md). Review changes for authorization boundaries, retries, and privacy. Keep examples free of real credentials or customer records. Do not add deployments, paid services, account changes, or external messages beyond the user's authorization.

Keep documentation in step with behaviour. Update the decision register, endpoints, tests, and release notes together when a rule changes. A premium UI must satisfy [design](design/01-premium-design.md) and [accessibility](design/04-accessibility.md), not merely use decorative colours.
