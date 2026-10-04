# Installation and configuration runbook

Status: planned process; no services are installed by this documentation project. Complete [setup form](../templates/hotel-setup.md) and [release acceptance](../delivery/04-acceptance.md) before live use.

## Per-hotel package

Same versioned software, separate database/media/credentials/device enrolments/backups. Branding, recipes, table numbers, stations, taxes, and printer destinations are configuration. Do not copy one hotel's private data into another installation as a starting template.

## Setup sequence

1. Survey network coverage, electrical reliability, device mounting, printer positions, and cashier workflow.
2. Validate hub specification under representative load. Configure power backup and secure OS accounts.
3. Install pinned, supported application/database/proxy/worker releases. Use dedicated service accounts and restricted file permissions.
4. Establish HTTPS/trusted certificates for local hostnames and enrolled tablets. Test certificate renewal before expiry.
5. Initialize the database, installation ID, first owner through a one-time bootstrap secret, and backup destination. Remove bootstrap access.
6. Configure hotel identity, KES, Africa/Nairobi, business-day cutoff, table labels, staff roles, station routing, and printers.
7. Enrol devices; verify staff/customer mode isolation and revocation.
8. Load chef-approved recipes, real photos, prices, translations, and availability. Preview customer presentation.
9. Configure payment and fiscal integrations in sandbox only. Finish integration evidence before production credentials are enabled.
10. Run rehearsal: table orders, kiosk orders, guest settlement, cash handover, invoice, refund, printer failure, and restore.
11. Train staff; obtain operational sign-off and switch explicitly to live mode. Never silently mix sandbox results with real receipts.

## Configuration categories

Runtime settings: bind address, public/local origin, database connection, storage location, installation ID, TLS, timeouts, queue leases, logging, and backup scheduling. Provider secrets remain outside UI/source; UI shows only configured status and redacted identifiers.

Business settings: meal prices, taxes, supported language, guest checkout policy, terminal reference rules, table list, staff assignments, print routing, receipt details, service charges, kiosk expiry policy, and review permissions. Version sensitive settings and record who changed them.

## Updates

Announce maintenance to staff, complete/transfer active work, back up and test backup integrity, apply reviewed migrations, deploy pinned assets, run smoke tests, then resume. Do not refresh tablets mid-checkout. Service worker updates wait for an idle/reload-safe moment.

Rollback must account for database compatibility. Never restore an older database over newer live payments without reconciliation. If a migration is incompatible, use the tested forward repair or controlled maintenance recovery plan.
