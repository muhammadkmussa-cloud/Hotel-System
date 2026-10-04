# Open decisions and risks

This list keeps progress possible without falsely claiming everything has been agreed. Prototype work can continue using named defaults. Only dependent production work waits for evidence.

| ID | Validation required | Proposed direction | When resolved |
|---|---|---|---|
| O01 | Payment provider, merchant onboarding, refund/status capabilities, amount precision | Daraja first; Paystack alternative | M1 before real integration commitments |
| O02 | Hotel tax setup, fiscal invoice boundary, approved integrator, outage policy | Verified eTIMS integrator | M1 discovery; before M8/live |
| O03 | Actual meal recipes, fixed/removable ingredients, allergy procedure, image rights | Chef-approved catalogue | Before M3 publication/pilot |
| O04 | Device sizes/OS, printer models, network coverage, hotel-side print bridge suitability | Small physical pilot | M1/M2 before bulk purchase |
| O05 | Shared-item confirmation and early guest checkout policy | D01/D02 defaults | Prototype review before M6 |
| O06 | Waiter cash handover timing, drawer roles, exception authority | D03/D15 with independent cashier count | Before live cashier training |
| O07 | Interface languages and reviewed translations | English; Kiswahili when reviewed | Before locale enablement |
| O08 | Kiosk eat-in collection versus table delivery | Counter collection D07 | Kiosk prototype review |
| O09 | Availability reservation durations and portion limits | Basic portion controls D12 | Payment/hardware spike |
| O10 | Hosting provider/location, backup retention, recovery targets, support/update model | DirectAdmin C23; independent off-account backups, D17 | Before operational release |
| O11 | Whole-table payer, mixed-method UI, service charges/tips | Guest-by-guest settlement first | Before expanding M6 scope |
| O12 | Sale/licence/support terms for installations elsewhere | No SaaS assumptions | Separate business decision |
| O13 | Actual DirectAdmin tier, web server/routing, PHP/MySQL versions/extensions, cron limits, private paths, quotas, Composer/SSH, restore capability | Shared-host-compatible D16/D17; verify MySQL, not an assumed MariaDB substitute | M1 before implementation/deployment commitments |

## Key risks

Visual orbit may be difficult with many labels: retain accessible grid/list and test both. Menu facts may be incomplete: require chef-owned recipes and compound ingredients. Hosted service or internet may fail: connectivity contingency, manual incident procedure, off-account backup, restore rehearsal. Shared-host cron/polling quotas may limit responsiveness: measure against the selected plan before release. Payment outcome may be uncertain: stable references, verification, exception queue. Printers may deliver without reporting: explicit unknown state and copies. Multiple devices may race: server transaction guards, versions, uniqueness constraints. Tax integration may have requirements beyond the generic plan: isolate adapter and validate against actual certified workflow.

## Decision record format

ID; date; question; confirmed facts; options considered; chosen default; evidence/source; owner; implications for screens/API/data/tests; accepted by; review trigger. Never backdate user approval. Updating a default requires changing all affected documents and acceptance tests.
