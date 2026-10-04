# DirectAdmin deployment and file layout

Status: DirectAdmin is confirmed C23. This is a planned layout, not created application files. Assume ordinary shared DirectAdmin hosting until O13 records the actual account capabilities.

## Account layout

The account home below is illustrative; discover the actual home and domain document root during installation. `domains/DOMAIN/public_html` is the typical domain web root. Confirm the HTTP and HTTPS roots, including any subdomain override. `private_html` may serve HTTPS content or link to `public_html`; despite its name it is not private application storage. The repository will contain matching `hotel-app/` and `public_html/` folders for upload mapping.

```text
/home/DIRECTADMIN_USER/domains/DOMAIN/
  hotel-app/                    # private; outside every public document root
    composer.json
    composer.lock
    vendor/                     # production PHP dependencies
    config/                     # private settings; secret values never committed
    bootstrap.php
    src/
      Http/                     # routing, sessions, authorization, validation
      Modules/                  # identity, catalogue, ordering, billing, etc.
      Adapters/                 # payments, fiscal, print transport contracts
      Support/                  # exact money, clock, logging, identifiers
    views/                      # HTML templates; escape dynamic output
    contracts/                  # validated API schema at implementation
    database/
      migrations/               # reviewed MySQL schema changes
      seeds/                    # demo-only, never loaded into live by default
    bin/
      run-jobs.php              # bounded CLI entry for DirectAdmin cron
      migrate.php               # controlled deployment operation
    storage/
      originals/                # private uploaded images awaiting processing
      logs/
      sessions/
      temporary/
  private_html/                 # HTTPS root or link to public_html; no secrets
  public_html/                  # domain document root; only public entry/assets
    .htaccess                   # routing/access rules if host supports them
    index.php                   # entry for pages, /api/v1 and /callbacks routes
    assets/
      css/
      js/
      images/                   # application-owned icons/placeholders
      fonts/
      locales/
    media/                      # validated published raster derivatives only
    service-worker.js           # public assets only; scope/update policy
```

Tests, developer tools, Git metadata, this documentation, SQL exports, backup copies, and development secrets are excluded from the public deployment. Backup staging, if needed, is private and short-lived; durable backups go to the approved off-account destination. The local printer agent is a separate hotel-side installation, not a PHP file run by DirectAdmin.

## Routing and access rules

Use same-origin pages, assets, and `/api/v1` routes. The public entry loads PHP logic from the private directory through a configured filesystem path. Confirm the host web server (Apache, Nginx, or LiteSpeed family); `.htaccess` support must not be assumed, especially on Nginx-only hosting. Arrange equivalent provider-managed routing/access rules if needed. Use reviewed rewrite rules to preserve the routes in the screen map and API catalogue; validate deep links, asset URLs, callback delivery, and unknown-route handling on the chosen host. Do not create an unauthenticated URL for the CLI job runner or migrations.

Only the public entry is intended to execute PHP. Block script execution in writable media paths and disable directory listing. Deny dotfile, SQL, backup, and configuration downloads as defence in depth; private placement remains the main control. A deployment that exposes `hotel-app/` through any other domain or symlink fails acceptance. Set least-privilege permissions compatible with the account's PHP handler; do not prescribe world-writable folders.

## Host capability gate (O13)

Before choosing the package or beginning deployment, verify:

- Supported PHP version for both web requests and CLI cron; PDO MySQL, cURL, fileinfo, multibyte support, and GD with required image formats; secure session/password functionality.
- Actual **MySQL** service and version with InnoDB, not a silent substitution by a host offering only MariaDB; database/user naming prefixes, connection limits, storage quota, and privileges.
- HTTPS certificate issuance/renewal, domain document root, routing support, private-directory access from PHP including open_basedir restrictions, and upload/body/execution/memory limits.
- Cron availability, allowed frequency, PHP executable path, bounded runtime, overlapping-run limits, and outbound HTTPS access to chosen providers. Proposed one-minute recovery runs depend on the provider's policy; measure user-facing latency separately.
- CPU/concurrent-request/database quotas under expected polling load; backup export/restore and retention; availability of binary logs if point-in-time recovery is required.
- SSH/Terminal and Composer availability. If absent, prepare production dependencies on a compatible development machine and upload the complete release. Apply migrations only through an approved administrative deployment process, never an open web installer.

A hosting plan that fails essential capabilities is unsuitable. Keep C23 and choose a suitable DirectAdmin plan; do not silently weaken payment, privacy, or recovery requirements.

## Scheduled work and printing

Cron launches bounded PHP batches using absolute private paths. Each run leases due jobs in MySQL, limits work/time, releases or expires leases safely, and records a heartbeat. Concurrent runs must not duplicate business effects. The user closing a browser must not stop recovery. Provider timeouts retain original references; cron lateness appears in operational health.

Kitchen screens poll committed records. The hotel print bridge polls a dedicated HTTPS queue, leases jobs for its registered station, prints to allowlisted local hardware, and reports results. No incoming printer port forwarding is required. Keep ambiguous physical outcomes distinct from failed transport; a recovery copy retains the original order identity.

## Official hosting references

DirectAdmin documents [website locations](https://docs.directadmin.com/getting-started/first-steps/faq.html), [document-root overrides and PHP filesystem restrictions](https://docs.directadmin.com/webservices/apache/customizing.html), [PHP version selection](https://docs.directadmin.com/webservices/php/multiple-php.html), [cron configuration](https://docs.directadmin.com/directadmin/general-usage/all-directadmin-conf-values.html), and [backup/restore](https://docs.directadmin.com/directadmin/backup-restore-migration/index.html). The provider controls available account features. These references support the hosting mechanisms; the separation and job design above are project recommendations.
