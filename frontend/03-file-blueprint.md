# Frontend source-file blueprint

**Planned files, not present application code.** Implement progressively following [build plan](../delivery/01-build-plan.md). Each feature owns its routes, UI, API hooks, and tests; shared rules stay in backend contracts.

```text
apps/web/
  package.json                 # pinned frontend dependencies and scripts
  index.html                   # Vite entry, language and viewport setup
  vite.config.ts               # bundles, aliases, dev proxy only
  tsconfig.json
  public/                      # non-private application icons; no secrets
  src/
    main.tsx                   # application boot and error boundary
    app/router.tsx             # screen-map routes, lazy feature bundles
    app/providers.tsx          # query/session/localisation contexts
    app/session.ts             # principal and capability handling
    app/service-worker.ts      # public assets only; versioned update flow
    layouts/CustomerLayout.tsx
    layouts/KioskLayout.tsx
    layouts/StaffLayout.tsx
    layouts/KitchenLayout.tsx
    features/catalogue/        # category/menu/search/meal detail
    features/customiser/       # orbit, composition drawer, change summary
    features/cart/             # guest-scoped recoverable drafts
    features/table-service/    # guest identity, visit assignment, orders
    features/checkout/         # bill, methods, M-PESA pending/results
    features/kiosk/            # dining/name/reference/reset flow
    features/cashier/          # external card/cash/reconciliation
    features/kitchen/          # station queue and transitions
    features/collection/       # public projection
    features/menu-admin/       # images, ingredients, recipe publishing
    features/staff-admin/      # roles, grants, device management
    features/reports/          # sales/tenders/cash/fiscal exceptions
    components/ui/             # accessible shared primitives
    components/food/           # image/ingredient presentation
    lib/api-client.ts          # transport and standard errors
    lib/events-client.ts       # SSE replay and query refresh
    lib/draft-storage.ts       # session-bound storage and secure clearing
    lib/money-display.ts       # formatting only, not authoritative pricing
    lib/locale.ts
    styles/tokens.css
    styles/global.css
    locales/en.json
    locales/sw.json            # only enable after human review
    generated/api-types.ts     # generated from API contract during build
  tests/                       # unit/component and accessibility tests
tests/e2e/                     # shared multi-device end-to-end journeys
```

Actual file subdivision depends on implementation size. Do not create empty components merely to satisfy this tree. Every routed feature needs loading, denied, empty, disconnected, and error states before it is considered complete.
