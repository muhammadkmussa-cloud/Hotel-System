# Acceptance and release checklist

Status of every item: **not yet tested**, because this delivery is documentation only. Fill evidence and reviewer during implementation.

| Gate | Pass condition | Evidence owner |
|---|---|---|
| Scope | C01–C18 represented; proposed defaults separately identified | Product lead |
| Premium design | Real dish/ingredient photography; clear hierarchy; readable orbit/grid on actual hardware; no generic landing page | Designer + hotel |
| Customer ordering | Independent guests, correct table/guest labels, additions and reversible permitted removals | QA + waiter lead |
| Kitchen | Distinct tickets, visible exclusions, station status, printer recovery | QA + kitchen lead |
| Billing | Shared items preserve quantity/total; individual checkout; exact due balances | Backend + cashier lead |
| Payments | Cash/card/M-PESA tests including unknown/late/duplicate cases | Integration + cashier lead |
| Permissions | Negative RBAC/session tests pass; customer cannot confirm money | Security reviewer |
| Privacy | Reset/reassignment clears local details; public queue redacted | QA + hotel owner |
| Fiscal | Configured invoice/credit-note rules and verified integration evidence | Integrator + hotel accountant |
| Resilience | Tested internet/Wi-Fi/hosting/cron/print-bridge failures with truthful UI | Operations + QA |
| Recovery | Independent off-account backup restored; provider reconciliation before replay | Operations |
| Reports | Known fixture and pilot day totals reconcile | Cashier + accountant |
| Accessibility | Touch, keyboard, zoom, contrast, screen-reader critical flows checked | Designer + QA |
| Deployment | Pinned versions, safe secrets, TLS, role setup, update/rollback | Operations |
| Training | Staff can complete service and resolve common exceptions | Hotel manager |

## Visual evidence to capture

Menu at tablet portrait/landscape; meal with 3 and 20 ingredients; removed/fixed ingredient states; guest cart with two different versions of same meal; eight-guest cashier view; pending M-PESA; kiosk confirmation; unpaid cashier slip; kitchen ticket; missing-image and offline screens. Screenshots must use demo data and avoid real phones or sensitive notes.

## Blocking defects

Wrong table/guest, lost or duplicated order, incorrect bill, false payment success, unauthorised access, unsupported allergy claim, duplicate fiscal submission, lost backup/recovery capability, or a customer flow impossible to complete on installed hardware. Visual issues that obscure a price, ingredient, or action are also blockers.

## Pilot protocol

Start with controlled staff rehearsals and demo orders. Perform a supervised limited service only after live merchant/fiscal/hardware gates pass. Keep a clear manual fallback and named incident owner. Expand device/table count after reviewing actual service results; do not buy a full fleet based solely on mockups.

## Sign-off record

Record release version, date, installation, required gate evidence, remaining non-blocking issues, approved scope exceptions, reviewer identities, and rollback contact. Unresolved validation items are explicit; do not mark them passed because a deadline arrived.

## DirectAdmin hosting gate

Before release, prove the configured HTTP/HTTPS document roots, private PHP/configuration isolation, real MySQL service, host-compatible routing, bounded cron recovery, authorized polling capacity, and outbound hotel print-bridge operation. Record actual versions/quotas and off-account restore evidence under O13. Hosting-panel access alone does not pass this gate.
