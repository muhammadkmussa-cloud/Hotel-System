# End-to-end journeys

References: [requirements](02-requirements.md), [states](../architecture/03-state-machines.md), [payments](../integrations/01-payments.md).

## Table service

1. Waiter signs in, selects available Table 7, and opens a visit with four guests.
2. Waiter binds tablets to Guests 1–4. Customer mode shows the table and guest persistently; staff controls are locked.
3. Each guest browses real meal photos, opens a meal, changes permitted ingredients, and adds to their own cart.
4. Each guest reviews and submits independently. The backend checks binding, open bill, current prices, stock, removability, and any allergy review.
5. Accepted submission creates immutable order items and proposed charges. Eligible release posts charges and creates station work and a durable dispatch record; review-held work remains proposed until approval. The customer sees confirmed receipt or the explicit review hold, not optimistic success.
6. Kitchen receives a distinct ticket for each released submission. Kitchen completion alerts the waiter.
7. More items repeat steps 3–6 without resending previous items.
8. Guest requests their bill. Staff can assist and confirm shared-item allocations. Only that checkout's charges are frozen.
9. Cash, M-PESA, or external card settlement updates the selected guest balance. Other unpaid guests continue ordering under D02.
10. Waiter closes the visit after all balances settle and checks food service is resolved. Devices reset. Cash handover, if still due, remains separately owed by the waiter to the cashier.

## Kiosk with M-PESA

Choose language, then eat-in/takeaway; browse; customise; review; give collection name; resolve any allergy review; receive a server-priced checkout; enter phone and request payment. Keep one live attempt visible. On verified payment, release the same saved order once, allocate collection number, and print the paid receipt. Show the number even if printing fails, and direct the customer to staff for a reprint.

If payment is delayed, show the original reference and offer staff assistance. Abandoning the screen does not cancel a payment already in progress or expose its details to the next customer. A late success after reservation release enters staff review/refund handling instead of silently sending unavailable food to the kitchen.

## Kiosk with cash or card (D06)

Create a priced pending order and print/display an **UNPAID—PAY AT CASHIER** reference. Cashier retrieves it by code or QR, rechecks availability/expiry, takes payment, and releases it. Card is paid on the separate terminal, then manually recorded by cashier with amount/reference. Give the paid receipt and collection number. Never issue two food orders for the same pending reference.

## Shared platter (D01)

Guest 1 orders one KSh 2,400 platter. Default payer is Guest 1. Choosing Guests 1–4 creates proposed cost shares of KSh 600 each. Staff confirms allocation before checkout; affected guest screens show the pending proposal and then the confirmed charge. The kitchen always sees one platter. Do not allow an unauthorised guest to silently add debt to another guest. After any affected checkout starts, allocation is locked; correction requires safely cancelling pending checkout attempts or a recorded adjustment after payment.

## Allergy declaration (D09)

Guest explicitly asks for allergy assistance. Capture a minimal private note and notify waiter/kitchen lead. The order enters review, not preparation. A trained staff member checks actual recipe and preparation limits, records the decision and any customer-approved alternative. If unable to accommodate, decline those items without claiming safety. Kiosk payment starts only after approval. Ordinary dislikes use normal removal controls.

## Change after submission

Guest requests staff assistance. Manager-authorised change is evaluated against kitchen state. Before preparation, cancel/reissue affected work with a clear linked correction. During or after preparation, staff resolve service and accounting explicitly. Never erase a sent line or silently alter the cook's existing ticket. Preserve original item, adjustment, reason, actor, and payment/fiscal consequences.

## Staff handover

Waiter reports cash being handed over. Cashier counts it and accepts the amount, with both identities retained. Partial handover leaves the remainder in waiter custody. Differences become an exception for manager review; they never reduce customer payments or create fake discounts. At shift change, transfer open visits to another waiter and retain history.
