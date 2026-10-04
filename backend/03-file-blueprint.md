# Backend source-file blueprint

Planned implementation paths. The documentation delivery does not include executable source or migrations.

```text
apps/api/
  package.json
  tsconfig.json
  src/server.ts                # process lifecycle, graceful shutdown
  src/app.ts                   # Fastify composition and error mapping
  src/config/schema.ts         # validated environment/runtime settings
  src/plugins/database.ts
  src/plugins/auth.ts
  src/plugins/csrf.ts
  src/plugins/rate-limit.ts
  src/plugins/request-id.ts
  src/modules/identity/
  src/modules/catalogue/
  src/modules/availability/
  src/modules/visits/
  src/modules/ordering/
  src/modules/billing/
  src/modules/payments/
  src/modules/cash/
  src/modules/fulfilment/
  src/modules/printing/
  src/modules/fiscal/
  src/modules/reporting/
  src/modules/audit/
  src/modules/delivery/
apps/worker/
  src/main.ts                  # leases, retries, shutdown
  src/jobs/                    # print, fiscal, event and reconciliation jobs
apps/payment-relay/
  src/main.ts                  # optional public callback intake, restricted surface
  src/provider-verification.ts # provider-specific evidence handling
  src/durable-inbox.ts         # persists callbacks before acknowledgment
  src/hub-delivery.ts          # authenticated outbound-hub retrieval contract
packages/contracts/
  openapi.yaml                 # create and validate in milestone M1
  schemas/                    # shared runtime schemas
packages/domain/
  money.ts                    # exact arithmetic and allocation policy
  transitions.ts              # allowed states and guards
  permissions.ts              # canonical capability IDs
packages/database/
  migrations/                 # ordered, reviewed schema changes
  seeds/                      # demo-only fixtures, no live credentials
packages/adapters/
  mpesa/                      # selected provider implementation
  fiscal/                     # chosen verified integrator
  printers/                   # allowlisted physical printer transport
infra/
  compose.yaml                # pinned services for approved local deployment
  proxy/                      # TLS and restricted routes
  backup/                     # backup and restore tooling
tests/
  integration/
  contract/
  resilience/
```

Each domain module typically contains `routes.ts`, `schemas.ts`, `service.ts`, `repository.ts`, and meaningful tests; add files as the behaviour requires. Keep external provider payloads inside adapters. Do not let browser contracts depend on provider-specific internal objects.

The optional relay needs its own isolation and lifecycle testing. It is an infrastructure component for a single installation, not a new multi-hotel business portal. A secure deployment may host separate instances on shared infrastructure, but hotel credentials and event stores must remain isolated.
