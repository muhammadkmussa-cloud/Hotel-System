# Role-based access control

Status: proposed staff permission baseline supporting C10–C12. Role-based permissions combine with resource ownership, device mode, station assignment, and state guards. A manager role does not override mathematical balances or prove an external payment succeeded.

## Principals

Owner configures the installation and grants roles. Manager supervises service and approves exceptions. Cashier records external card/counter cash and reconciles money. Waiter owns assigned visits and collects cash. Kitchen staff operate assigned preparation stations; kitchen lead can review recipes/allergy requests. Menu editor drafts content. Auditor has approved read-only reporting. Device/guest sessions are narrowly scoped non-staff principals. Installer has time-limited setup access, removed after handover.

| Capability | Default allowed roles | Scope/condition |
|---|---|---|
| staff.manage | Owner; manager for non-owner grants | No self-escalation or last-owner removal |
| settings.manage | Owner | Sensitive changes require recent authentication |
| devices.manage | Owner, manager | Enrol/revoke only this installation |
| visits.manage | Waiter, manager, cashier | Waiter limited to assigned visits; manager can assign |
| visits.transfer | Manager | Table/waiter transfer with audit and destination validation |
| visits.close | Assigned waiter, manager | All closure guards pass |
| catalogue.edit | Menu editor, manager, owner | Draft content only |
| catalogue.publish | Manager, owner | Recipe/removability review recorded by kitchen lead |
| availability.manage | Kitchen lead, manager | Assigned menu/stations; reason recorded |
| orders.review | Kitchen lead, manager | Actual preparation assessment; cannot promise safety by software alone |
| orders.adjust | Manager | Reason and kitchen acknowledgment when applicable |
| bills.allocate | Waiter, cashier, manager | Same assigned visit; no locked/settled allocations |
| payments.cash | Waiter, cashier, manager | Own custody or authorized active drawer |
| payments.card | Cashier, manager acting as cashier | Successful external transaction, reference required |
| refunds.request | Waiter, cashier, manager | Request only; no money movement |
| refunds.approve | Manager, owner | Reason, original payment, provider capability; completion separate |
| refunds.complete | Cashier, manager | Approved cash/card disbursement with evidence; no manual electronic-success override |
| cash.reconcile | Cashier, manager | Count/accept handover and drawer close; cannot accept own proposal alone |
| kitchen.view | Kitchen staff/lead, manager, assigned waiter | Station/visit projection only |
| kitchen.update | Kitchen staff/lead, manager | Assigned station task |
| printing.request | Waiter, cashier, manager, kitchen staff | Source/destination restricted to role |
| reports.view | Owner, manager, auditor; cashier for shift reports | Financial detail limited by role |
| audit.view | Owner, auditor; manager for operations | Mask unnecessary personal data |
| fiscal.manage | Owner, designated manager/cashier | Provider state and redacted config; no arbitrary acceptance |

Owner financial actions require explicit appropriate permission/role assignment; owning the installation does not create a 'mark M-PESA successful' operation. The only electronic settlement authority is verified provider evidence applied by the service.

## Guest and device permissions

Guest can browse published menu, edit own draft, submit own order, read own bill, propose sharing own item, request own checkout, initiate their M-PESA attempt, and call waiter. A safe aggregate table total may be visible, but other guests' phone numbers, detailed payments, and private notes are not.

Kiosk can access only its current session. Kitchen devices receive no customer payment credentials. Collection devices see only the public queue projection. Guest IDs in request bodies do not expand scope.

The hotel print bridge is a separate service principal, scoped to its installation and registered stations. It can claim assigned jobs, report leased-job outcomes, and send heartbeats only; it cannot submit customer orders, read general financial reports, or apply payments. Revoke/rotate its credentials independently of staff sessions.

## Enforcement

Every route and worker command validates capability, resource scope, and lifecycle. Record both requester and approver for delegated actions. Authorization-denied responses do not expose the existence of private unrelated records. Revocation invalidates sessions promptly and is checked on every event-poll and service request.

No unrestricted shared PIN. Fast staff unlock can be supported on a trusted enrolled terminal with a personal PIN, rate limiting, and a preceding authenticated enrolment, but sensitive actions require stronger recent authentication. Keep customer and staff sessions separate when a waiter hands over a tablet.

## Required negative tests

Waiter tries card confirmation; guest changes guestId/tableId; kitchen reads cashier reports; cashier edits provider credentials; menu editor publishes without review; deactivated staff reuses session; expired tablet polls bill events; cashier accepts their own handover; manager attempts to force unpaid visit closed. All must be denied without unintended side effects.

## P03.05 implemented enforcement interfaces

Principal identifies a server-verified subject. PrincipalResolver must verify credentials, expiry and revocation per request. CapabilityAuthorizer must check capability together with installation/resource ownership and lifecycle. AccessServiceProvider currently binds both interfaces to DenyAccess: no staff/device/service authentication is implemented yet.

Routes may use principal for identity-only reads or capability:reports.view (and other named capabilities) for scoped actions. RequireCapability always invokes RequirePrincipal itself; it cannot succeed because a route forgot a separate authentication middleware. A fresh resolution replaces any existing principal attribute. Missing principals return safe 401; denied scope/capability returns 403 before the handler. Submitted IDs, roles and headers do not supply a principal. Verified principal adapters and role/resource policies belong to the later feature steps. Domain transactions still enforce state/balance invariants even after middleware approval.
