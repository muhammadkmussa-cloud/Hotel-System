# eTIMS and fiscal documents

Status: integration design with production validation gate D13. This file does not claim certification, prescribe tax rates, or establish the hotel's tax status.

KRA documents system-to-system eTIMS integration and a development/testing/certification process, including the option to use verified integrators. OSCU and VSCU have different operating assumptions. Select a route with the hotel/accountant and a currently verified integrator; do not choose based solely on a software label such as 'offline'. [KRA integration guidance](https://www.kra.go.ke/business/etims-electronic-tax-invoice-management-system/learn-about-etims/etims-system-to-system-integration)

## Document distinctions

Kitchen ticket: preparation instructions. Running bill: amount currently owed, not proof of payment. Payment receipt: record of received money. Fiscal invoice/credit note: required structured fiscal record with the approved integration's references. A single piece of paper may include multiple elements when supported, but the underlying records and states remain distinct.

## Required configuration

Hotel taxpayer identity and approved establishment details; item tax categories; price-includes-tax policy; applicable service charges and their treatment; item identifiers; invoice numbering rules; credit-note rules; rounding; buyer information when required; outage/fiscal submission policy; retention; sandbox and production credentials.

These are O02 launch evidence in [open decisions](../delivery/05-risks-decisions.md), not invented defaults. Display final customer prices transparently after configuration.

## Invoice boundaries

Track every charge allocation against exactly one active fiscal sale representation under the chosen policy. Separate guest bills and shared dish allocations need integrator validation so quantities, taxes, and totals remain consistent. Multiple payments against one invoice must not create multiple taxable sales. One whole-table payer likewise must not duplicate invoices already issued to individual guests.

Create immutable fiscal payload snapshots with stable request identifiers. On uncertain response, query by original reference before generating another invoice. Persist provider identifiers and acceptance/rejection evidence. Fiscal state does not falsely imply payment state, or vice versa.

## Corrections

A post-invoice cancellation/refund may need a linked credit note under the approved rules. Preserve original invoice and payment records. Do not delete and recreate history to make totals appear correct. A fiscal rejection creates an exception for authorized staff and does not erase the sale or the customer's payment.

## Release gate

Evidence must cover ordinary sale, individual guest bills, shared item, discounts, payment after invoice where applicable, refund/credit note, timeout/query/retry, provider outage, and date/business-day boundary. Establish when service may continue during fiscal outage with the chosen integrator and hotel's advisers. Do not enable live mode while these policies are undefined.
