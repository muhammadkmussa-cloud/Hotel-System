# DirectAdmin installation and configuration runbook

Status: planned process only; nothing is deployed. PHP, HTML/CSS/JavaScript, MySQL, and DirectAdmin are confirmed. Use the [deployment layout](../architecture/05-directadmin-layout.md), [setup form](../templates/hotel-setup.md), and [release acceptance](../delivery/04-acceptance.md).

## Per-hotel package

Each hotel has isolated domain/application files, database/user, media, credentials, devices, and backups; a separate DirectAdmin account is preferred. Same versioned software, hotel-specific configuration. Never copy another hotel's private records as setup data.

## Setup sequence

1. Record provider/account capabilities O13: actual MySQL/PHP versions, extensions, web server, routing, cron, quotas, private-path permissions, outbound HTTPS, and backup/restore access. Confirm that the chosen plan can support the expected load.
2. Configure the domain and trusted HTTPS with renewal. Verify both public_html/private_html mappings. Neither may expose private application files; redirect HTTP to HTTPS.
3. Prepare a release with locked PHP dependencies matching the host. Use Composer locally if host shell/Composer access is unavailable. Deploy hotel-app outside the public roots and only the public entry/assets to the domain root. Exclude tests, Git metadata, secrets, SQL exports, and developer files from public upload.
4. Create an isolated MySQL database/user through the permitted host tools, using actual prefixed names and least-privilege access. Store credentials in private configuration. Apply reviewed migrations through the controlled administrative process and disable setup access after bootstrap.
5. Configure DirectAdmin cron with the verified PHP CLI path, absolute private job-runner path, bounded runs, leases, and overlap protection. Check heartbeat/error reporting without leaking secrets in job output or email.
6. Set installation identity, KES, Africa/Nairobi, business-day cutoff, table labels, roles, tax configuration, receipt settings, and session/storage paths. Validate database and media backup/restore to the approved independent destination.
7. Install the tested print bridge on the hotel-side device. Enrol it with narrowly scoped credentials; verify outbound HTTPS and local printer allowlists. No printer port forwarding is required.
8. Enrol tablets/kiosk/staff/displays; verify session isolation, internet/Wi-Fi coverage, disconnection handling, and revocation.
9. Load chef-approved recipes, approved images, prices, and reviewed translations. Enable payment/fiscal adapters in sandbox only; validate direct hosted callbacks and all recovery cases before live credentials.
10. Rehearse ordering, bills, kiosk payment, cash handover, printing, fiscal handling, internet/host/cron outages, and restore. Measure response/polling/print latency on the actual host.
11. Train staff and obtain operational sign-off before enabling live mode. Deployment/account changes occur only with authorization; this document is not such an action.

## Configuration and updates

Runtime settings include application origin, actual private/public paths, database connection, PHP/cron versions, leases, provider timeouts, image limits, logging, and backups. Secrets stay outside source and public roots; administrative screens show redacted status only. Business settings and permissions remain versioned and audited.

Announce maintenance, protect active checkouts, take a verified backup, pause affected cron/bridge processing, apply reviewed migrations, and deploy a matched private/public release. Run smoke checks and resume work deliberately. Do not refresh tablets during payment; service-worker updates wait for an idle-safe moment.

Rollback must preserve database compatibility and reconciliation. Never restore an older database over newer payments without accounting for external money/fiscal outcomes. Use controlled repair or the tested restore procedure.
