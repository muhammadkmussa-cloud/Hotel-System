# DirectAdmin deployment and file layout

Status: DirectAdmin is confirmed C23. The local Laravel scaffold exists; the full deployment layout below remains planned and has not been installed on a hosting account. Assume ordinary shared DirectAdmin hosting until O13 records the actual account capabilities.

## Domain-independent installations

C26: each owner supplies their own domain during installation. There is no project-wide production domain, central registration service or shared hotel database. Keep the application origin in private per-installation configuration (`APP_URL`), with same-origin relative browser paths. Do not hardcode an owner's hostname in source, assets or provider callback definitions. Each installer must verify trusted origin/host handling, HTTPS and the actual DirectAdmin capabilities before deployment; arbitrary request Host headers must not become trusted callback origins. Local development may use loopback without any purchased domain or hosting account.

## Account layout

The account home below is illustrative; discover the actual home and domain document root during installation. `domains/DOMAIN/public_html` is the typical domain web root. Confirm the HTTP and HTTPS roots, including any subdomain override. `private_html` may serve HTTPS content or link to `public_html`; despite its name it is not private application storage. Keep the standard Laravel project under `hotel-app/`; map only its `public/` directory to the actual domain document root. The frontend blueprint uses `public_html/` to describe deployed public paths, not a second source tree.

```text
/home/DIRECTADMIN_USER/domains/DOMAIN/
  hotel-app/                    # Laravel root; private
    artisan
    composer.json
    composer.lock
    .env                        # private; excluded from version control
    app/                        # controllers, policies, actions, models, jobs
    bootstrap/                  # app.php and private writable cache
    config/
    routes/                     # web/API/console routes
    resources/views/            # Blade produces ordinary HTML
    contracts/                  # eventual machine-readable API schema
    database/                   # migrations/factories/seeders
    vendor/                     # locked production PHP dependencies
    storage/                    # private originals, logs, sessions, views/cache
    public/                     # only this content is web-accessible
      index.php
      .htaccess                 # host-dependent routing/access rules
      assets/                   # CSS, native JS, approved images/fonts/locales
      media/                    # published raster derivatives only
      service-worker.js
  public_html/                  # mapped to hotel-app/public as allowed by host
  private_html/                 # HTTPS root or link; never secret storage
```

Tests, developer tools, Git metadata, this documentation, SQL exports, backup copies, and development secrets are excluded from the public deployment. Backup staging, if needed, is private and short-lived; durable backups go to the approved off-account destination. The local printer agent is a separate hotel-side installation, not a PHP file run by DirectAdmin.

## Routing and access rules

Use same-origin pages, assets, and `/api/v1` routes. Prefer a provider-supported document-root or public_html symlink mapping to `hotel-app/public`, preserving Laravel's normal entry paths. If the host permits only copying public files to a fixed public_html directory, explicitly adapt the entry's private bootstrap/autoload paths and Laravel public-path setting together; verify asset URLs, maintenance files, HTTP/HTTPS roots, and CLI behaviour on that host. Never move/expose the Laravel project root to solve routing. Confirm the host web server (Apache, Nginx, or LiteSpeed family); `.htaccess` support must not be assumed, especially on Nginx-only hosting. Arrange equivalent provider-managed routing/access rules if needed. Use reviewed rewrite rules to preserve the routes in the screen map and API catalogue; validate deep links, asset URLs, callback delivery, and unknown-route handling on the chosen host. Keep Artisan CLI-only; do not create an unauthenticated web endpoint for job runners or migrations.

Only the public entry is intended to execute PHP. Block script execution in writable media paths and disable directory listing. Deny dotfile, SQL, backup, and configuration downloads as defence in depth; private placement remains the main control. A deployment that exposes `hotel-app/` through any other domain or symlink fails acceptance. Set least-privilege permissions compatible with the account's PHP handler; do not prescribe world-writable folders.

## Local scaffold mapping review — P01.10

Reviewed on 4 October 2026 against the official references below and the actual local files. This is local evidence only, not DirectAdmin deployment acceptance.

| Source path | Deployment destination / exposure | Current evidence |
|---|---|---|
| hotel-app/public/index.php | Domain public root; only PHP entry | Resolves private vendor/bootstrap and maintenance paths relative to the Laravel root |
| hotel-app/public/assets/ | Public CSS and native JavaScript | Two static files; both served successfully |
| hotel-app/.env, app/, bootstrap/, config/, routes/, resources/, vendor/, storage/, artisan and Composer files | Private Laravel root outside every public root | Placement inspected; none lies under public/ |
| storage/app/private | Private original uploads in later image work | Filesystem disk has serve=false; upload processing is not implemented |
| public/media | Future published, validated raster derivatives only | Not created; upload validation and script-execution restrictions remain P08/P29 work |
| public_html and any HTTPS/private_html mapping | Resolve only to hotel-app/public | Temporary local symlink simulation passed; real host permissions and both HTTP/HTTPS roots remain O13/P29 checks |

