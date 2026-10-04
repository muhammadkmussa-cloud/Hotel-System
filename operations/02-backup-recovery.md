# Backup and recovery

Proposed operational targets, subject to actual equipment and pilot validation: committed-order recovery point within 15 minutes using database backup/WAL strategy, recovery time within 60 minutes to tested replacement hardware. These are goals, not guarantees. A complete disk failure may lose more without validated off-device capture.

## What to protect

Database including financial/audit/inbox/outbox records; original and derived menu images; installation settings; printer templates; encrypted provider credentials and relay configuration through a separately controlled process; exact software/migration versions. Keep decryption recovery material separately accessible to the owner.

Automated backups must include off-device storage. A second folder on the same hub is not disaster recovery. Retention schedule and secure storage location are hotel-approved configuration. Monitor backup age and failures visibly; encrypt copies and restrict restore/download access.

## Restore rehearsal

Restore to an isolated environment; verify schema/version, record counts, sample hashes, media loading, bill totals, and links between payment/fiscal references. Do not send printer jobs, provider requests, or fiscal submissions during the rehearsal. Restore mode disables external side effects until explicitly reconciled.

## Incident procedures

Internet failure: retain local ordering/cash if local components are healthy; show electronic verification unavailable; preserve payment attempts.

Printer failure: use kitchen display/reference, inspect transport/paper, request labelled copy when needed. Do not re-enter the sale.

Hub failure: stop electronic submission, use numbered manual tickets and staff incident log, replace/restore hub, then reconcile manual orders and money before importing them. Keep a mapping from manual reference to system reference.

Database restore: record restored cutoff. Compare provider, terminal, cash, and fiscal records since that cutoff. Reconcile externally completed transactions before enabling any replay worker. Use original references to avoid duplicate charges/invoices. Resume queues only after reviewed recovery decisions.

## Closeout monitoring

Alerts: stale backup, low storage, database readiness, kitchen heartbeat, print backlog, unknown payments, unmatched terminal entries, cash custody overdue, fiscal rejection/unknown state, certificate expiry, and incorrect system clock. Staff-facing messages explain who should act and what remains safe to do.
