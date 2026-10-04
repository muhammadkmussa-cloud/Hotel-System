# Contract examples

Synthetic examples; IDs and data are not production records. Money uses integer minor units. Schemas and examples become executable contract fixtures in M1.

## Submit own table order

`POST /api/v1/orders` with authenticated guest binding and `Idempotency-Key: demo-order-guest2-001`. Table/guest/channel are derived by the server.

```json
{
  "quoteId": "quote_demo_01",
  "cartVersion": 3,
  "items": [
    {
      "mealVersionId": "mealv_burger_02",
      "quantity": 1,
      "removedIngredientIds": ["ingredient_onion"],
      "extraOptionIds": [],
      "reviewRequested": false
    }
  ]
}
```

```json
{
  "data": {
    "id": "order_demo_104",
    "tableLabel": "7",
    "guestLabel": "Guest 2",
    "state": "released",
    "total": {"amountMinor": 85000, "currency": "KES"},
    "version": 1
  },
  "requestId": "req_demo_01"
}
```

Required validations: at least one item; positive bounded integer quantity; quote belongs to principal, unexpired, with identical choices; selected removal exists and is removable in that meal version; bill still open. Supplied identity/price fields are rejected.

## Create own checkout

```json
{
  "billId": "bill_guest2_demo",
  "expectedVersion": 5
}
```

Backend returns checkout ID, frozen bill version, amount, permitted methods, and reservation/expiry details. Staff may select multiple bills with explicit authority for a whole-table payer; guests cannot name unrelated bills.

## M-PESA attempt

`POST /api/v1/checkouts/checkout_demo_01/mpesa-attempts`

```json
{"phone": "+254700000000"}
```

```json
{
  "data": {
    "id": "attempt_demo_01",
    "state": "pending",
    "amount": {"amountMinor": 85000, "currency": "KES"},
    "phoneMasked": "+2547*****000",
    "message": "Complete the payment prompt on your phone."
  },
  "requestId": "req_demo_02"
}
```

This response is 202 Accepted, not proof of payment. Amount comes from checkout. Never request or accept a customer M-PESA PIN in this system.

## Cashier records external card

```json
{
  "amountMinor": 85000,
  "terminalReference": "DEMO-TERMINAL-001",
  "confirmedSuccessful": true
}
```

Only payments.card principal can perform this. Server compares amount with due balance, rejects reused reference, records confirmer and time, and never stores PAN/CVV/PIN. A waiter receives 403 even when the form is invoked outside the UI.

## Cash collection

```json
{
  "tenderedMinor": 100000,
  "expectedDueMinor": 85000,
  "custody": "collecting_staff"
}
```

Server validates current checkout, records KSh 850 applied, KSh 150 change, and the authenticated staff identity. Cashier collection goes directly to an active drawer; waiter collection goes to waiter custody. Handover later does not create another payment.

## Shared platter proposal

```json
{
  "participantGuestIds": ["guest_demo_1", "guest_demo_2", "guest_demo_3", "guest_demo_4"],
  "allocationMode": "equal",
  "expectedChargeVersion": 1
}
```

Participants must belong to the same active visit. The proposal is not posted until authorized staff confirms. Server computes allocations and rounding, shows them before confirmation, and rejects changes affecting locked/settled amounts.
