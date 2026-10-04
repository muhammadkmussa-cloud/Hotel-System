# Backup and recovery

Proposed operational targets, subject to DirectAdmin provider capability and restore rehearsal: committed-order recovery point within 15 minutes and recovery time within 60 minutes to an isolated restored hosting installation. Use consistent MySQL backups and binary-log recovery only where the host provides the required access. Shared hosting may not support that capture frequency or point-in-time recovery; O10/O13 must agree achievable targets or a suitable plan before release. These are goals, not guarantees.

## What to protect

Database including financial/audit/inbox/outbox records; original and derived menu images; installation settings; printer templates; encrypted provider credentials and print-bridge configuration through a separately controlled process; exact software/migration versions. Keep decryption recovery material separately accessible to the owner.

Automated backups must include independent off-account storage. A second folder on the same hosting account is not disaster recovery. Retention schedule and secure storage location are hotel-approved configuration. Monitor backup age and failures visibly; encrypt copies and restrict restore/download access.

## Restore rehearsal

Restore to an isolated environment; verify schema/version, record counts, sample hashes, media loading, bill totals, and links between payment/fiscal references. Do not send printer jobs, provider requests, or fiscal submissions during the rehearsal. Restore mode disables external side effects until explicitly reconciled.

## Incident procedures

Hotel internet failure: stop confirmed electronic orders and cash/payment recording; keep drafts and original attempt references. Use numbered manual records under staff procedure, then reconcile before entering recovery transactions. Hosted callbacks may still receive money while the hotel is disconnected.

Printer failure: use kitchen display/reference, inspect transport/paper, request labelled copy when needed. Do not re-enter the sale.

Hosting failure: stop electronic submission, use numbered manual tickets and staff incident log, recover the hosted application/database with the provider, then reconcile manual orders and money before importing them. Keep a mapping from manual reference to system reference.

Database restore: record restored cutoff. Compare provider, terminal, cash, and fiscal records since that cutoff. Reconcile externally completed transactions before enabling any replay worker. Use original references to avoid duplicate charges/invoices. Resume queues only after reviewed recovery decisions.

## Closeout monitoring

Alerts: stale backup, low storage, database readiness, kitchen/print-bridge heartbeat, cron heartbeat, hosting quota pressure, print backlog, unknown payments, unmatched terminal entries, cash custody overdue, fiscal rejection/unknown state, certificate expiry, and incorrect system clock. Staff-facing messages explain who should act and what remains safe to do.
