# Conversation decision register

Reviewed against the conversation through 4 October 2026. This file prevents recommendations from being mistaken for user approval.

## Confirmed

| ID | Decision | Conversation basis |
|---|---|---|
| C01 | Build for one hotel, with separate installations at other hotels | Explicit correction of the earlier shared-platform assumption |
| C02 | First operating market is Kenya | Explicit answer |
| C03 | Include cashier/POS, billing, and sales records | Explicit answer |
| C04 | Waiters carry and assign tablets; several tablets can join one table | Table 7 and family examples |
| C05 | Distinguish guests within a table | Guest 1–4 ticket and payment examples |
| C06 | Guests submit independently; separate ticket per submission, labelled table and guest | Latest explicit ordering decision |
| C07 | Additional food/drinks may be added while the guest/table bill remains open | Explicit answer |
| C08 | Table guests pay after eating and see their bill on the system | Explicit answer |
| C09 | Allow cash, M-PESA, and card | Cash explicitly restored in the latest payment clarification |
| C10 | Cashier confirms external card-terminal payment; terminal is not connected | Explicit confirmation |
| C11 | Waiter records cash received and hands cash to cashier | Described cash workflow |
| C12 | Waiter closes the visit after settlement | Explicit payment/closure description |
| C13 | Different guests can pay for their own food using different methods | Explicit explanation of individual payments |
| C14 | Kiosk supports eat-in/takeaway, customer name, payment before kitchen release, order number, and printed receipt | Original request, retained throughout |
| C15 | Reusable ingredient/photo library; meals select from it and have their own photos | Original request |
| C16 | Central meal photo with surrounding ingredient pictures; customers remove permitted ingredients | Original request; feasibility refinements below remain defaults |
| C17 | Premium, image-led frontend and comprehensive Markdown build documentation | Current request |
| C18 | Save a project named Hotel System to Desktop; no zip | Current request supersedes the initial zip request |
| C19 | Use PHP for the backend | Explicit technology choice on 4 October 2026 |
| C20 | Use basic HTML, CSS, and JavaScript for the frontend | Explicit technology choice on 4 October 2026 |
| C21 | Use MySQL for the database | Explicit technology choice on 4 October 2026 |
| C22 | Plan the technologies and integrations and update the documentation before creating code | Explicit documentation-only instruction on 4 October 2026 |
| C23 | Deploy using DirectAdmin and organise the architecture and file layout for that hosting environment | User clarified DirectAdmin after initially naming cPanel on 4 October 2026 |
| C24 | Begin local build work in small verified steps, with independent step and phase reviews | User requested the reviewer workflow and then said “start” on 4 October 2026; C22 planning prerequisite is fulfilled |
| C25 | Use the latest stable Laravel and MySQL releases as the implementation targets | User chose latest Laravel/MySQL on 4 October 2026; this is a version preference, not evidence of installed hosting versions |

## Proposed defaults

These guide the plan but are not additional confirmed user decisions.

| ID | Default | Why / boundary |
|---|---|---|
| D01 | Shared dishes initially charged to ordering guest; optionally divided equally among selected guests | User requested a recommendation but did not explicitly approve the detailed rule |
| D02 | Allow individual guest checkout while others keep ordering | Prevents one payment freezing the entire table |
| D03 | Cash settles the customer bill immediately; separately track waiter-to-cashier handover | Protects both customer experience and cash accountability |
| D04 | Direct Safaricom Daraja for first M-PESA adapter | Research recommendation; Paystack remains an alternative pending onboarding validation |
| D05 | Superseded by C23 and D17 | The on-site authoritative hub and optional relay recommendation is retired; hosted ordering requires connectivity |
| D06 | Kiosk cash/card payments happen at cashier; pending kiosk orders cannot reach preparation | Consistent with independent card terminal |
| D07 | Kiosk eat-in uses counter collection initially | Table delivery was discussed but not decided |
| D08 | Kitchen screen plus separate printed tickets, optional bar routing | Paper is confirmed; screen and stations are recommended |
| D09 | Chef-reviewed removable ingredients and staff handling of allergy declarations | Product safety design; chef must validate actual recipes |
| D10 | Fixed minimum touch size and overflow ingredient list; no indefinitely shrinking circles | Makes the requested visual design usable |
| D11 | English and Kiswahili interface; English initial authoring with reviewed translations | Languages were asked about, not answered |
| D12 | Basic availability/portion limits first; full recipe inventory later | Keeps first release focused |
| D13 | eTIMS via a verified integrator, after fiscal workflow validation | Provider and hotel tax configuration are not yet selected |
| D14 | Superseded by C19–C21; see the technology fact file | The previous React/Vite, Fastify, PostgreSQL, TypeScript recommendation is retired |
| D15 | Manager approval for post-submission cancellations, discounts, and refunds | Authority and thresholds require hotel configuration |
| D16 | Laravel modular application, Composer, MySQL InnoDB through Laravel database/PDO, native browser modules, and supporting tools in the fact file | The framework-free recommendation is superseded by C25; remaining supporting recommendations require compatibility validation |
| D17 | DirectAdmin-compatible hosted application with private/public file separation, short polling, bounded PHP cron jobs, and hotel-side print bridge | Shared-host constraints are a planning assumption, not a confirmed hosting tier; validate O13 |

## Explicitly superseded or excluded

- Shared multi-hotel SaaS, central owner registration, and subscription management: removed after C01.
- One family member submits everybody's food: superseded by C06. Do not retain group submission as the default.
- M-PESA/card only: superseded by C09, which restores cash.
- Integrated card reader: excluded by C10.
- Freeze the whole table whenever any guest pays: replaced by proposed guest-level checkout D02.
- Every ingredient always removable: infeasible as a blanket rule; D09 requires per-meal preparation constraints.
- Downloadable zip: superseded by C18.
- On-site server/default offline ordering D05 and the initial cPanel reference: superseded by DirectAdmin C23; no offline authoritative server is included.
- Framework-free PHP default in D16: superseded by Laravel C25; the plain HTML/CSS/JavaScript frontend remains C20.
- Previous technical baseline D14: superseded by confirmed PHP, HTML/CSS/JavaScript, and MySQL choices C19–C21.

## Remaining decisions

See the [technology and integration fact file](../TECHNOLOGY-FACT-FILE.md) for the complete stack plan and [risk and decision gates](../delivery/05-risks-decisions.md) for validation. Unresolved items include supporting runtime/tool versions, payment merchant onboarding, actual recipes and photos, taxes/invoice boundaries, installed hardware, verified languages, commercial software terms, and local recovery requirements. C22 documentation preparation is complete and C24 starts the reviewed local build plan. Live deployment/account changes remain separately authorized actions.
