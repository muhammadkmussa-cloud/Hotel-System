# Technology and integration fact file

Documentation version 1.1 · 4 October 2026. This is the central technology plan. The [decision register](product/01-decisions.md) records approval status; the detailed specifications define behaviour. No application code, installation, or integration activation is included in this update.

## Confirmed technology choices

| Area | We will use | Responsibility | Decision |
|---|---|---|---|
| Backend | PHP | Authentication, permissions, menu rules, orders, bills, payments, reports, and integration processing | C19 |
| Frontend | Basic HTML, CSS, and JavaScript | All tablet, kiosk, cashier, waiter, kitchen, collection, and administration screens | C20 |
| Database | MySQL | Authoritative hotel records, transactions, financial history, and durable jobs | C21 |
| Hosting | DirectAdmin | Domain, public/private file layout, PHP/MySQL hosting, HTTPS, and scheduled jobs | C23 |
| Current phase | Documentation and planning only | Record the stack and integration plan before creating code | C22 |

These choices replace the earlier D14 recommendation. DirectAdmin supersedes the earlier on-site hub proposal D05 and the briefly requested cPanel target. One hotel continues to have its own isolated installation, database, media, credentials, and backups (C01).

## Recommended supporting tools

Everything in this section is a **proposed default (D16/D17)**, not an additional user-confirmed choice. Use the smallest set needed for the first release. Select and lock compatible supported versions in M1; versioned documentation links below do not pin the application version.

| Area | Recommendation | Purpose and boundary |
|---|---|---|
| PHP structure | One modular application; no full PHP framework initially | Separate request handling, business rules, database access, and provider adapters. Reuse maintained libraries for infrastructure; a framework such as Laravel is not selected. |
| PHP dependencies | Composer with a committed lockfile | Dependency management and autoloading; review the exact libraries in M1. |
| Database connection | PDO with the MySQL driver and prepared statements | Keep SQL and credentials on the backend; validate requests before database access. |
| Database configuration | MySQL with InnoDB, foreign keys, unique constraints, indexes, and utf8mb4 | Transactions and row locking for concurrent tablets, bills, and jobs. No separate cache database or message broker initially. |
| Frontend structure | Semantic HTML, shared plain CSS, native JavaScript modules | Reusable DOM components, CSS Grid/Flexbox, and responsive layouts; no frontend framework or mandatory bundler. |
| Browser/API communication | Same-origin HTTPS, JSON endpoints, native Fetch API | PHP validates all commands. Create OpenAPI schemas in M1 and generate a JavaScript-compatible client contract; JSDoc can provide editor hints without changing the frontend language. |
| Live updates | Short authenticated JSON polling with cursors | Read committed order/kitchen events directly; validate the proposed 2–5 second cadence under hosting limits. Persistent SSE/WebSocket processes are not required. |
| Background jobs | Bounded PHP CLI cron jobs using MySQL inbox/outbox records | Recover payment/fiscal work after commits; lease jobs, prevent overlap, and retain retry identities. Printer delivery uses a separate hotel-side bridge. |
| Hosting runtime | Provider-managed PHP and MySQL on DirectAdmin | Confirm the actual web server, PHP handler, extensions, HTTPS, and quotas. No root access or custom server installation is assumed. |
| Job scheduling | DirectAdmin Cron Jobs | Run short batches using the verified PHP CLI path and allowed frequency. Monitor late runs; do not rely on an open browser for recovery. |
| External HTTP requests | PHP cURL behind provider adapters | Server-side calls with TLS verification, bounded timeouts, and provider-specific retry rules. |
| Food images | Hosting-account storage and PHP GD for approved raster variants | Re-encode and resize hotel-approved images; verify format support and resource limits. Keep originals private and serve published derivatives from the domain. |
| Staff/device access | Server-managed sessions, secure cookies, CSRF protection, role checks | Preserve separate staff and guest privileges; use PHP password hashing APIs for staff passwords. |
| Backups | Consistent MySQL and media backups to an encrypted off-account destination | Binary-log recovery depends on provider access. Rehearse restores and agree achievable recovery targets under O10/O13; panel backup availability alone is insufficient. |
| Diagnostics | Private structured logs and staff-visible operational health | Track failed jobs, uncertain payments, printers, and backup age; redact personal data and secrets. |
| Testing tools | PHPUnit for PHP; Playwright with JavaScript for browser journeys | Use real MySQL for database tests. Node.js/npm may be development/test tools for Playwright or contract generation; they are not the application backend. |
| Version control and checks | Git, dependency lockfiles, automated documentation/contract/test checks | Keep release changes reviewable. Remote repository and CI hosting are not selected by this plan. |

