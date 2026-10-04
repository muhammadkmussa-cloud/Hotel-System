# Printing and hardware

## Proposed pilot equipment

Four customer tablets, one portrait touch kiosk, one cashier workstation, one kitchen display, kitchen ticket printer, customer receipt printer, reliable local router/access points, local hub, power backup, and secure backup destination. Collection display can be a dedicated screen or pilot display. This is a test arrangement, not a purchase list with verified model compatibility.

The same tablet may be reassigned between guests/visits by staff. Use kiosk/managed-device mode to prevent customers leaving the ordering app. Charging, cleaning, tablet stands, theft protection, touch reach, cable routes, and printer paper access are part of installation acceptance.

## Printing architecture

Use a restricted local print bridge with tested printer adapters. Browser print dialogs are not an adequate unattended kiosk receipt solution. The hub creates immutable jobs after the relevant transaction commits; the bridge sends to allowlisted destinations and reports transport status.

Choose supported printer models only after verifying OS/driver or ESC/POS compatibility, character rendering, cutter behaviour, paper width, QR readability, network loss, and recovery. Do not assume all 'thermal printers' accept identical commands. Kitchen environment may require a different printer technology from the customer receipt printer.

## Ticket content

Kitchen: source channel, submission reference, time, table/guest or kiosk number, dining option, item quantities, approved preparation changes, review alert where authorized, and station. Emphasize exclusions in clear text. Do not print every ingredient by default when it obscures changes; provide recipe access separately.

Customer unpaid slip: reference, items, amount, **UNPAID—PAY AT CASHIER**, optional lookup QR. Paid receipt: hotel identity, items/modifications, gross amount, payment method/status, order reference, collection number or table/guest, and required fiscal fields from the accepted integration. Never label a pending fiscal document as accepted.

Shared-item receipts show the guest's charge allocation; kitchen quantity remains one. Reprint retains original identifiers and displays COPY. Printer paper exhaustion must not resubmit the order or take another payment.

## Delivery ambiguity

A successful socket write may not prove that paper emerged. Model unknown outcomes explicitly. Avoid unlimited automatic retry after a possibly printed job. Staff inspect printer or request a labelled copy. The kitchen display is the recoverable record of work, while printed tickets remain individually traceable.

## External card terminal

No application connection in v1. Verify its independent operational setup with the hotel/provider. Cashier enters only a transaction reference and successful amount; the application does not capture card data. Any future integrated terminal is a separate scoped project.
