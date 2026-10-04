# Hotel System

**Implementation documentation · Version 1.0 · 4 October 2026**

A restaurant ordering and POS system for **one hotel per installation**, designed to be installed separately at other hotels. The customer experience combines portable table tablets, a walk-in kiosk, and a photographic meal customiser with ingredient circles.

This project currently contains **Markdown specifications and a build plan**. It does not contain a working application, live payment integration, installed hardware, production food photography, or a certified tax integration. Source-file blueprints describe what developers should create next; their paths are not claims that those source files already exist.

## Start here

1. [Confirmed decisions and proposed defaults](product/01-decisions.md): what the conversation actually established.
2. [Build plan](delivery/01-build-plan.md): implementation order and completion gates.
3. [Premium frontend design](design/01-premium-design.md): visual direction and measurable design standards.
4. [Screen map](product/04-screen-map.md): every principal customer and staff screen.
5. [System architecture](architecture/01-system.md): frontend, backend, local installation, and external services.
6. [Documentation index](DOCUMENTATION-INDEX.md): the full collection.

## Non-negotiable product decisions

- Several tablets may join the same table visit; each is assigned to a guest.
- Each guest submits independently. Each submission has its own kitchen ticket, table label, and guest label.
- Guests can order more while their bill is open. Table customers pay after eating.
- Cash, M-PESA, and card are available. A separate card terminal is **not integrated**; the cashier confirms its successful transactions.
- Kiosk orders reach preparation only after payment confirmation and any required staff review.
- Meal and ingredient images come from a reusable catalogue. Removing an ingredient changes only that ordered item.
- A waiter closes the table visit once all balances are settled.
- Restaurant billing, cashier duties, kitchen operations, and sales reporting are in scope. Room booking is not.

## Read the status labels

**Confirmed** means the user explicitly established the requirement. **Proposed default** means the documentation recommends it but does not attribute approval to the user. **Validation required** means evidence or a provider/hotel decision is needed before that part goes live.

Engineering may implement proposed defaults behind configuration or adapters. Do not start real payments, buy hardware, select a tax vendor, or treat unresolved operational policies as approved simply because they appear in this project.

## Project navigation

| Folder | Purpose |
|---|---|
| `product/` | Scope, traceable requirements, customer journeys, screen inventory |
| `design/` | Premium visual system, ingredient layout, food imagery, accessibility |
| `frontend/` | Application structure, component contracts, source-file blueprint |
| `backend/` | Domain modules, transaction rules, source-file blueprint |
| `architecture/` | Deployment shape, data model, lifecycle states, synchronisation |
| `api/` | Endpoint catalogue, payload examples, API rules, live events |
| `security/` | Roles, permissions, device sessions, privacy, audit |
| `integrations/` | M-PESA/card/cash, eTIMS, printers and hardware |
| `operations/` | Installation, backups, staff procedures, reports and availability |
| `delivery/` | Build stages, backlog, tests, acceptance, unresolved decisions, document review |
| `research/` | Sources and limits of the previous research |
| `templates/` | Hotel setup and asset handover forms |

## Recommended implementation direction

React + TypeScript + Vite frontend; Fastify + TypeScript modular backend; PostgreSQL; one on-site authoritative installation with a restricted internet-facing payment relay where needed. This is a **proposed technical baseline**, not an already deployed or benchmarked solution. See [architecture](architecture/01-system.md).

The first build milestone is a photographic, interactive prototype and a tested local vertical slice. Production integration and deployment follow the gates in the build plan.

## Document precedence

Confirmed decisions outrank defaults. The requirements define intended behaviour; API and architecture documents translate it. If documents disagree, record the conflict and correct all affected specifications rather than silently selecting a convenient interpretation.

No software licence is granted by this documentation bundle. Licensing and commercial support terms for installation at other hotels remain a business decision.
