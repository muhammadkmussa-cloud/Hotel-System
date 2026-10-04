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
| [MDN service workers](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API) | Service-worker caching and secure-context requirements | HTTPS and scoped caching on local devices |
| [PHP PDO](https://www.php.net/manual/en/book.pdo.php) | PHP database interface with driver-specific access | PDO MySQL and backend-controlled transactions |
| [Composer](https://getcomposer.org/doc/00-intro.md) | PHP dependency management | Locked dependencies and autoloading |
| [MySQL InnoDB](https://dev.mysql.com/doc/refman/8.4/en/innodb-introduction.html) | Transactions, row locking, foreign keys | Financial and availability concurrency |
| [MySQL recovery](https://dev.mysql.com/doc/refman/8.4/en/point-in-time-recovery.html) | Point-in-time recovery uses backup and binary logs | Confirm provider access before promising recovery targets |
| [JavaScript modules](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Modules) | Native module organisation | Plain JavaScript frontend without a required framework |
| [PHP cURL](https://www.php.net/manual/en/book.curl.php) | HTTP transfer support | Server-side provider adapters |
| [PHP GD](https://www.php.net/manual/en/book.image.php) | Image processing support | Proposed raster derivatives; validate host formats |
| [PHPUnit](https://phpunit.de/documentation.html) | PHP test-tool documentation | Match test version to selected PHP platform |
| [Playwright](https://playwright.dev/docs/intro) | Browser testing with JavaScript support | Multi-device journeys; development tooling only |
| [DirectAdmin website locations](https://docs.directadmin.com/getting-started/first-steps/faq.html) | Per-domain website directory convention | Verify actual domain public root |
| [DirectAdmin web configuration](https://docs.directadmin.com/webservices/apache/customizing.html) | Document-root overrides and PHP filesystem restrictions | Verify both HTTP/HTTPS roots and access to private application folders |
| [DirectAdmin PHP selection](https://docs.directadmin.com/webservices/php/multiple-php.html) | Domain PHP version selection | Verify web and CLI compatibility on selected host |
| [DirectAdmin configuration](https://docs.directadmin.com/directadmin/general-usage/all-directadmin-conf-values.html) | User cron configuration and PHP path controls | Bounded scheduled work; host capability gate |
| [DirectAdmin backups](https://docs.directadmin.com/directadmin/backup-restore-migration/index.html) | Backup/restore scope and scheduling options | Check account-level access, data coverage, and restore rehearsal |

## Conclusions versus evidence

PHP, HTML/CSS/JavaScript, MySQL, and DirectAdmin are user-confirmed choices C19–C21/C23. Supporting tools, polling/cron design, design palette, sharing default, counter collection, and build sequence remain project recommendations, not vendor prescriptions. The previous on-site hub and framework stack have been superseded. The ingredient orbit is a feature to validate, not a proven market novelty or a demonstrated usability improvement. Hotel sales growth, staffing savings, build cost, and delivery dates have not been estimated from evidence and are not promised.

## Build-start version references — 4 October 2026

- [Laravel 13.34.0 release](https://github.com/laravel/framework/releases/tag/v13.34.0): checked stable framework target; dependency resolution/lockfile still required.
- [Laravel release policy](https://laravel.com/framework/docs/releases): Laravel 13 supports PHP 8.3–8.5; local PHP 8.3.30 meets the minimum, not proof of host compatibility.
- [MySQL GA downloads](https://dev.mysql.com/downloads/mysql/): current general download lists 26.7.0.
- [MySQL Docker security patch](https://dev.mysql.com/doc/relnotes/mysql/26.10/en/news-26-7-1.html): 26.7.1 applies to Docker images; select the appropriate patched artifact if used.
- [MySQL 26.10 notes](https://dev.mysql.com/doc/relnotes/mysql/26.10/en/news-26-10-0.html): early access, excluded from stable target.
- [Laravel structure](https://laravel.com/framework/docs/13.x/structure) and [deployment](https://laravel.com/framework/docs/13.x/deployment): conventional private application/public-root separation. DirectAdmin-installed software remains unverified.