The public source tree contains exactly index.php, assets/css/global.css and assets/js/main.js, with no symlinks to private data. No .htaccess, production web-server rules, service worker, media publication or copy-mode adapter is implemented yet. Laravel's local server router is test tooling and does not establish Apache/Nginx/LiteSpeed routing or dotfile/backup protection on the real host.

A temporary public_html → public and private_html → public_html simulation served the home page and assets, and denied six private paths (.env, artisan, composer.lock, bootstrap/app.php, storage/logs/laravel.log and vendor/autoload.php). Both mappings used plain loopback HTTP; this does not test TLS or actual DirectAdmin. Temporary mappings and servers were removed. The first harness attempt could not find PHP under a restricted PATH, and a subsequent one-second startup timeout was too short; rerunning with the resolved executable and ten-second request timeout passed. No application changes were needed.

Installers must resolve every actual domain/subdomain/HTTPS document root, confirm none exposes the private root, verify provider routing/open_basedir and allowed mapping method, then exercise private-file denial on the actual web server. A fixed-public_html copy deployment needs a separately reviewed entry/public-path adaptation; the current source supports the unchanged Laravel public-directory mapping only. Do not copy this entire repository into public_html.

## Host capability gate (O13)

Before choosing the package or beginning deployment, verify:

- Laravel 13-compatible PHP 8.3+ for both web requests and CLI cron; PDO MySQL, cURL, fileinfo, multibyte support, and GD with required image formats; secure session/password functionality.
- Actual **MySQL** service and version with InnoDB, not a silent substitution by a host offering only MariaDB; database/user naming prefixes, connection limits, storage quota, and privileges.
- HTTPS certificate issuance/renewal, domain document root, routing support, private-directory access from PHP including open_basedir restrictions, and upload/body/execution/memory limits.
- Cron availability, allowed frequency, PHP executable path, bounded runtime, overlapping-run limits, and outbound HTTPS access to chosen providers. Proposed one-minute recovery runs depend on the provider's policy; measure user-facing latency separately.
- CPU/concurrent-request/database quotas under expected polling load; backup export/restore and retention; availability of binary logs if point-in-time recovery is required.
- SSH/Terminal and Composer availability. If absent, prepare production dependencies on a compatible development machine and upload the complete release. Apply migrations only through an approved administrative deployment process, never an open web installer.

A hosting plan that fails essential capabilities is unsuitable. Keep C23 and choose a suitable DirectAdmin plan; do not silently weaken payment, privacy, or recovery requirements.

The Laravel root, .env, storage/framework, bootstrap/cache, and original uploads must remain outside all web roots. Verify writable framework directories and private-file access under the host's open_basedir policy. Standard public storage symlinks must never expose originals or private customer/provider data. See [Laravel deployment guidance](https://laravel.com/framework/docs/13.x/deployment).

## Scheduled work and printing

Cron launches bounded Laravel Artisan commands using the verified PHP executable and absolute private artisan path; no permanent queue process is assumed. Each run leases due jobs in MySQL, limits work/time, releases or expires leases safely, and records a heartbeat. Concurrent runs must not duplicate business effects. The user closing a browser must not stop recovery. Provider timeouts retain original references; cron lateness appears in operational health.

Kitchen screens poll committed records. The hotel print bridge polls a dedicated HTTPS queue, leases jobs for its registered station, prints to allowlisted local hardware, and reports results. No incoming printer port forwarding is required. Keep ambiguous physical outcomes distinct from failed transport; a recovery copy retains the original order identity.

## Official hosting references

DirectAdmin documents [website locations](https://docs.directadmin.com/getting-started/first-steps/faq.html), [document-root overrides and PHP filesystem restrictions](https://docs.directadmin.com/webservices/apache/customizing.html), [PHP version selection](https://docs.directadmin.com/webservices/php/multiple-php.html), [cron configuration](https://docs.directadmin.com/directadmin/general-usage/all-directadmin-conf-values.html), and [backup/restore](https://docs.directadmin.com/directadmin/backup-restore-migration/index.html). The provider controls available account features. These references support the hosting mechanisms; the separation and job design above are project recommendations.
