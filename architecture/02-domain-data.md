# Domain and database specification

Status: logical schema for implementation. Tables/fields below are the design contract. P02.03 implements only hotel_settings and its model; all other business tables remain planned. P02.02 provides the reviewed migration runner/history conventions. All business records carry installation identity through the isolated database and audit context. Use opaque IDs; public order numbers are display references, not authorization credentials.

Use MySQL with InnoDB, utf8mb4, explicit transactions, foreign keys, and prepared PDO statements (C21/D16). Verify constraints and locking on the actual supported MySQL version.

## Core records

| Record | Key fields / relationships | Important constraint |
|---|---|---|
| HotelSettings | name, timezone, currency, business-day cutoff, fiscal configuration version | One active installation identity |
| StaffUser / Role / Permission | identity, credential hash, active flag, role grants | Deactivation revokes sessions; no shared accounts |
| Device / DeviceSession | enrolled ID, mode, token digest, expiry, last_seen | Revocable, mode-scoped access |
| DiningTable | display_number, capacity, active | Unique active table label |
| Visit | table_id, owner_waiter_id, status, opened_at, closed_at, version | Unique active-table guard compatible with MySQL |
| Guest | visit_id, display_label, optional name, status | Guest number unique within visit |
| GuestBinding | device_session_id, guest_id, expires_at, revoked_at | Valid binding required for guest actions |
| KioskSession | device_session_id, dining_option, collection_name, active/ended, expiry | Reset ends customer access but retains server order/payment records |
| Ingredient / IngredientComponent | name, media_id, composition links, allergen notes | No composition cycles |
| Category / MealCategory | label, display_order, meal relationship | Published menu categorisation versioned with catalogue |
| Meal / MealVersion | name, recipe, price, image, publication status | Published versions immutable |
| MealIngredient / ModifierRule | meal_version_id, ingredient_id, fixed/removable/extra, price rule | Rules scoped to meal, not global ingredient |
| Availability | meal_id, sellable, optional portions_remaining, version | Atomic decrement/reservation |
| Cart / CartLine | actor_session, selected version, choices, quantity, version | Draft does not create sale or kitchen work |
| OrderSubmission | visit/guest or kiosk session, channel, state, reference, version | Unique idempotent submission |
| OrderItem | submission_id, meal snapshot, quantity, price/tax snapshot, changes | Historical snapshot survives catalogue changes |
| ReviewRequest | submission_id, reason, assigned staff, decision, timestamps | Review completion required where applicable |
| Charge / ChargeAllocation | order_item_id, proposed/posted state, gross_minor, bill_id, allocated_minor | Allocation sum equals charge total; provisional kiosk charges are not posted sales |
| Bill (guest or kiosk) | exactly one of guest_id or kiosk_session_id, state, version | One current bill per guest/session; posted history retained |
| ShareProposal | charge_id, participants, shares, state, confirmed_by | Guests cannot impose charges on others silently |
| Checkout / CheckoutAllocation | bill/charge selections, amount_minor, currency, state, version | Freeze selected allocations; no overlapping active checkout |
| PaymentAttempt | checkout_id, method, merchant/provider, reference, state | Pending attempt is not payment received |
| Payment / PaymentAllocation | verified/recorded amount, method, source_ref, applied allocations | Unique merchant/provider transaction; no over-application |
| Adjustment / Refund | original charge/payment, amount, reason, approver, external state | Append-only correction, not deletion |
| CashCustody / CashHandover | waiter, amount, cashier, counted_amount, accepted_at | Custody transfer never adds sales |
| DrawerSession / DrawerEntry | opening float, counted closing, actor, reason | Reconcile expected versus actual |
| Station / StationTask | submission, item, quantity, state, timestamps | Shared bill splits do not multiply task quantity |
| PrintJob / PrintAttempt | kind, source_id, destination, payload snapshot, outcome | Stable job identity, explicit ambiguous result |
| FiscalDocument | source charges, provider reference, status, kind, totals | Unique fiscal request; credit note references invoice |
| InboxEvent / OutboxEvent | source/key, payload hash, attempts, next_attempt, processed_at | Unique delivery key and durable retry |
| AuditEvent | actor, device, action, target, before/after refs, reason, request_id | Append-only; redact secrets and unnecessary personal data |

## Money representation

Store monetary values in integer KES minor units; 120000 means KSh 1,200. Never use binary floating point for money. Use a decimal-safe pricing/tax routine and documented rounding. Shared allocation uses deterministic remainder allocation by stable guest order, preserving the exact total.

The default equal split may produce fractional shillings. Provider amount granularity is a launch gate: the M-PESA adapter must declare its accepted units. If a payable amount cannot be represented, block that method with an explanation and allow cashier-assisted allocation or another method. Do not silently round or overcharge. A whole-shilling allocation option may be enabled only after hotel approval and while preserving the original total.

