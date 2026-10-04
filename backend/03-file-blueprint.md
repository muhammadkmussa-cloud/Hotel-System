# Backend source-file blueprint

Planned PHP implementation paths aligned with [DirectAdmin deployment](../architecture/05-directadmin-layout.md). No executable source, dependencies, or migrations are created by this documentation update.

```text
hotel-app/                      # upload outside the domain document root
  composer.json
  composer.lock
  vendor/                       # locked production dependencies
  bootstrap.php
  config/                       # validated settings and private secret loading
  src/
    Http/
      Router.php
      Middleware/               # auth, CSRF, authorization, limits, request IDs
    Modules/
      Identity/
      Catalogue/
      Availability/
      Visits/
      Ordering/
      Billing/
      Payments/
      Cash/
      Fulfilment/
      Printing/
      Fiscal/
      Reporting/
      Audit/
      Delivery/
    Adapters/
      Mpesa/
      Fiscal/
      Printing/                 # authenticated bridge job API
    Support/
      Money.php
      Transitions.php
      Permissions.php
  views/                        # HTML; see frontend blueprint
  contracts/
    openapi.yaml                # create/validate during M1
    schemas/
  database/
    migrations/
    seeds/
  bin/
    run-jobs.php                # bounded cron batch, leases, retries
    migrate.php                 # controlled administrative operation
  storage/
    originals/
    logs/
    sessions/
    temporary/
public_html/
  index.php                     # pages, JSON API and provider callback routing
  .htaccess                     # host-tested routes and access controls
  assets/                       # see frontend blueprint
  media/                        # published raster files; no script execution
  service-worker.js
```

A domain module may contain `Routes.php`, `Validator.php`, `Service.php`, and `Repository.php`; add files as the behaviour requires. Repositories use PDO MySQL and prepared statements. Services own transactions and financial guards. Provider payloads stay inside adapters. Generated JavaScript contracts come from the validated API schema, not manually duplicated endpoint definitions.

Development-only PHPUnit/Playwright tests, contract-generation tools, and deployment notes stay outside the public upload. Build Composer dependencies locally when hosting lacks Composer/SSH, using the verified host PHP platform and extensions. Use DirectAdmin cron for scheduled work; do not assume a resident worker, containers, root privileges, or a Node.js server. Callbacks route directly to the hosted PHP application; no separate relay is part of the baseline.

The hotel-side printer bridge is separately installed on a supported local device and validated with real hardware. Its runtime/driver choice remains O04; it is not a second order or payment authority.
