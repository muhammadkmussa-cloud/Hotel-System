# Test strategy

No application tests have been run in this documentation-only project. The scenarios below specify future evidence.

## Test layers

Domain tests: money allocation, rounding, state transitions, recipe composition, pricing snapshots, and permission predicates. Database integration tests: actual MySQL/InnoDB constraints/locking, idempotency, outbox/inbox and rollback. Contract tests: eventual OpenAPI request/response schemas and example fixtures. UI/component tests: accessible ingredient state, cart isolation, error rendering. End-to-end tests: multiple independent browser contexts against a real PHP/MySQL backend in an isolated test environment. Hardware/provider tests: actual printers, selected merchant sandbox, fiscal integrator sandbox, and controlled live pilot.

Proposed tools: PHPUnit with Laravel HTTP/testing facilities for PHP domain/integration tests and Playwright with JavaScript for DOM and browser journeys. Use real MySQL for locking/concurrency tests. Development tooling is not the production runtime.

## Mandatory cases

| Test ID | Scenario | Expected evidence |
|---|---|---|
| T01 | Four tablets submit for Table 7 concurrently | Four submissions/tickets; correct guests and one visit |
| T02 | Double tap, network retry, same idempotency key | One order/charge; original result returned |
| T03 | Reuse key with different body | Conflict, no new mutation |
| T04 | Meal price/recipe changes after item ordered | Original order unchanged; draft requote required |
| T05 | Remove fixed ingredient or alter server price field | Validation failure, no kitchen work |
| T06 | 3/8/20 ingredients and missing photos | Accessible labels, no overlap, honest fallback |
| T07 | KSh 2,400 platter shared four ways | One kitchen item, four KSh 600 charges |
| T08 | Indivisible shared amount | Exact allocation sum; supported payment precision enforced |
| T09 | Guest 1 checks out while Guest 2 orders | Guest 1 amount stable; Guest 2 accepted |
| T10 | Stale device submits after visit closure | Rejected; no new charge/task |
| T11 | Wrong guest/table ID, revoked session, cross-session event polling | Denied at backend without data leakage |
| T12 | Waiter tries external card confirmation | Denied; cashier succeeds with unique reference |
| T13 | Cash tender KSh 1,000 for KSh 850 bill | KSh 150 change, KSh 850 payment/custody |
| T14 | Cash handover and partial counted handover | Correct custody; sales unchanged |
| T15 | Duplicate or wrong-amount/merchant payment callback | One application or review exception, never blind settlement |
| T16 | Payment timeout then late success | Original attempt reconciled; no second order |
| T17 | Kiosk reset while payment pending | No personal-data leak; payment still recoverable |
| T18 | Unpaid cash/card kiosk reference | No preparation tasks until cashier confirms |
| T19 | Last portion ordered by two sessions | One allocation succeeds; other receives clear unavailability |
| T20 | Reservation expires before late payment | Staff exception/refund path, no unavailable auto-order |
| T21 | Paper out or unknown print delivery | Order retained; tracked copy; no new payment |
| T22 | Hotel internet cut versus device Wi-Fi cut versus DirectAdmin host failure | Distinct documented behaviours, no fake 'sent' state |
| T23 | Crash after commit before event/print | Durable recovery, no duplicate business application |
| T24 | Fiscal request timeout | Query same reference; no duplicate tax invoice |
| T25 | Refund after invoice/shared allocation | Original records retained, correct linked adjustments |
| T26 | Restore backup with external transactions after cutoff | Reconciliation before replay/reopening |
| T27 | 200% zoom, keyboard, reduced motion, long translated labels | Essential flows usable and readable |
| T28 | Shift/business-day boundary and report exports | Exact reconciliation and safe export fields |
| T29 | Ingredient/allergen review required | Staff hold; kiosk does not charge before approval |
| T30 | Financial UI manipulated outside browser | Server derives amount/scope, rejects unauthorized commands |
| T31 | Unpaid kiosk abandoned or allergy-reviewed order declined | Proposed charges excluded from sales; no fake revenue/cancellation loss |
| T32 | Staff reopens settled guest for another drink | Previous payment retained, only new charge becomes due |
| T33 | DirectAdmin HTTP/HTTPS roots, private folders, routing and upload paths | No secrets/SQL/log downloads or media script execution; deep links and callbacks work |
| T34 | Overlapping, delayed, or terminated cron runs | Expiring leases and stable identities recover safely; stale heartbeat visible |
| T35 | Polling load, cursor gap, session revocation, host throttling | Bounded authorized responses, snapshot recovery, measured latency and backoff |
| T36 | Bridge reconnects after printing but before result report | Unknown delivery requires inspection/copy; no duplicated sale or blind reprint |

## Load and performance

Use R-quality targets as starting load assumptions: representative large image menu, 60 device sessions, bursts of simultaneous submissions, print/fiscal jobs, and reporting while kitchen works. Report p50/p95/error rates, database locks, queue delay, and actual hardware. Payment-provider latency is measured separately from hosted application responsiveness.

## Release evidence

Store test results, redacted provider references, device/browser versions, physical printer outcomes, screenshots, accessibility findings, and unresolved issues. Financial/order/authorization tests must pass without exceptions. Do not hide flaky tests by repeating until green; investigate and record the cause.
