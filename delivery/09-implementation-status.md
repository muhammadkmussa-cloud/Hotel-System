# Implementation status — October 2026

This file records what the application in [`hotel-app/`](../hotel-app/README.md) actually does today, how it was verified, and what is still outstanding. It supersedes the per-step tick counts in the README tracker for phases P08–P28: those phases were built as one continuous implementation pass at the owner's request (the reviewer-agent gate in the build plan was **waived by the owner** for this pass), so individual build-plan checkboxes were not ticked one by one.

## Summary

| Area | Phases | State |
|---|---|---|
| Foundation, setup, staff, settings, devices, visits | P00–P07 | Built (earlier reviewed work) |
| Media library: safe uploads, GD re-encode, metadata strip, crops | P08 | Built |
| Ingredient catalogue and meal editor (recipes, rules, extra prices, publish/version) | P09–P10 | Built |
| Customer table menu, ingredient customiser, cart with server quotes | P11–P12 | Built |
| Per-guest order submission, idempotency, review holds, availability/portions | P13–P14 | Built |
| Polling, durable background jobs, outbox, heartbeats | P15 | Built (`hotel:run-jobs`, cron) |
| Kitchen board, collection display, waiter service requests | P16 | Built |
| Print jobs and hotel-side bridge API (leases, retries) | P17 | Built — **no physical printer tested** |
| Guest bills, shared dishes (split proposals/approval) | P18 | Built |
| Checkout and payment ledger (cash, card record, M-PESA) | P19–P22 | Built — M-PESA runs in **simulator** mode; Daraja adapter present but **not tested against Safaricom** |
| Cash drawers, change, cancellations, discounts, refunds with approval | P20, P23 | Built |
| Walk-in kiosk with prepaid release (M-PESA or pay at cashier) | P24 | Built |
| Fiscal documents and credit notes | P25 | **Simulator only**, labelled “SIMULATED — not a tax invoice”; no eTIMS certification |
| Reports (sales, tax, payment methods, top items, drawer variance, open balances, CSV export), audit log, exceptions | P26 | Built |
| All staff/customer screens, accessibility states | P27 | Built and visually checked in headless Chromium |
| Backups (create, verify, rotate, restore) | P28 | Built (`hotel:backup`, `hotel:restore`) |
| DirectAdmin staging, physical installation | P29 | **Not done** — needs a real host, domain and hardware |
| End-to-end integrity and capacity gates on target stack | P30 | Partly: HTTP E2E suite passes on SQLite; **MySQL and load tests not run** |
| Staff rehearsal, pilot, release | P31 | **Not done** — requires a hotel |

The application has 155 routes, 27 migrations and eight staff roles (owner, manager, cashier, waiter, kitchen lead, kitchen staff, menu editor, auditor).

## Verification evidence (7 October 2026)

All of the following were executed in the development sandbox (PHP 8.4, SQLite standing in for MySQL):

- `php vendor/bin/phpunit` — **OK, 132 tests, 705 assertions**.
- `tests/e2e/run.sh` (resets the demo database, then drives the real HTTP app like a browser would):
  - `pages.py` — every staff, admin and device page (including reports, CSV exports, audit and operations) renders for the right role and is refused for the wrong one; admin forms (staff, roles, settings, tax, tables, stations, devices) work.
  - `flow_table.py` — open visit → add guests → bind tablets → per-guest menu, cart and order submission with idempotent replay → kitchen ticket transitions → bill → checkout → receipt → visit close.
  - `flow_money.py` — drawer open, allergy-note review hold and manager approval, shared-dish split, manager discount (waiter refused), cash with change and card record at the cashier, receipt with tax snapshot and simulated fiscal label, refund request → approve → complete, visit close blocked until food served, kiosk pay-at-cashier with collection number, kiosk M-PESA simulator (insufficient funds, then success), drawer close.
  - Result: **0 failures**.
- **Not yet covered by automated tests** (add before the pilot): M-PESA cancelled/timeout outcomes, item cancellation, print bridge leasing, media upload/crop.
- `php artisan hotel:run-jobs -v`, `hotel:backup` (51 tables verified), `hotel:restore` (dry run and `--force` with automatic safety backup), `schedule:list` — all succeed.
- Headless Chromium screenshots of sign-in, lock, pairing, welcome, staff home, tables, visit, kitchen board, collection display, cashier, checkout, receipt, refunds, all admin screens, the table tablet (menu, customiser, cart, bill) and the kiosk journey — no JavaScript errors.

## Design decisions made during implementation

- **Tax is inclusive** in menu prices. A blank tax rate means “not configured”; receipts then show no tax line. The rate and label are **snapshotted on each checkout when it is paid**, so later changes to the tax setting never alter old receipts, reports or fiscal documents.
- Guests are **never auto-settled**; a waiter closes the visit only after every guest balance is zero.
- Card payments are a staff-confirmed record of an external terminal transaction (reference required); the system never talks to the terminal.
- Kiosk orders reach the kitchen only after payment is confirmed (M-PESA) or a cashier takes payment.
- Allergy notes are passed to the kitchen and shown on tickets, but the UI never claims a dish is allergy-safe.
- Demo data and test-mode receipts are clearly labelled “TEST MODE”.

## Known gaps and risks before a real pilot

1. **MySQL**: all features were exercised on SQLite. The MySQL-only suites in `tests/Database/` and the full E2E suite must be run against MySQL 8+ on the target host before go-live.
2. **M-PESA**: switch `MPESA_MODE=daraja` only after Safaricom sandbox credentials are issued and the callback URL is reachable over HTTPS; test every result code.
3. **Fiscal/eTIMS**: the simulator must be replaced by a certified integration (or an approved VSCU/OSCU device) before issuing real tax invoices. Keep `FISCAL_ENABLED=false` until then.
4. **Printers**: the bridge API is implemented; a hotel-side bridge program and real receipt/kitchen printers still need to be installed and tested (P17/P29).
5. **Load**: polling intervals and request limits were sized for one hotel; run a capacity test on the real host (P30).
6. **Food photography**: demo meals use placeholder imagery; production photos must be uploaded through the media library.

## How to run it

See [hotel-app/README.md → Run the demo](../hotel-app/README.md#run-the-demo).
