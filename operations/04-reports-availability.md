# Reporting and availability

## Required v1 reports

| Report | Definition | Important distinction |
|---|---|---|
| Sales by business day | Posted item charges minus approved sales adjustments | Not identical to money received that day |
| Collections by method | Successful cash/M-PESA/card payments, with refunds separately | Pending attempts excluded |
| Outstanding guest bills | Posted due balance for open/exception bills | Includes table/guest and assigned staff |
| Cash custody | Cash held by waiter less accepted handovers/approved corrections | Does not count a handover as a new sale |
| Drawer reconciliation | Opening float + net entries versus counted closing | Variance requires reason |
| Meal performance | Quantity sold and net sales by meal/version | Shared charges do not multiply dish quantity |
| Preparation performance | Accepted/preparing/ready/served timestamps | Estimates require sufficient actual data |
| Payment exceptions | Unknown, unmatched, overpaid, late-success, failed refund | Must remain actionable |
| Fiscal exceptions | Queued/rejected/unknown invoices and credit notes | Separate from paid/unpaid |
| Staff action history | Orders, approved changes, cash, refunds by actor | Access controlled; avoid misleading blame metrics |

Date filters display Africa/Nairobi and configured business-day cutoff. Store UTC instants and preserve original business-day association. Export only authorized data; log exports and mask personal fields by default. CSV exports neutralize spreadsheet formula injection in user-entered text.

Orders awaiting review or kiosk payment carry proposed charges only. Count them as pending demand, not posted sales. Release posts charges; expired unpaid references never inflate revenue. Late paid/unfulfillable kiosk orders appear in unapplied-money exceptions until staff resolves them.

## Availability v1 (D12)

Support a manual sellable/sold-out flag and optional whole-portion counts per meal. This is not ingredient stock accounting. All ordering devices see changes promptly, but the backend still checks on submit to resolve races for the last portion.

Table submission reserves/deducts portions on acceptance according to one configured policy; cancellation releases only when preparation rules permit. Kiosk reserves portions while payment is active. Unpaid abandoned reservations expire under a visible policy; uncertain payment and late success enter review rather than blindly restoring/releasing stock.

Reservation durations are chosen in the payment spike and pilot, not hardcoded from an assumed provider timeout. Cashier revalidates expired kiosk references before accepting money. Changing a sold-out switch does not remove already accepted orders.

## Later phase

Recipe quantities, units/conversions, ingredient purchasing, suppliers, wastage, batch preparation, stocktake, and cost-of-goods need a separate inventory specification. Ingredient photographs and composition do not by themselves provide accurate inventory deductions.
