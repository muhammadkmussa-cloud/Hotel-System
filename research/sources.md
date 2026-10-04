# Research sources and evidence boundaries

Restaurant research reviewed 3 October 2026; technical references and KRA integration page checked 4 October 2026. Pages may change. Recheck vendor terms and supported versions before implementation. No vendor demo, provider activation, hardware benchmark, or user study was performed for this documentation.

| Source | Documented fact used | Project implication |
|---|---|---|
| [Lightspeed table service](https://k-series-support.lightspeedhq.com/hc/en-us/articles/360051089273-Adding-orders-in-Table-Service-mode) | Table/seat grouping and additional-order dispatch | Table visits, guest assignment, incremental tickets |
| [Lightspeed check splitting](https://k-series-support.lightspeedhq.com/hc/en-us/articles/360051089493-Check-splitting) | Seat/shared-item splitting and possible confusing fractions | Explicit allocation labels, no accidental dish multiplication |
| [Square bill splitting](https://squareup.com/help/us/en/article/8165-split-a-payment-and-check-with-square-for-restaurants) | Separate item/seat bills and divided item charges | Guest bills and optional shared dishes |
| [Toast kiosk workflow](https://support.toasttab.com/en/article/Kiosk-Placing-Orders-Making-Payments-and-Tipping) | Dining choice, review, payment and cashier cash route | Separate prepaid kiosk and unpaid cashier reference |
| [Toast modifiers](https://support.toasttab.com/en/article/Creating-Modifier-Groups-and-Modifiers-1492803987509) | Configured meal modifications and default choices | Recipe plus allowed changes, presented visually |
| [Square kitchen routing](https://squareup.com/help/us/en/article/7959-route-orders-with-your-kds) | Multiple order sources route into kitchen displays | Shared kitchen backend with source labels |
| [Toast shift review](https://dev.toasttab.com/doc/platformguide/platformCompletingShiftReview.html) | Cash held by staff and cash reconciliation | Separate payment from staff custody transfer |
| [Toast offline operation](https://support.toasttab.com/en/article/Using-Toast-in-Offline-Mode) | Offline behaviour depends on network/equipment; features differ | Test exact outage modes, not a blanket offline claim |
| [Kiotapay restaurant page](https://kiotapay.co.ke/solutions/restaurant-pos-system-kenya) | Vendor advertises local payments, kitchen routing and offline features | Kenyan market context only; performance not independently verified |
| [Safaricom API catalogue](https://developer.safaricom.co.ke/apis) | M-PESA Express and business payment APIs | Candidate direct M-PESA adapter |
| [Paystack payment channels](https://paystack.com/docs/payments/payment-channels/) | M-PESA phone prompts; channel-specific fields | Alternative provider; check email/amount/account constraints |
| [Paystack verification](https://paystack.com/docs/payments/verify-payments/) | Server-side transaction status verification | Do not equate initiation with payment |
| [Paystack webhooks](https://paystack.com/docs/payments/webhooks/) | Provider-specific notification verification | Verify authenticity; do not assume all providers use same scheme |
| [Paystack Kenya pricing](https://paystack.com/ke/pricing) | Public charges and settlement terms | Commercial validation before provider selection; no frozen price promise |
| [KRA eTIMS integration](https://www.kra.go.ke/business/etims-electronic-tax-invoice-management-system/learn-about-etims/etims-system-to-system-integration) | System integration and certification/integrator routes | Fiscal readiness gate, no self-declared compliance |
| [Food Standards Agency](https://www.food.gov.uk/business-guidance/allergen-guidance-for-food-businesses?c=Linux) | Recipe/allergen records and cross-contact precautions | Safety-informed design; not a statement of Kenyan legal requirements |
| [W3C target size](https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum) | Operable control size/spacing requirements | Minimum interaction geometry and alternative list |
| [Vite guide](https://vite.dev/guide/) | Supported project setup and runtime constraints | Pin compatible frontend tooling at implementation |
| [Fastify validation](https://fastify.dev/docs/latest/Reference/Validation-and-Serialization/) | Schema-based request/response handling | Contract-driven server boundary validation |
| [PostgreSQL locking](https://www.postgresql.org/docs/current/explicit-locking.html) | Row locking and concurrency behaviour | Financial/availability transaction design |
| [MDN service workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API) | Service-worker caching and secure-context requirements | HTTPS and scoped caching on local devices |

## Conclusions versus evidence

The proposed stack, design palette, local hub, sharing default, counter collection, and build sequence are project recommendations, not claims that these vendors prescribe those choices. The ingredient orbit is a feature to validate, not a proven market novelty or a demonstrated usability improvement. Hotel sales growth, staffing savings, build cost, and delivery dates have not been estimated from evidence and are not promised.
