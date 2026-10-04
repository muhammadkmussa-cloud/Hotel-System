# Endpoint catalogue

All paths are relative to `/api/v1`. All commands follow [conventions](01-conventions.md). 'Own guest' means server-derived binding, not a supplied guest ID. Staff scope also includes visit/station assignment where applicable.

| Method | Path | Authorized caller | Behaviour |
|---|---|---|---|
| POST | `/setup/bootstrap` | One-time protected installer secret | Initialize owner/installation only while unconfigured; disable permanently afterward |
| POST | `/auth/sessions` | Staff credentials | Create staff session; rate-limited |
| DELETE | `/auth/sessions/current` | Current session | Revoke session and clear browser identity |
| GET | `/session` | Any authenticated principal | Redacted identity/capabilities/binding |
| GET/POST | `/staff` | staff.manage | List/create staff |
| PATCH | `/staff/{id}` | staff.manage | Update role/active state; version check |
| POST | `/devices/enrolments` | devices.manage | Create short-lived pairing invitation |
| POST | `/devices/activations` | Pairing secret | Redeem once; establish device session |
| GET | `/devices` | devices.manage | Enrolled devices and health |
| POST | `/devices/{id}/revocations` | devices.manage | Revoke device credentials and bindings |
| GET | `/tables` | visits.manage | Table status without unnecessary guest data |
| POST/PATCH | `/tables` / `/tables/{id}` | settings.manage | Configure table labels/capacity; cannot erase active/history references |
| GET/POST/PATCH | `/stations` / `/stations/{id}` | settings.manage | Configure preparation stations and routing |
| GET/POST/PATCH | `/printers` / `/printers/{id}` | settings.manage | Allowlisted printer configuration; no arbitrary URL proxy |
| POST | `/visits` | visits.manage | Open one active visit for table |
| GET | `/visits/{id}` | Assigned staff | Table-wide operational overview |
| POST | `/visits/{id}/guests` | visits.manage | Add guest to active visit |
| POST | `/guest-bindings` | visits.manage | Bind enrolled device to selected guest |
| POST | `/guests/{id}/reopenings` | visits.manage | Reopen a settled guest for additional charges; preserve payment history |
| POST | `/kiosk-sessions` | Enrolled kiosk | Start a fresh session with dining choice |
| PATCH | `/kiosk-sessions/{id}` | Current kiosk session | Update collection name/dining choice before checkout lock |
| POST | `/kiosk-sessions/{id}/endings` | Current kiosk device | End UI access and clear local data, not server money records |
| POST | `/visits/{id}/transfers` | visits.transfer | Transfer table or waiter with locks/audit |
| POST | `/visits/{id}/closures` | visits.close | Close only when financial guards pass |
| GET | `/menu` | Enrolled customer/staff | Published catalogue/version |
| GET/POST/PATCH | `/categories` / `/categories/{id}` | catalogue.edit | Draft menu categorisation and display order |
| GET | `/meals/{id}` | Enrolled customer/staff | Published details and allowed changes |
| GET/POST | `/ingredients` | catalogue.edit | Library browse/create |
| PATCH | `/ingredients/{id}` | catalogue.edit | Edit draft facts with review consequences |
| POST | `/media` | catalogue.edit | Validated upload; return media ID |
| PATCH | `/media/{id}` | catalogue.edit | Update approved alt/crop/rights metadata; keep original version history |
| POST | `/meals` | catalogue.edit | Create draft meal |
| PATCH | `/meals/{id}/draft` | catalogue.edit | Update draft, not existing published orders |
| POST | `/meals/{id}/recipe-reviews` | orders.review | Record kitchen approval against a specific draft version |
| POST | `/meals/{id}/publications` | catalogue.publish | Publish reviewed immutable version |
| PATCH | `/availability/{mealId}` | availability.manage | Sellable state/portion count |
| POST | `/carts/quotes` | Own guest/kiosk/staff | Validate choices and compute server quote |
| POST | `/orders` | Own guest/kiosk/staff | Idempotent submission; channel determined by principal |
| GET | `/orders/{id}` | Owner guest/scoped staff | Current submission and preparation state |
| GET | `/guests/current/orders` | Own guest | Own submitted orders |
| POST | `/orders/{id}/review-decisions` | orders.review | Resolve allergy/preparation review |
| POST | `/orders/{id}/change-requests` | Owner guest/staff | Request staff help; no silent mutation |
| POST | `/orders/{id}/cancellations` | orders.adjust | Approved cancellation/linked correction |
| GET | `/guests/current/bill` | Own guest | Own charges/payments and safe table summary |
| GET | `/guests/{id}/bill` | Scoped staff | Selected guest bill |
| POST | `/charges/{id}/share-proposals` | Owner guest/scoped staff | Propose participants; no immediate imposed debt |
| POST | `/share-proposals/{id}/confirmations` | bills.allocate | Confirm shares on editable bills |
| POST | `/checkouts` | Own guest/kiosk/scoped staff | Freeze selected payable charges |
| GET | `/checkouts/{id}` | Owner session/scoped staff | Amount and separate payment/fiscal state |
| POST | `/checkouts/{id}/cancellations` | Owner/scoped staff | Only safely cancellable attempts/checkouts |
| POST | `/checkouts/{id}/mpesa-attempts` | Owner/scoped staff | Initiate phone prompt; 202 pending |
| GET | `/payment-attempts/{id}` | Owner/scoped staff | Redacted verified/unknown outcome |
| POST | `/checkouts/{id}/cash-payments` | payments.cash | Record tender/change/custody |
| POST | `/checkouts/{id}/card-payments` | payments.card | Record external successful terminal payment |
| POST | `/payments/{id}/refund-requests` | refunds.request | Request with reason and affected allocation |
| POST | `/refunds/{id}/approvals` | refunds.approve | Approve; external completion still separate |
| POST | `/refunds/{id}/external-completions` | refunds.complete | Record approved cash/card refund evidence; never override M-PESA verification |
| POST | `/cash-handovers` | payments.cash | Propose amount being handed to cashier |
| POST | `/cash-handovers/{id}/acceptances` | cash.reconcile | Record counted/accepted custody |
| POST | `/drawer-sessions` | cash.reconcile | Open drawer with float |
| POST | `/drawer-sessions/{id}/closures` | cash.reconcile | Count and record variance |
| GET | `/kitchen/tasks` | kitchen.view | Only assigned station work |
| POST | `/kitchen/tasks/{id}/transitions` | kitchen.update | Guarded state change |
| POST | `/service-requests` | Own guest | Call waiter / request bill assistance |
| POST | `/service-requests/{id}/resolutions` | Scoped staff | Acknowledge/resolve request |
| GET | `/collection` | Collection display | Public redacted ready/preparing references |
| GET | `/receipts/{id}` | Owner/scoped staff | Receipt projection, no editable payment state |
| POST | `/print-jobs` | printing.request | Approved source/destination only |
| POST | `/print-jobs/{id}/copies` | printing.request | Tracked COPY of original payload |
| POST | `/print-bridge/claims` | Registered installation/station service | Lease a bounded batch of assigned jobs; idempotent claim, no arbitrary destination |
| POST | `/print-bridge/jobs/{id}/results` | Service owning lease | Record sent/failed/unknown with lease identity; cannot alter order/payment |
| POST | `/print-bridge/heartbeats` | Registered installation/station service | Report readiness without financial/customer payloads |
| GET | `/reports/{reportName}` | reports.view | Allowlisted reports with date/role scope |
| GET | `/audit-events` | audit.view | Redacted auditable event list |
| GET/PATCH | `/settings` | settings.manage | Redacted configuration, version-controlled edits |
| GET | `/fiscal-documents` | fiscal.manage | Integration status/exceptions |
| POST | `/fiscal-documents/{id}/reconciliations` | fiscal.manage | Query/reconcile uncertain result, no new duplicate invoice |
| GET | `/events` | Authenticated principal | Server-filtered short JSON event poll with cursor/limit; returns next cursor or snapshot-reload signal |
| GET | `/health/live` | Public | Minimal process liveness only |
| GET | `/health/ready` | Operations principal | Dependency readiness without secrets |

Provider callback endpoints route to the hosted PHP adapter, e.g. `/callbacks/mpesa`; no separate relay is required. Their exact vendor payload, verification and response semantics must be specified during the integration spike; do not invent a universal signed-callback protocol.

Admin reports, fiscal credentials, receipt lookup, and collection data must not be accessible solely by guessing a sequential order number. Bulk operations need their own authorization and limits if added later.
