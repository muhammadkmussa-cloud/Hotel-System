# Frontend architecture

Status: HTML/CSS/JavaScript confirmed C20; supporting structure proposed D16/D17. See [screen inventory](../product/04-screen-map.md) and [source blueprint](03-file-blueprint.md).

## Application modes

One shared HTML/CSS/plain-JavaScript codebase provides table, kiosk, staff, kitchen, collection, and administration layouts. On-demand native JavaScript modules keep customer loading light. The backend supplies the current principal, device mode, visit/guest binding, and permitted capabilities. A URL or hidden button is never an authorization boundary.

Customer flows share catalogue and meal-customisation components, but differ in submission: table orders release without prepayment; kiosk orders require a checkout/payment gate. Do not spread channel-dependent conditionals through every visual component; route containers call distinct application use cases.

## State ownership

Server-query cache: published menu, availability, submitted orders, guest bills, checkout/payment states, permissions. Local DOM component/form state: open drawer, selected ingredient draft, filter, unsent input. Recoverable local draft storage: own cart under current session, with expiry. No frontend balance calculation may override server values.

An add-to-cart update may be immediate because it is a draft. Submission, payment, cancellation, and closure need server confirmation. On uncertain network outcome show 'Checking whether your order was received' and recover via command key; never tell the guest to submit a fresh order blindly.

## Authentication surfaces

Staff use individual authenticated sessions with secure HttpOnly cookies, same-origin deployment, CSRF protection, and inactivity lock. Enrolled devices receive scoped device identity. Staff pairing establishes guest sessions without customer accounts. The customer cookie does not retain staff privileges after the waiter switches modes.

Provider secrets and personal payment data are not stored in browser storage. A phone field is masked after submission, cleared after session end, and never sent to analytics.

## Data and error handling

Generate a browser-compatible JavaScript client contract from the machine-readable API specification created in M1, with optional JSDoc hints. The central Fetch client handles request ID, CSRF, idempotency, cancellation, and structured errors. Server-side validation remains authoritative. Render business errors in context: unavailable item, changed price, closed visit, payment already pending, permission denied. Do not collapse them into a generic toast.

Short authenticated polling with cursors invalidates/refetches scoped views; no permanent connection is required. Full screen reload restores the authoritative order/bill, not a cached optimistic guess. Include a persistent connectivity indicator and distinguish browser connectivity, hosted application failure, and external provider failure.

## Performance and testing

Use explicit image dimensions, responsive variants, same-origin font hosting, on-demand JavaScript modules, and measured initial payloads. Do not add animation libraries solely for orbit placement. Test core flows with real backend contracts, a simulator for payment outcomes, and representative images. Visual regression screenshots need review before updating baselines.

Localization, price formatting, form accessibility, and empty/error/loading states are shared concerns. Kitchen and collection layouts have independent density and privacy rules.