The supporting choices are engineering recommendations based on the existing requirements. Official references: [Composer](https://getcomposer.org/doc/00-intro.md), [PHP PDO](https://www.php.net/manual/en/book.pdo.php), [MySQL InnoDB](https://dev.mysql.com/doc/refman/8.4/en/innodb-introduction.html), [JavaScript modules](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules), [PHP cURL](https://www.php.net/manual/en/book.curl.php), [PHP GD](https://www.php.net/manual/en/book.image.php), [MySQL recovery](https://dev.mysql.com/doc/refman/8.4/en/point-in-time-recovery.html), [PHPUnit](https://phpunit.de/documentation.html), and [Playwright](https://playwright.dev/docs/intro).

## Integration plan

This consolidates the existing integration scope. A confirmed payment method does not mean its provider, account, or hardware has been selected or connected.

| Integration | Status | Planned connection and required evidence |
|---|---|---|
| M-PESA | Method confirmed C09; Daraja proposed D04 | PHP adapter for Safaricom Daraja phone prompts, durable callback evidence, and verified reconciliation. Validate merchant onboarding, amount precision, status/refund capabilities, and sandbox results. Paystack remains an alternative, not a second default integration. See [payments](integrations/01-payments.md). |
| Cash | Confirmed C09/C11; custody policy D03 proposed | Internal payment and handover records; no external payment API. Cashier acceptance of handover must not create another sale. |
| Card terminal | Confirmed C10 | Separate physical terminal; cashier records confirmed amount/reference. No terminal API, card-number storage, or browser confirmation of money received. |
| Payment callbacks | Hosted PHP endpoint proposed D17 | Receive callbacks directly at the hotel domain over HTTPS; persist and verify provider evidence before applying money. No separate relay is required. |
| eTIMS | Proposed D13; validation required O02 | PHP adapter to the route agreed with the hotel/accountant and a verified integrator. Select provider, fiscal rules, credentials, and outage policy before live use. This plan adds no new tax or certification claim. See [fiscal specification](integrations/02-etims.md). |
| Kitchen and receipt printers | Printed outputs in scope; transport/models require O04 validation | Durable hosted jobs are retrieved through outbound HTTPS by a restricted hotel-side print bridge. Its runtime and driver/ESC/POS transport remain dependent on actual hardware; the host cannot directly address private LAN printers. Preserve unknown-delivery and labelled-copy handling. See [printing](integrations/03-printing-hardware.md). |
| Kitchen and collection screens | Proposed D08 | Internal web screens using scoped JSON polling; no external display subscription. Public collection views omit private guest/payment data. |
| Menu photographs | Confirmed C15; content validation O03 | Upload original hotel-approved meals/ingredients to hosting-account storage. Demo images stay labelled; no stock-photo or AI-image service is selected. |
| Backup destination | Required operational plan; location undecided O10 | Encrypted off-account copies, monitored completion, and isolated restore rehearsal. No cloud storage account has been selected. |

Email, SMS, WhatsApp, delivery marketplaces, loyalty services, reservations, and a shared multi-hotel portal are outside the first-release integration plan. Add them only through a separate scope decision.

## Equipment and deployment plan

Retain the proposed pilot: four table tablets, portrait kiosk, cashier workstation, kitchen display, receipt and kitchen printers, a hotel-side print-bridge device, reliable internet and Wi-Fi/router, power backup, and an off-account backup destination. A collection display can use pilot equipment. Exact models, operating systems, network coverage, and printer compatibility require physical validation before purchase. The external card terminal remains independent.

Deploy public files to the DirectAdmin domain web root and private application files to its non-public sibling directory, following the [DirectAdmin layout](architecture/05-directadmin-layout.md). Hosting tier is unconfirmed; ordinary shared-host constraints are the planning assumption. Internet or host loss prevents new confirmed electronic orders and payment records. A disconnected tablet retains a draft only; manual fallback requires later reconciliation. See [offline behaviour](architecture/04-realtime-offline.md).

## Planning and implementation order

1. **This documentation update:** record C19–C23, retire D14/D05, align the architecture and file blueprints, and keep supporting choices visibly proposed.
2. **M1, when implementation begins:** lock PHP/MySQL/tool versions, choose reviewed PHP libraries and the contract generator, validate InnoDB concurrency, DirectAdmin polling capacity, HTTPS, cron recovery, private-path access, and backup capability. Check merchant/fiscal/printer feasibility using the existing decision gates.
3. **M2–M4:** build the HTML/CSS/JavaScript prototype, then the PHP/MySQL catalogue, identity, and independent guest-ordering slice.
4. **M5–M8:** add tested printing, billing/cash/card records, the selected M-PESA adapter, kiosk release rules, fiscal integration, and recovery tooling.
5. **M9:** rehearse the physical installation and staff workflows; collect release evidence before live operation.

The detailed [build plan](delivery/01-build-plan.md) remains authoritative for dependencies. Supporting tool approval/validation, provider onboarding, fiscal configuration, hardware, backup location, and operational policies remain visible in [open decisions](delivery/05-risks-decisions.md). This plan does not start implementation or authorize external purchases, accounts, or deployments.
