# Screen and route map

These are proposed frontend routes, not API endpoints. Authentication is enforced by the backend as well as the UI. Never use a table number in a URL as authorization.

| Screen ID | Route | Principal | Essential content/action |
|---|---|---|---|
| S01 | `/setup` | Installer/owner | First-install identity, timezone, test/production mode |
| S02 | `/staff/sign-in` | Staff | Individual sign-in; protected rapid unlock on enrolled devices |
| S03 | `/staff/tables` | Waiter/manager | Table status, guest count, balance, open/retrieve visit |
| S04 | `/staff/visits/:visitId` | Assigned waiter | Guest list, assign tablet, order progress, close visit |
| S05 | `/device/activate` | Enrolled device | Short-lived pairing code; no private data before activation |
| S06 | `/table/menu` | Bound guest | Categories, meal photos, guest identity, own cart |
| S07 | `/table/meals/:mealId` | Bound guest | Main photo, ingredient orbit/list, price, allowed choices |
| S08 | `/table/cart` | Bound guest | Item-specific changes, quantity, submit own order |
| S09 | `/table/orders` | Bound guest | Own accepted orders and status; request assistance |
| S10 | `/table/bill` | Bound guest | Own bill, shared allocations, table-total summary without private payment data |
| S11 | `/table/checkout/:checkoutId` | Scoped guest/staff | Fixed amount, permitted methods, payment status |
| S12 | `/kiosk` | Enrolled kiosk | Welcome, language, start new session |
| S13 | `/kiosk/dining` | Kiosk session | Eat-in/takeaway |
| S14 | `/kiosk/menu` | Kiosk session | Same catalogue with kiosk layout |
| S15 | `/kiosk/meals/:mealId` | Kiosk session | Same customisation engine as S07 |
| S16 | `/kiosk/cart` | Kiosk session | Full review and edit before payment |
| S17 | `/kiosk/checkout` | Kiosk session | Collection name, amount, M-PESA or pay-at-cashier |
| S18 | `/kiosk/status/:reference` | Bound session | Pending/paid result, collection number, print problem guidance |
| S19 | `/staff/cashier` | Cashier | Pending kiosk references, guest bills, payment exceptions |
| S20 | `/staff/checkouts/:checkoutId` | Cashier | Cash/card records, M-PESA status, receipt |
| S21 | `/staff/cash` | Cashier/manager | Drawer session, waiter handover, expected/count/difference |
| S22 | `/kitchen` | Kitchen device | Station queue, table/guest labels, modifications, acknowledge/prepare/ready |
| S23 | `/collection` | Display device | Collection numbers and status; optional approved first names only |
| S24 | `/admin/ingredients` | Menu editor | Ingredient library, image upload, composition |
| S25 | `/admin/meals` | Menu editor | Meal editor, ingredient selection, price, removability, preview/publish |
| S26 | `/admin/availability` | Authorized staff | Sold out/portion count with reason |
| S27 | `/admin/staff` | Owner/manager | Staff, role grants, activation/revocation |
| S28 | `/admin/settings` | Owner | Hotel identity, table list, stations, receipt layout |
| S29 | `/admin/devices` | Manager | Pair/revoke, binding and last-seen state |
| S30 | `/admin/reports` | Manager/owner | Sales, tenders, balances, cash custody, exceptions |
| S31 | `/admin/integrations` | Owner | Redacted provider configuration, test status, fiscal queue |
| S32 | `/admin/audit` | Owner/auditor | Filtered immutable action trail |

## States required on each screen

Loading, empty, ready, permission denied, recoverable failure, and disconnected. Mutation screens also need submitting, uncertain outcome, success, and conflict states. These must be designed alongside the happy path.

Do not show a customer a staff login after a routine session expiry. Explain the tablet needs reassignment and provide a call-waiter action. Kiosk timeouts first warn and permit extension; restart clears personal inputs but does not delete outstanding server payment records.

## Navigation boundaries

Customer table mode: Menu / My orders / My bill / Call waiter. Kiosk: a visible step sequence and sticky order summary. Staff mode: Tables / Cashier / Kitchen / Catalogue / Reports as allowed. Public collection mode has no interactive access to any private order lookup.