P02.06 implements App\Support\MinorAmount for non-negative integer KES minor units, accepting PHP integers or canonical ASCII integer strings up to PHP_INT_MAX. This is a technical representation limit, not a business limit. Zero is allowed; operation-specific positivity/limits remain future work. Decimals, floats, signs, whitespace, leading zeroes and overflow are rejected without rounding. Signed adjustments require a separate operation contract. Future browser/API contracts must preserve large amounts as exact strings; this validator is not yet an endpoint or money arithmetic implementation.

P02.07 adds an explicitly named half-up division helper for non-negative integer minor-unit ratios (nearest unit, ties up). This does not select a fiscal/provider rounding policy. Equal allocation uses quotient/remainder directly, with extra minor units assigned by ascending bytewise canonical server recipient ID. This defines stable guest order independently of visible guest numbers or request order. It preserves the full total, permits zero shares and requires distinct recipients; the caller must enforce guest existence and authorization. D01 sharing remains proposed. No rate multiplication, weighted allocation or whole-shilling conversion is implemented.

## Implemented installation record

P02.03 creates hotel_settings with a UUID primary key, required name/timezone, KES-only currency, nullable business-day cutoff/fiscal configuration version and timestamps. A stored generated installation_slot equal to 1 has a unique index, enforcing at most one row even with competing inserts or raw SQL. There is no inactive/cross-hotel directory. This is an implementation of C01/R01, not a shared tenancy feature.

No settings row is seeded and no application API writes these fields yet. Empty storage means unconfigured. Owner setup/settings validation must later enforce a nonblank name and valid IANA timezone; null cutoff/fiscal version means not supplied. P02.05 shares the UUIDv7 trait through App\Models\Record and explicitly generates automatic timestamps in UTC. No business-day policy or fiscal provider approval is inferred from schema defaults. Rollback drops the settings table and data and remains an explicitly controlled administrative operation.

P02.04 verifies the configured InnoDB engine and utf8mb4_unicode_ci table/connection conventions on real MySQL. Multilingual text, including four-byte characters, survives a committed write and fresh connection. Exception and constraint-error transactions restore the full prior settings row. This is DML rollback evidence only; MySQL DDL, business transaction policies and deadlock retries are not covered.

## Identifier and time conventions

Business models extend App\Models\Record: UUIDv7 string keys, no auto-increment and UTC automatic timestamps. Preserve app UTC and database session +00:00; normalize explicit/imported dates at input boundaries. Current timestamps have second precision and serialize as ISO-8601 UTC. Hotel timezone is for local display/business-day decisions. UUIDs can reveal time and are not credentials. Public order/collection numbers must be separate display fields, never access tokens; future endpoints still require authorization. P02.05 verifies the shared settings implementation, not order numbering or authentication.

## Referential and concurrency rules

Foreign keys protect relationships. Archive menu/staff/table records instead of deleting referenced history. Use optimistic versions for editable resources and row locks for financial/availability mutations. Acquire multi-bill locks in sorted ID order to reduce deadlocks. Maintain unique constraints as the final guard against duplicate payment application.

MySQL migrations must implement active-only uniqueness explicitly: for example, a nullable generated active-table key with a unique index, alongside locking the table row when opening/closing a visit. Do not copy a PostgreSQL partial-index declaration. Apply equivalent guards to overlapping checkouts and test concurrent insertion. Handle deadlocks with bounded retries of the complete idempotent transaction.

Recipe approval references an exact draft/version digest. Changing ingredients, composition, or removability invalidates affected approval and requires review before publication. Ingredient-library edits create a new content version and identify impacted meals; they do not silently alter published recipes.

Proposed indexes: visit status/table, guest visit, submission created_at/state, station task station/state/time, checkout state, provider merchant/reference, outbox next_attempt, audit timestamp/actor, and fiscal state. Measure queries before adding speculative indexes.

## Financial views

Keep sales, payment collection, refunds, cash custody, and provider settlement as distinct reports. A provider payout is not a new customer sale. Bill projections are reproducible from posted records and can be checked against cached balances. Never use a client cart total as the ledger.

In other documents, 'guest bill' means a guest-owned Bill, not a separate incompatible table. Kiosk checkout can reference a kiosk-owned Bill. Orders waiting for review/payment have proposed charges that are shown in their quote but excluded from posted sales. Charges post when the order is released: immediately for an eligible table order, after approval for review-held table work, and atomically with verified payment allocation/release for an eligible kiosk order. A paid but unfulfillable kiosk case records received money as unapplied pending staff resolution; it does not manufacture a sale. Expired unpaid proposed charges are cancelled without reducing posted sales.
