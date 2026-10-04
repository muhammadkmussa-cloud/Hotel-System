# Backend modules and ownership

Use a modular Laravel/PHP application with explicit interfaces, Laravel database access through PDO MySQL, and the [DirectAdmin layout](../architecture/05-directadmin-layout.md). Laravel routes/controllers and Form Requests authenticate/validate and call domain actions/services; use cases own domain transactions; repositories persist data; workers perform external side effects.

| Module | Owns | Does not own |
|---|---|---|
| Identity | Staff, roles, sessions, device enrolment, guest binding | Prices or payment state |
| Catalogue | Ingredients, composition, meal versions, media, publication | Historical order mutation |
| Availability | Sellable state and optional portion reservations | Full ingredient purchasing in v1 |
| Visits | Tables, guest membership, waiter assignment, close/reopen guards | Electronic verification |
| Ordering | Validated submissions, snapshots, review requests | Marking external money received |
| Billing | Charges, allocations, share proposals, checkouts, balances | Card-terminal communication |
| Payments | Attempts, verified/manual records, allocation, refunds | Kitchen preparation state |
| Cash | Custody, handovers, drawers, shift differences | Creating another sale on handover |
| Fulfilment | Station tasks, release conditions, progress, collection | Fiscal acceptance |
| Printing | Immutable payload jobs, printer routing, copies | Financial settlement |
| Fiscal | eTIMS adapter, invoices, credit notes, exceptions | Guessing tax law or deleting sales |
| Reporting | Scoped read models and reconciliation | Editing posted financial history |
| Audit | Immutable security/business actions | Storing full secrets or unnecessary personal details |
| Delivery | Inbox/outbox, retries, dead-letter alerts, scoped live events | Bypassing domain guards |

## Transaction boundaries

Ordering coordinates availability, bill charges, and station release atomically where necessary. Payments coordinates one verified payment record and its allowed allocations. Domain operations can span modules inside a single database transaction; modularity is not a requirement for separate databases or microservices.

Never hold a database lock while waiting on a provider or printer. Commit intent first, perform external request with stable identity, then commit the verified outcome. Unknown outcomes are first-class states requiring reconciliation.

## Background operations

DirectAdmin cron launches bounded PHP batches; no persistent daemon is assumed. Record heartbeat, prevent overlapping execution from duplicating work, and stop within host limits. Jobs are leased with expiry, attempt counts, exponential backoff, jitter, and bounded retries. Exhausted attempts raise a staff-visible exception and remain recoverable. Job retry must retain the business/provider idempotency identity. Print jobs with unknown physical results require inspection or explicitly labelled copies, not endless automatic resend.

## Diagnostics

Use structured logs with request, installation, order, checkout, job, and correlation IDs. Mask phone numbers; never log tokens, provider credentials, card data, or raw allergy notes in general logs. Expose authenticated operational health and a minimal unauthenticated liveness endpoint without business data.
