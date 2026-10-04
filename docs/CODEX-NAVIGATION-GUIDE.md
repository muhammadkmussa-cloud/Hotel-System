# Developer navigation guide

Start at [README](../README.md), [decision register](../product/01-decisions.md), and [build plan](../delivery/01-build-plan.md). This is a documentation-first project; planned code paths are described in the frontend/backend blueprints and are not existing source files.

## Surface ownership

Customer menu/customisation: [design](../design/01-premium-design.md), [ingredient interaction](../design/02-ingredient-experience.md), and [frontend](../frontend/01-architecture.md). Order/bill integrity: [requirements](../product/02-requirements.md), [data](../architecture/02-domain-data.md), and [transactions](../backend/02-transactions.md). Permissions: [RBAC](../security/01-rbac.md). Providers/hardware: [payments](../integrations/01-payments.md), [fiscal](../integrations/02-etims.md), and [printing](../integrations/03-printing-hardware.md). Deployment: [installation](../operations/01-installation.md) and [recovery](../operations/02-backup-recovery.md).

## Review packet for future code changes

Describe the concrete behaviour changed, relevant requirement/decision IDs, affected routes/modules, tests and physical/provider evidence, migrations, and unresolved limitations. Include UI screenshots for visible changes, but never real customer/payment credentials. Financial changes must explain transaction boundaries and replay/recovery behaviour. Do not claim production readiness from mocked tests.

Update the index when adding specifications. Keep generated API types and hand-written domain policies separate. If an assumption changes, update the decision register before allowing the implementation and docs to drift.
