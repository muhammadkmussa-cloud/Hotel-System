# Component contracts

Components are a build inventory, not existing code. Customer components follow [design](../design/01-premium-design.md).

| Component | Inputs | Output / guard |
|---|---|---|
| SessionHeader | table label, guest label, mode, connection | Read-only identity; staff unlock action separate |
| CategoryRail | published categories, active ID | Select category; keyboard navigation |
| MealCard | photo variants, title, description, price, availability | Open details; disabled reason visible |
| MealHero | source variants, focal point, alt | Stable layout, error placeholder |
| IngredientOrbit | ingredient states, available bounds | Removal toggle; falls back to tray without lost labels |
| IngredientPortrait | image, name, included/fixed/removed | Explicit accessible state; no colour-only signal |
| IngredientDetails | composition, removability explanation | Read-only facts; no unsupported dietary claim |
| ModificationSummary | selected changes, extra amounts | Plain-language confirmation |
| QuantityControl | integer value, allowed bounds | Valid quantity; no negative/fractional portions unless designed |
| CartLine | meal snapshot, options, quantity, draft price | Edit own draft only |
| OrderReview | validated quote, cart version | Submit once with stable command key |
| OrderStatus | authoritative submission/task projection | Read-only progress; request help |
| GuestBillPanel | charges, shares, payments, due | Request own checkout; no payment confirmation control |
| ShareItemDialog | charge, eligible guests, proposed amounts | Proposal only; staff confirmation required |
| PaymentMethodPicker | capabilities and representable amount | Choose only currently available methods |
| MpesaPromptPanel | fixed amount, phone form, attempt state | One pending attempt; honest unknown state |
| CashierCardForm | checkout, amount, terminal reference | Cashier permission, duplicate reference protection |
| CashReceiptForm | amount due/tendered, change, actor | Records cash once; identifies custody |
| KitchenTicket | station items, table/guest, exclusions, age | Station-authorized transitions |
| CollectionTile | public number, ready state, approved name | No phone, item details, or payment data |
| PhotoUploader | file constraints, publication usage | Validated media record, rights/alt metadata |
| RecipeEditor | ingredient library, selected recipe, rules | Draft, preview, chef review, publication |
| ConnectionBanner | local/provider/event health | Explains what still works without implying submission |
| ErrorPanel | typed error, recoverable action | Safe retry retaining original command identity |

## Shared primitives

Button, icon button with accessible name, input with labelled error, checkbox, switch, dialog, drawer, tabs, money text, badge, table, skeleton, empty state, confirmation panel, and status message. Use one token source and consistent focus styling. Avoid duplicating these primitives per application mode.

## Composition tests

Meal detail with long compound ingredients; eight guest payment panels; three differently customised identical meals; missing images; offline draft; expired guest session; pending/unknown M-PESA; card confirmation denied to waiter; portrait kiosk keyboard. Ensure a modal never hides the payable amount or its own confirmation action.
