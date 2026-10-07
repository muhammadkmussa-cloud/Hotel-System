# Hotel System application

Laravel 13 restaurant ordering and POS for **one hotel per installation**: table tablets with a per-guest menu and ingredient customiser, a walk-in kiosk, kitchen board, collection display, waiter tools, cashier checkout (cash, external card terminal record, M-PESA), drawers, discounts, refunds, receipts, reports, audit, backups and administration. What is built, how it was verified and what remains before a pilot are recorded in [delivery/09-implementation-status.md](../delivery/09-implementation-status.md).

Keep this entire directory private on DirectAdmin. Expose **only `public/`** through the provider-supported domain document root or public_html mapping described in [the deployment specification](../architecture/05-directadmin-layout.md).

## Run the demo

Requirements: PHP 8.3+ (pdo_sqlite, gd, zip, intl, mbstring), Composer 2.

```sh
cd hotel-app
scripts/dev-demo.sh --serve     # creates .env (SQLite), installs vendor, resets + seeds, serves on 0.0.0.0:8000
```

The demo is in **TEST MODE**: receipts say so, M-PESA uses the built-in simulator and fiscal documents are labelled “SIMULATED — not a tax invoice”.

| Who | Sign in at `/staff/sign-in` | Password |
|---|---|---|
| Owner, manager, cashier, waiter, kitchen lead, kitchen staff, menu editor, auditor | `owner@demo.test`, `manager@demo.test`, `cashier@demo.test`, `waiter@demo.test`, `kitchen-lead@demo.test`, `kitchen-staff@demo.test`, `menu-editor@demo.test`, `auditor@demo.test` | `demo-password-2026` |

Devices (tablets, kiosk, kitchen screen, collection display) are paired by opening `/device/pair` on the device and entering a code. The seeder prints codes valid for 15 minutes; issue new ones in **Admin → Devices**.

A typical walkthrough:

1. Pair a tablet, then as **waiter** open a visit on *Table 1*, add a guest and bind the tablet to that guest.
2. On the tablet, pick a dish, remove or add ingredients, add to cart and send the order. Add a note to see the allergy review hold.
3. Pair a kitchen screen and move the ticket through *preparing → ready → served*.
4. As **cashier**, open a drawer, check the guest out with cash/card/M-PESA and print the receipt.
5. As **waiter**, close the visit once every balance is zero.
6. Pair a kiosk for the walk-in prepaid journey; simulated M-PESA phones ending `111` (insufficient funds), `222` (cancelled) and `333` (timeout) fail on purpose, any other number succeeds.

The simple built-in server must use the router so static assets bypass Laravel: `php -S 0.0.0.0:8000 -t public scripts/dev-router.php`.

## Tests

```sh
php vendor/bin/phpunit                # 132 tests (feature suite); tests/Database/* need MySQL
tests/e2e/run.sh                      # with the server running: resets + seeds the demo DB, drives every HTTP flow (python3 + requests)
```

## Operating an installation

| Task | Command |
|---|---|
| Background jobs (M-PESA reconciliation, fiscal queue, kiosk expiry, print leases) | cron: `* * * * * cd /path/to/hotel-app && php artisan schedule:run >> /dev/null 2>&1` |
| Run jobs once, verbosely | `php artisan hotel:run-jobs -v` |
| Backup now (also scheduled daily 03:30, keeps 14) | `php artisan hotel:backup` |
| Verify / restore a backup | `php artisan hotel:restore <file>` (dry run) then `--force` (takes a safety backup first) |
| Migrations | `php artisan app:migrate --force` |
| First owner (production) | `php artisan app:bootstrap-owner` or the one-time `/setup` page |

Backup format 2 archives are authenticated with this installation's private `APP_KEY`; preserve that key separately with the recovery material or the archive cannot be trusted/restored. Never replace or regenerate an existing installation key during routine maintenance. Legacy unsigned format 1 archives are intentionally rejected and must not be treated as recovery evidence. Archive table names, media paths, entry counts and expanded sizes are bounded before restore.

Production settings: `DB_DRIVER=mysql`; use `MPESA_MODE=sandbox` only for Safaricom sandbox verification and `MPESA_MODE=production` only after that verification and an approved live cutover; keep `FISCAL_ENABLED=false` until a certified eTIMS integration exists. The sections below document the foundation steps in detail.

## Reproduce the dependency foundation

Requirements: PHP 8.3–8.5 with the extensions listed in composer.json, and Composer 2. Dependency resolution uses PHP 8.3.30 as the minimum tested development platform. DirectAdmin installations must separately check their real PHP/extensions with `composer check-platform-reqs`; the platform setting is not proof of host compatibility.

From this directory:

```sh
composer install --no-interaction --no-scripts --no-plugins --prefer-dist
composer validate --no-check-publish
composer check-platform-reqs
php artisan --version
```

Keep composer.json and composer.lock together. The lockfile pins 74 production packages and 25 development-only packages; Laravel is explicitly pinned to 13.34.0. Dependency changes require a separate reviewed update and security check. Composer plugins are disabled and no project scripts are configured. `vendor/` and generated runtime caches remain untracked; no database or .env is created by these commands.

Verified locally on 4 October 2026: dependency installation and platform checks passed; CLI printed Laravel Framework 13.34.0. The install-time advisory check and a fresh network-enabled `composer audit --locked` both reported no known security advisories; the fresh audit also reported no abandoned packages or filter findings. A subsequent offline install dry run had no changes; its remote filter-list refresh was unavailable, so it is reproducibility evidence only, not a fresh security check. Composer validation reports two expected warnings: the explicit framework pin and the absent project licence. O12 licensing terms remain undecided; do not invent a distribution licence.

No MySQL server connection, provider, physical hardware or deployment has been tested. The local home page is verified separately below.

## Private installation configuration

For a new local installation, copy `.env.example` to `.env` **only if `.env` does not already exist**. Keep it at the private application root. Set APP_ENV and APP_URL for this installation, then run:

```sh
php artisan key:generate
php artisan app:check-config
php tests/configuration.php
```

The example uses local loopback, not a production domain. Each production installation sets APP_ENV=production (or staging for staging) and its own HTTPS origin in APP_URL. Only an origin is accepted: no login credentials, path other than `/`, query or fragment. Local/testing may use HTTP with localhost, 127.0.0.1 or [::1]. Generate a separate private encryption key per installation; never copy another hotel's key or rerun key generation on an existing live installation as a routine upgrade.

Laravel loads `.env` privately; externally supplied environment settings can take precedence. Required APP_URL and APP_KEY are checked by `app:check-config` (exit 0/1) and before every application HTTP route. The private command identifies invalid setting names without printing values. Unconfigured HTTP requests receive a generic, non-cacheable 503 response. Malformed environment-file syntax is also redacted, including parser errors that could contain secret values. Detailed browser errors are always disabled in this foundation, even if APP_DEBUG=true is supplied. Local logs remain private.

The URL used internally to bootstrap setup commands falls back to synthetic localhost for malformed/missing URLs; the guard still rejects the missing/invalid raw APP_URL, so this is never permission to serve an unconfigured installation. Origin configuration is not yet host-header/proxy enforcement; those request safeguards belong to P03. File sessions keep the first local page independent of a database. Production HTTPS sessions have secure, HTTP-only, same-site cookies. Staff authentication and session policy are implemented through P05 (see below).

Before caching configuration, run the private check. After changing settings, clear and rebuild the private cache so requests use the new values:

```sh
php artisan config:clear
php artisan app:check-config
php artisan config:cache
php artisan app:check-config
```

Stop if either check fails. Cached configuration is authoritative until cleared; it contains secrets and stays outside the public web root and version control. Setup/diagnostic Artisan commands remain available with incomplete settings so key generation and recovery work. Future business commands must use the same configuration check before doing work; no business commands exist yet. Database/provider credentials and connectivity checks arrive in their respective steps.

The dependency-free PHP regression script runs isolated subprocesses and temporary copies of the bootstrap/config/routes, never the real .env or installation cache. It covers valid private settings and independent domains, missing settings, unsafe origins, redaction, config-cache behaviour and private key setup. The script is also run by the PHPUnit foundation suite; it remains directly runnable for diagnosis.

## First local page

After private configuration passes, run from this directory:

```sh
php artisan serve --host=127.0.0.1 --port=8000 --tries=1 --no-reload
```

Visit the loopback URL configured in APP_URL. If that port is already occupied, choose another loopback port and set APP_URL to match; do not stop an unrelated service. This PHP development server is for local checks only. Production still requires the actual DirectAdmin public-root/routing checks.

The home page is plain semantic HTML with an escaped installation name and an honest unavailable-ordering message. One plain stylesheet and one native JavaScript module now load from same-origin public assets. This page does not represent the premium menu design or a working ordering feature. The private filesystem disk has serving disabled; original uploads never gain a framework file-serving route.

Run the HTTP smoke check with loopback socket permission:

```sh
php tests/http-smoke.php
```

It starts and stops an isolated temporary server, verifies HTML/escaped names, missing routes and private-file boundaries, generic 503 responses, malformed environment redaction over HTTP, and recovery after fixing settings. It never changes the real private .env. Browser inspection also passed at desktop and 390px mobile width, with no console errors after an empty favicon declaration removed an automatic missing-icon request. Browser artifacts under the ignored .playwright-mcp directory are local review evidence, not a release dependency. DirectAdmin-specific acceptance remains pending.

## Initial CSS and JavaScript

The welcome page loads public/assets/css/global.css and public/assets/js/main.js directly, with no framework, bundler, CDN or new dependencies. The styling uses the proposed warm canvas/green action palette with system fonts, readable spacing, visible keyboard focus and a touch-sized button. The module reveals a “Check again” button only after attaching its reload handler. Without JavaScript, the availability message and staff-assistance text remain readable and the inactive button stays hidden. Reloading never claims that ordering is available or changes server state.

Use the Artisan server command above for local development. The earlier index.php-as-router command sends asset requests through Laravel instead of serving static files. The HTTP smoke test now uses Laravel's bundled development router with its document root and working directory confined to public/. DirectAdmin must use its actual static-file and front-controller configuration; no local server router is uploaded as a production replacement.

P01.05 checks: both assets returned 200 with CSS/JavaScript MIME types and exact file contents; six HTTP smoke groups passed. Browser checks verified module execution, keyboard-triggered page reload, no-JavaScript fallback, desktop/mobile layout and 200% text enlargement at 360px without horizontal overflow. Measured body contrast was 6.53:1 and button contrast 10.15:1; the button was about 56px high with a 3px visible focus outline. These are checks of this small starter page, not complete accessibility or product acceptance.

## Development ignore rules

Root/application ignore rules exclude private .env variants, Composer auth.json credentials, installed dependencies, generated test/coverage/browser reports, Laravel caches/logs/sessions/compiled views, installation uploads under storage/ and public/media/. The safe .env.example, lockfile, application assets, tests and runtime-directory .gitignore placeholders remain eligible for source control.

P01.06 verified 24 sensitive/generated path exclusions and 13 source/example/placeholder inclusions with `git check-ignore --no-index`. `git ls-files --cached --ignored --exclude-standard` returned no files; adding ignore rules does not remove previously indexed files, so this check must accompany future rule changes. These checks did not read or print private credential values. Ignore rules prevent accidental Git inclusion; they do not replace private hosting paths, deployment exclusions or access controls.

## PHP test suite

PHPUnit 12.5.37 is pinned in require-dev for the PHP 8.3 baseline; [PHPUnit 12 requires PHP 8.3](https://docs.phpunit.de/en/12.5/installation.html). Install development dependencies using the Composer command above, then run from this directory:

```sh
php vendor/bin/phpunit --testdox
```

The default Foundation suite runs two PHPUnit tests: one executes all 16 isolated configuration scenarios; the other executes six real loopback HTTP groups. Failures in either script propagate as a failed PHPUnit assertion with its diagnostic output. Each subprocess has a 60-second limit. The tests preserve the existing temporary fixtures and do not load the actual installation .env or contact a database. No coverage percentage is claimed for these process-based smoke tests.

Local prerequisites include PHP CLI extensions declared by the lockfile, process creation, temporary-directory writes, symlink support and permission to bind a loopback socket. The HTTP test is tagged `http`; in an environment that forbids sockets, `php vendor/bin/phpunit --exclude-group http` runs only configuration checks and is explicitly a partial result, not a full pass. Do not silently skip the HTTP test in acceptance evidence.

The XML configuration discovers only *Test.php classes under tests/Feature, bootstraps Composer's autoloader, stores PHPUnit cache privately in .phpunit.cache, and fails empty/risky/warning/deprecation runs. It does not bootstrap the real application or force an SQLite fallback. Existing standalone regression scripts stay available. Actual MySQL integration testing is implemented through P02 and later database suites.

Verified on PHP 8.3.30: PHPUnit 12.5.37 reported **2 tests, 2 assertions passed** (covering the underlying 16 configuration scenarios and six HTTP groups). All 74 production lock entries are unchanged; 25 dev entries were added. Composer validation and actual platform checks passed, with the previously documented licence/framework-pin warnings. A fresh locked-dependency advisory audit returned no advisories, abandoned packages or filter findings. For a production release use `composer install --no-dev --no-interaction --no-scripts --no-plugins --prefer-dist` in the release assembly directory and exclude tests/phpunit configuration from deployment; do not remove dev dependencies from a working test checkout while its tests are running.

## Browser test suite

Playwright 1.63.0 is pinned in package.json/package-lock.json as development-only tooling. The application still serves plain HTML/CSS/JavaScript without Node.js on DirectAdmin. Local browser-test prerequisites are Node.js 24.x (verified 24.18.0), npm (verified 11.16.0), the PHP/Composer prerequisites above, a Playwright-supported desktop Linux/macOS environment, symlinks, temporary-directory writes and loopback socket permission.

From this directory:

```sh
npm ci --ignore-scripts
npx playwright install chromium --no-shell
npm run test:browser
```

The [browser installation command](https://playwright.dev/docs/browsers) downloads the revision matched to the locked Playwright version; browser OS libraries must be available on the development machine. The configuration uses Chromium's regular headless mode (`channel: chromium`), not the separate headless-shell download. Do not install browsers, Node.js, node_modules, browser tests or test reports on the production DirectAdmin account.

The [managed test server](https://playwright.dev/docs/test-webserver) creates a private temporary fixture with generated test settings/key and the installed Composer dependencies. It never reads/copies the working .env, storage or bootstrap cache. PHP binds only 127.0.0.1:8137 and serves only the fixture public directory. Normal completion/interruption stops PHP and removes the fixture. Forced process termination can leave temporary files; they contain generated test data only. An existing server is never reused. If the test port is occupied, choose a free port explicitly, for example `HOTEL_TEST_PORT=8138 npm run test:browser`; do not stop the unrelated process. This variable accepts ports 1024–65535 and cannot select an external host.

Two Chromium smoke tests cover page identity/HTML status, CSS/module delivery, JavaScript execution, keyboard refresh, absence of browser errors, and a 360px no-JavaScript fallback without horizontal overflow. Failed runs retain a trace under ignored test-results/. These are starter-page checks, not full accessibility or business acceptance. Add Firefox/WebKit and actual supported tablets in later browser/device gates.

Verified locally on 4 October 2026: both tests passed with Chromium 153.0.8010.12 (revision 1243), PHP 8.3.30 and the versions above. The matching Chromium browser was already available locally; no system/browser upgrade was performed. The package install audited four packages with zero reported vulnerabilities. JavaScript syntax and whitespace checks passed. Private runtime files and node_modules/test-results remain ignored.

## Fresh local checkout checklist

Run the dependency commands above from hotel-app/, then follow these steps in order. Linux/macOS shell examples below use loopback 8000; select an unused port and matching APP_URL if needed.

1. Confirm `php --version`, `composer --version`, `node --version` and `npm --version` meet the recorded prerequisites. Install from both lockfiles, not by resolving new versions.
2. For a **new installation only**, create private settings without overwriting an existing file:

   ```sh
   if [ ! -e .env ]; then (umask 077; cp .env.example .env); fi
   ```

   Set APP_ENV=local and APP_URL to the intended loopback origin. Generate the key once with `php artisan key:generate` for this new installation; do not regenerate an existing installation's key.
3. Run `php artisan app:check-config`. To verify cache setup, run the clear/check/cache/check sequence above, then `php artisan config:clear` for uncached local development. Stop on failure.
4. Install the matching Chromium browser with `npx --no-install playwright install chromium --no-shell`, then run `php vendor/bin/phpunit --testdox` and `npm run test:browser`. Browser tests use their own generated settings and port, independent of the development page.
5. Run the local server command above. Verify `/`, `/assets/css/global.css` and `/assets/js/main.js` respond successfully; the page must say ordering is unavailable. Stop the server with Ctrl+C when finished.

P01.09 reproduction on 4 October 2026 used a fresh temporary source copy, excluding the working .env, dependencies and generated runtime. Composer installed all 99 locked PHP packages into that copy; npm ci installed the three locked browser packages and reported no vulnerabilities. Existing matching Chromium revision 1243 was reused. The browser installer exited successfully but warned that Ubuntu 25.10 is outside its officially supported OS list and uses its Ubuntu 24.04 fallback build; successful local tests do not certify OS support. Use an officially supported development/CI OS for release browser evidence. PHP 8.3.30, Composer 2.10.2, Node 24.18.0 and npm 11.16.0 were the local prerequisites. Composer validation/platform checks, new-key/config/cache recovery, two PHPUnit tests and two Chromium tests passed. The documented Artisan server ran on unused loopback 8140 with APP_URL overridden to match; HTML/CSS/JS returned 200 with appropriate content types, then the server was stopped. Browser tests used 8141. No working-installation settings were changed. This verifies local startup only; MySQL and DirectAdmin remain untested.

## Local DirectAdmin mapping status

P01.10 inspects the current public/private files and a temporary public_html/private_html symlink simulation; see the [mapping evidence and remaining host gates](../architecture/05-directadmin-layout.md#local-scaffold-mapping-review--p0110). Only index.php and the two public assets exist under public/. No production rewrite rules or fixed-public_html copy adapter are included. Uploads remain unimplemented; originals must stay private and only validated derivatives may later be published. Actual hosting, HTTPS, filesystem restrictions and media execution controls remain per-installation verification.

## Private MySQL connection — P02.01

The application defaults to MySQL through PDO. This diagnostic explicitly selects the mysql connection and never falls back to another database; Laravel's other bundled named connections are not used by it. Supply DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in this installation's private environment. Database/user names may include a provider's account prefix. Use a dedicated database user with access only to that installation's schema, not a server administrator. The example leaves credentials empty so an unconfigured copy cannot accidentally use a default database.

For a remote connection requiring TLS, set DB_SSL_CA to the provider's trusted CA file in a private readable path; certificate verification remains enabled. Actual provider TLS and privileges must be verified during installation. This step's local fixture does not certify remote TLS. The current connection uses TCP; Unix-socket support is not configured.

After changing private settings, clear/rebuild cached configuration as described above, then run:

```sh
php artisan app:check-database
```

Exit 0 means a read-only query reached a MySQL server with the supplied database selected. It does not prove schema, permissions for future migrations, engine transactions or business behaviour. Exit 1 prints a fixed diagnostic without setting values, SQL/driver exceptions or stack traces, including when verbose output is requested. The check requires valid installation settings first and closes its connection afterwards. It does not log the caught driver exception. Use this command for connection diagnostics; general framework administrative commands are not promised the same redaction and must remain private.

Connection defaults use utf8mb4, strict SQL mode, UTC and InnoDB for future schema work; native prepared statements and disabled multi-statements are configured. Later P02 steps must prove the database conventions with schema and transaction tests. The starter page remains independent of the database and has no database-backed business route.

The separate integration suite requires an explicitly chosen **isolated test database**. Set HOTEL_TEST_DB_HOST, HOTEL_TEST_DB_PORT, HOTEL_TEST_DB_DATABASE, HOTEL_TEST_DB_USERNAME and HOTEL_TEST_DB_PASSWORD in the private test process environment, then run:

```sh
php vendor/bin/phpunit --configuration phpunit.mysql.xml.dist --filter MySqlConnectionTest --testdox
```

Missing variables fail the suite; it never silently skips MySQL or reads the working .env. It copies configuration/bootstrap/routes into a temporary private fixture and uses generated installation settings. Tests check successful connection, wrong password, missing database, refused port, invalid DSN input, empty settings and recovery; failure output must match the constant safe diagnostic and not create driver-detail logs. Captured subprocess output is withheld from assertion failures. The standard foundation suite remains separate; passing it alone is not database acceptance. This connection test issues read-only probes and never resets an existing database. The separate migration test below requires explicit schema-test opt-in and uses disposable tables.

Verified 4 October 2026 on PHP 8.3.30/Laravel 13.34.0 with **MySQL 26.7.1 Community Server**: the explicit database suite passed **1 test, 30 assertions**. The standard foundation suite also passed its two tests. A preliminary supplementary MySQL 8.4.11 run passed the earlier 29-assertion form, followed by a stricter success-output assertion for the final target run. The MySQL suite correctly failed when test settings were absent. An initial missing test-helper dependency was corrected to Laravel's installed filesystem utility before passing integration tests.

The selected test artifact is Oracle's public `container-registry.oracle.com/mysql/community-server:26.7.1`, pinned by registry manifest `sha256:7adb05c11e2eeba9fe5eadff7638740e75da2477b07f144c85efc9aee55a3947`. Reproduce the image selection with:

```sh
docker pull container-registry.oracle.com/mysql/community-server@sha256:7adb05c11e2eeba9fe5eadff7638740e75da2477b07f144c85efc9aee55a3947
```

Docker is optional local test infrastructure, not part of the DirectAdmin deployment. Provision a new disposable database/user with generated private MYSQL_ROOT_PASSWORD/MYSQL_PASSWORD and MYSQL_DATABASE/MYSQL_USER settings via an owner-only environment file; never use an existing installation's data. Bind only a free 127.0.0.1 port, use disposable storage, and pass the resulting non-root connection to HOTEL_TEST_DB_* for the suite. The verified run used 1 GiB memory, one CPU and a tmpfs data directory; test credentials stayed outside source control. Remove only that dedicated fixture and its credential file when done.

The registry download was slow, so the verification run retrieved the same published layers in parallel ranges, checked every SHA-256 digest, and imported the verified archive. The loaded filesystem digests and runtime configuration matched the official image; its server reported 26.7.1. Docker's imported image identifier differs from the registry manifest/config identifiers and is not a substitute registry reference. The original slow transfer was cancelled after verified retrieval. See [Oracle's Docker setup documentation](https://dev.mysql.com/doc/mysql-installation-excerpt/8.0/en/docker-mysql-getting-started.html) and [26.7.1 image security release](https://dev.mysql.com/doc/relnotes/mysql/26.10/en/news-26-7-1.html). No data tables, migration history, financial operations or remote-host readiness are claimed by this connection test.

P02.01 was approved by independent PHP and code reviewers, each rerunning the final 30-assertion suite. Both dedicated MySQL fixtures and their generated settings/data were removed after review; the verified image remains cached for future isolated tests. No test service is left running and no working .env was changed.

## Ordered migrations — P02.02

The private database/migrations/ directory is ready for timestamp-prefixed anonymous Laravel migrations. The migration repository uses the explicit `migrations` table. P02.02 introduced the runner without a business migration; P02.03 now adds the settings migration described below. Test-only migrations are generated in temporary fixtures and are never copied into the application migration directory.

For an explicitly selected installation whose private configuration is valid, apply pending migrations with:

```sh
php artisan app:migrate
```

The command preflights installation settings and real MySQL access before invoking Laravel's migrator. Laravel sorts migration filenames and records successful filenames/batches. Completed migrations are skipped on later runs. Do not rename or edit an already applied migration to make an upgrade; add a new reviewed file. With no migration files, a run creates only the migration-history table; the current source also applies the hotel_settings migration. The command prints a fixed success message or a redacted failure; it suppresses inner command output and caught exception details. Native framework migration/inspection commands remain private and do not inherit this output policy.

APP_ENV=production refuses migration unless an authorized operator explicitly adds `--force`. This flag is a CLI safeguard, not authentication or permission to deploy. Only one migration operator/process may run at a time; this wrapper does not provide a concurrent deployment lock. Follow the installation backup, maintenance and matched-release process before real deployment. No production migration was run during this step.

On failure, stop and inspect the private schema/history before retrying. MySQL DDL can commit implicitly: an exception can leave partially changed schema even though that migration is absent from history. No all-or-nothing DDL rollback is promised, and the command never automatically retries, resets, rolls back or marks a failed migration complete. Repair through a reviewed recovery procedure. The test failure occurs before mutation and is not evidence of arbitrary DDL recovery.

The database suite now includes schema-changing tests. In addition to the private HOTEL_TEST_DB_* settings, explicitly opt in **only against a disposable isolated database**:

```sh
HOTEL_TEST_DB_ALLOW_SCHEMA=1 php vendor/bin/phpunit --configuration phpunit.mysql.xml.dist --testdox
```

The migration test generates random table/history names and a temporary application/migration directory. This migration-framework test never reads the working .env, runs real application migrations or resets the database; the separate P02.03 test below runs the selected settings migration using isolated prefixed tables. It removes only its own generated tables and files in cleanup. A killed test process may leave its generated tables; discard the dedicated test database rather than cleaning an installation database. Without the opt-in the migration test fails, not skips. The connection-only command above remains read-only.

Verified on MySQL 26.7.1/PHP 8.3.30: **2 database tests, 89 assertions passed**, covering the existing connection check plus migration ordering, recorded history/batches, unchanged data/history on repeat runs, a later migration in batch two, production refusal/explicit force in the temporary fixture, preflight failure, redacted migration failure and retained completed history. The foundation suite also passed 2 tests/2 assertions. Actual hotel schema, financial transactions, deployment concurrency and DirectAdmin acceptance remain later work.

## Installation settings — P02.03

The first business migration creates hotel_settings; the HotelSettings model uses Laravel-generated UUIDs for its identity. The migration creates no demo or real hotel row. Creating the actual owner's settings belongs to the later authorized setup flow, so an empty table means unconfigured, not a ready installation. The starter page still uses private APP_NAME; it does not read hotel settings yet.

| Field | Current storage contract |
|---|---|
| id | UUID primary key generated by Laravel when the model creates a row |
| installation_slot | Stored generated constant 1 with a unique index; application writes cannot choose another slot |
| name / timezone | Required name (150 characters) and timezone identifier (64 characters); future input boundary must validate a nonblank name and an IANA timezone |
| currency | KES only, enforced by the schema under the required strict MySQL mode |
| business_day_cutoff | Nullable local time; no hotel cutoff is assumed |
| fiscal_configuration_version | Nullable unsigned integer; no configured/approved fiscal integration is implied |
| created_at / updated_at | Laravel timestamps under the existing UTC application/connection settings |

The table permits **at most one row**, a stricter form of one active identity appropriate to a single-hotel database. It has no inactive hotel directory or second-hotel slot. The unique generated column enforces this even for raw SQL and competing connections; no count-then-insert check is used. The model allows assignment of settings fields, not the generated slot or identity. Edit the existing row to update settings. Model/schema support is not an owner-facing setup API or authorization policy. The migration's down method drops the table and its data; never use rollback as a routine settings reset.

The schema-opt-in MySQL suite now includes the actual settings migration in a temporary application with a random table prefix, plus two processes released from a common start barrier. It checks exactly one creation, duplicate rejection, attempted generated-slot override, settings updates retaining the UUID, and migration rerun preserving the row. It runs only this selected business migration and cleans up its own prefixed settings/history tables. The working .env and unprefixed installation tables are untouched.

Verified on MySQL 26.7.1/PHP 8.3.30: **3 database tests, 144 assertions passed**; the foundation suite passed 2 tests/2 assertions. An initial test helper collided with a reserved PHPUnit method and was renamed before the successful run. At that stage shared identifier/time conventions were still pending P02.05 (now documented below). Application-level settings validation/authorization, fiscal configuration semantics, and full separate-installation acceptance remain later steps.

## Storage and transaction conventions — P02.04

The existing MySQL connection configuration fixes InnoDB, utf8mb4 and utf8mb4_unicode_ci; no new runtime dependency or migration is needed for this step. HotelSettingsTest now inspects the actual migrated table engine/collation and live connection character sets. A committed name containing Kiswahili, accented Latin, Arabic, Chinese and a four-byte emoji is read unchanged by a fresh process.

The same isolated fixture verifies Laravel transaction rollback after an intentional exception spanning update/delete/replacement insert, and after a duplicate-key error following an update. Fresh connections verify the entire previously committed row and UUID remain unchanged, and transaction depth returns to zero. All schema changes occur outside these transactions: MySQL DDL is not covered by this rollback guarantee. Financial workflows, locking and bounded deadlock retries remain future steps (P02.08 and feature phases).

Verification: MySQL 26.7.1 suite passed 3 tests/180 assertions. The foundation HTTP check initially could not bind a loopback port under the sandbox; it then passed with loopback access (2 tests/2 assertions). These local checks do not certify any DirectAdmin installation or provide a translated interface.

## Record identifiers and UTC timestamps — P02.05

Business models extend App\Models\Record, which shares Laravel's UUIDv7 string-key generation and explicitly UTC automatic timestamps. HotelSettings now uses this base without changing existing keys or the schema. UUIDs are opaque record references, not secrets: UUIDv7 contains time information. Neither UUID possession nor a public order/collection number grants access. Future order models must keep their display numbers separate, and future endpoints must enforce authentication and authorization; no credential or order-number generator is added here.

Keep the application timezone UTC and the MySQL session at +00:00. Automatic created_at/updated_at values are generated in UTC, stored at the current schema's second precision, and serialized by Eloquent as ISO-8601 UTC strings. Hotel timezone remains display/business-day configuration. Explicitly supplied dates, imported dates and raw SQL writes must be normalized at their future input boundaries; the base model's automatic timestamp method does not normalize arbitrary supplied attributes.

RecordConventionsTest checks UUIDv7/string/nonincrementing keys, protected ID mass assignment, rejection of a numeric display reference as a UUID route key, and UTC generation under a Nairobi process clock. The real MySQL fixture creates at a fixed Nairobi instant, reads raw UTC timestamps from a new process, updates an hour later while preserving ID/created_at, and checks UTC JSON serialization. Numeric-key rejection is structural validation, not an authorization test. Verification passed: foundation/convention suite 4 tests/109 assertions; MySQL 26.7.1 suite 3 tests/205 assertions. php_review and code_review independently approved P02.05; the disposable fixture and temporary credentials were removed.

## Integer money validation — P02.06

App\Support\MinorAmount::fromMinor accepts a non-negative PHP integer or canonical ASCII integer string already expressed in KES minor units: 120000 means KSh 1,200. It returns an immutable object with an integer value. Zero is valid. The technical ceiling is PHP_INT_MAX on the running platform; it is not a hotel price/payment limit. Negative adjustments must use later operation-specific representations, not negative inputs to this amount type. Payment and pricing boundaries still need their own positive/minimum/maximum rules and server-owned calculation.

Strings must be exactly 0 or digits starting with 1–9. Whitespace, signs, leading zeroes, decimal/scientific notation, Unicode digits and oversized values are rejected before conversion. Floats (including integral floats), booleans, null and containers are rejected. Failures use InvalidArgumentException with a constant message. Validation never rounds, clamps or uses binary floating point. Amounts beyond JavaScript's safe-integer range must reach this validator as exact strings; future API contracts must preserve that precision and must not blindly serialize these values as browser numbers.

This step adds no arithmetic, decimal-major-unit parser, provider conversion, endpoint or financial persistence. P02.07 handles rounding/allocation; later workflows handle overflow-safe arithmetic and business rules. Tests cover zero, integer limits and one above the limit, precision beyond JavaScript safe integers on 64-bit PHP, immutable values and malformed inputs. Tests first failed because the class had not been created, then passed after implementation. Focused tests passed 37 tests/49 assertions; full foundation suite passed 41 tests/158 assertions. php_review and code_review independently approved P02.06 with no required findings.

## Exact rounding and equal allocation — P02.07

MoneyAllocation::divideHalfUp divides a non-negative MinorAmount by an actual positive integer and rounds to the nearest minor unit, with exact halves rounded up. This explicitly named helper is an implementation convention, not an approved tax/provider rounding policy. It uses integer quotient/remainder and compares the remainder to divisor-minus-remainder, avoiding both floating point and overflow from doubling. It does not multiply rates or parse decimal major units.

MoneyAllocation::equally distributes the original total among a non-empty list of distinct non-empty string IDs. It sorts IDs bytewise (case-sensitive), assigns each recipient the integer quotient, then assigns one extra minor unit to the first remainder-count IDs. For example, 100 minor units across guest-a/b/c becomes 34/33/33 regardless of input order. Zero shares are valid when the total is smaller than the recipient count. Shares sum exactly to the total and differ by at most one. The result is a list of recipient_id/MinorAmount pairs, avoiding PHP numeric-key coercion. Equal splits do not use independent rounded division, which could change the total.

The bytewise canonical server ID order is the stable guest order for this helper; visible guest/table numbers and request order do not decide who gets remainder units. Callers must resolve actual authorized guests and supply canonical IDs; this helper validates only structural uniqueness and does not grant permission, look up guests or change bills. D01 remains a proposed sharing workflow. Whole-shilling/provider conversion, signed adjustments, weighted shares, tax rates and multiplication remain outside this step. Provider granularity must still be checked before payment.

Tests-first failed for the absent helper, then focused checks passed 3 tests/234 assertions. Checks cover rounding below/at/above halves, PHP integer boundaries, deterministic reordering, invalid inputs and 35 total/recipient-count combinations including zero, fewer minor units than recipients and PHP_INT_MAX. Full foundation passed 44 tests/392 assertions. php_review and code_review independently approved P02.07 after rerunning the focused suite. Proposed D18 records these implementation conventions; cumulative P02 is incomplete.

## Bounded transaction retries — P02.08

DatabaseTransaction::run accepts the MySQL connection and a database-only callback, returning its committed result. It requires a top-level transaction (including no raw PDO transaction) and attempts the whole callback at most three times. Each attempt delegates begin/commit/rollback to Laravel with its own retry count set to one. Only a PDO/MySQL diagnostic with SQLSTATE 40001 and error 1213 is retried, after rollback and verification that no transaction remains active. Delays are 10ms and 20ms. This bounds attempts, not query execution time; host timeouts still apply.

Application errors, duplicate constraints, lock-wait timeouts, lost connections and unknown commit outcomes propagate without application replay. Exceptions may contain private driver details and must be caught/redacted by future request/command boundaries; the wrapper does not log them. Never manually commit, roll back, nest this wrapper or execute DDL inside the callback. Reload models within each attempt and keep the callback replay-safe. External effects must use durable outbox records committed in the same transaction; this wrapper does not yet implement outbox or request idempotency, and cannot prevent a caller from performing unsafe external effects.

The isolated MySQL test creates two temporary probe rows and uses opposing row-lock order in two processes to force a genuine deadlock. One worker commits on attempt one and the other on attempt two; each row ends at two, proving the aborted first write did not survive. Separate SQL SIGNAL fault injection deterministically verifies three-attempt exhaustion and no retry for lock-wait timeout; fresh processes see unchanged rows. Existing application/constraint rollback checks now exercise this wrapper. Nested execution is rejected without consuming the outer transaction. A unit test verifies lost-connection and unrelated-error propagation with exactly one attempt. SIGNAL cases are injected diagnostics, not additional naturally occurring deadlocks.

MySQL 26.7.1 passed 3 tests/247 assertions. The initial unit test used a PDO mock without expectations and produced a PHPUnit notice; replacing it with a stub resolved the notice (focused 1 test/12 assertions). The clean full foundation rerun passed 45 tests/404 assertions. php_review and code_review independently approved P02.08 after passing the MySQL and focused unit suites. The disposable fixture and credentials were removed. No business workflow, endpoint, host or provider acceptance is implied.

## Isolated demo reset — P02.09

`php artisan app:demo-reset --confirm-database=YOUR_DEMO_DATABASE` replaces the demo hotel_settings row with clearly labelled fictional settings and, since P03.08, clears demo idempotent_commands in the same transaction. It does not reset the installation database, migrations, staff, orders or payments. The reviewed reset scope is hotel_settings plus idempotent_commands; migrations and the guard are preserved. Additional tables require an explicit reviewed extension.

Provision this only in a separate local/testing application copy and a dedicated disposable MySQL database with a user limited to that database. Never copy live records into it. Configure DEMO_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD (and TLS CA when needed) separately from DB_*; no credentials fall back to the primary connection. The primary DB_DATABASE must be set, and the demo and primary database names must differ even across hosts. Use APP_ENV=local or testing, DEMO_RESET_ENABLED=true and a private randomly generated 64-character lowercase hexadecimal DEMO_RESET_TOKEN. Keep these settings out of Git. The shipped example is disabled.

For a fresh empty demo database, an authorized operator runs `php artisan migrate --database=demo_reset` to apply the current settings schema. Before enabling reset, provision its explicit marker using the demo database connection only:

```sql
CREATE TABLE demo_reset_guard (
    id TINYINT UNSIGNED PRIMARY KEY,
    purpose VARCHAR(32) NOT NULL,
    token VARCHAR(64) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO demo_reset_guard (id, purpose, token)
VALUES (1, 'isolated-demo', 'REPLACE_WITH_THE_PRIVATE_DEMO_RESET_TOKEN');
```

The literal placeholder is not a valid token; replace it privately with the same random value configured above. The reset command never creates this marker. Do not add it to a live installation. Clear cached configuration after changing private settings. Provisioning and reset require a single operator with no concurrent schema changes.

Reset requires an exact database-name confirmation, non-production mode, explicit boolean opt-in, different primary/demo names, the exact known four-table schema, InnoDB storage and a single locked marker with matching purpose/token. Missing or mismatched settings refuse before deletion. Replacement happens in one transaction and failed inserts restore the old row. All command failures are constant and do not log connection details. This protects against accidental use; it cannot identify real data that an administrator deliberately moves into and marks as a demo database. Least-privilege separate credentials remain required.

The MySQL test owns an otherwise empty disposable schema, supplies generated private settings and cleans up its tables. It checks production/staging, disabled mode, bad/missing token, primary-name collision, missing confirmation, missing marker and unexpected tables; repeated resets preserve migration history, and an injected insert failure proves rollback. Unit tests require unsafe configuration to refuse before database access. The first database run exposed Laravel's schema-qualified table listing; explicitly requesting unqualified names in the selected demo schema fixed the allowlist check. MySQL verification passed 4 tests/399 assertions; the focused preconnection guard checks passed 1 test/16 assertions. The full foundation suite passed 46 tests/420 assertions. php_review and code_review approved P02.09; PHP independently reran the MySQL suite and both reran the guards. Fixture and temporary credentials were removed. DemoResetTest needs an empty disposable schema; run database suites serially against it.

## Database/money foundation gate — P02.10

The real MySQL suite now requires two empty disposable schemas on the same server, with distinct users each granted access only to its own schema. Supply the existing HOTEL_TEST_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD plus HOTEL_TEST_ISOLATION_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD and HOTEL_TEST_DB_ALLOW_SCHEMA=1 privately. Do not use root/shared broadly privileged accounts: the test must observe access denied in both directions. No .env fallback is used and credentials are not printed.

Run the database suite serially, exclusively, on those disposable schemas; DemoResetTest and MySqlIsolationTest use unprefixed tables only after asserting emptiness. The isolation test creates separate temporary app copies/keys/configuration, runs the actual settings migration in each, stores the same fixture ID with different names, updates A, and verifies fully qualified cross-schema reads/writes are denied both ways while both original records remain correctly scoped. Tests clean their tables and temporary copies; no real installation is contacted.

P02.10 verification on PHP 8.3.30 / MySQL 26.7.1: foundation 46 tests/420 assertions; MySQL 5 tests/475 assertions. Composer strict validation found only the already documented exact framework pin and missing licence warnings (nonzero strict result); O12 licence choice remains open. Dependencies were not changed. Cumulative review and limitations are recorded in [P02 verification](../delivery/07-database-foundation-verification.md). Database-level separation is not evidence of future endpoint authorization, complete application functionality or DirectAdmin acceptance.

## API route boundary — P03.02

P03.02 initially loaded routes/api.php under /api/v1 with a stateless API group; P03.06 below adds browser sessions and CSRF by default. The file deliberately has no implemented health/business endpoints yet. A path-scoped exception renderer returns the reviewed NOT_FOUND and METHOD_NOT_ALLOWED JSON shapes for 404/405 errors within that exact prefix, regardless of Accept headers. Each response has a new server-generated requestId and Cache-Control: no-store; 405 preserves Allow. HEAD returns the corresponding headers/status with no body. No catch-all route is used, so future registered routes and method restrictions retain normal routing behavior.

The starter page, static assets and ordinary web 404 behavior remain unchanged; /api/v10 is outside the API boundary. No unauthenticated health/readiness route has been added. Full request-ID middleware, success/error response handling, safe internal exception envelopes and authentication remain later P03 steps. Pre-routing installation configuration failures retain their existing safe text/plain 503 response, including malformed .env handling; the new renderer handles routing errors only.

The isolated real HTTP fixture adds a test-only API endpoint to prove prefix application and 405 behavior. It checks API root/deep/encoded paths across GET/POST/PUT/PATCH/DELETE/OPTIONS, HTML/JSON/wildcard Accept headers, HEAD semantics, no-store, generated IDs instead of echoed input, absent health endpoints, and web-prefix separation. The test-only endpoint is never registered in the application source routes. No working .env or database is used. Foundation verification passed 46 tests/420 assertions; contract validation retained 15 examples/12 passing tests. php_review and code_review independently approved P03.02 after rerunning the focused HTTP/config checks. Temporary HTTP fixtures were removed.

### P03.03 — bounded JSON and declared input fields

ApplicationRequest defers API decoding until ParseJsonInput reads at most 65,537 bytes and enforces a 65,536-byte/depth-32 object limit. Malformed/duplicate JSON, unsupported media, oversized bodies and input-rule failures return redacted JSON errors. JsonInput checks declared members recursively, validates endpoint rules, and returns body-only validated data. No product route is introduced. See api/01-conventions.md for D20 defaults, future upload/webhook requirements and host-buffering limits.

Verification: foundation suite 54 tests/483 assertions, including real HTTP valid/invalid input checks and eight focused parser/validation tests; OpenAPI 15 examples/12 regression tests passed. Initial focused run found only an assertion depending on associative-key order; the assertion was corrected to the validator's actual order. php_review and code_review approved the final P03.03 revision after query-override, long-string scanner and numeric-wildcard corrections.

### P03.04 — response envelopes and redacted errors

ApiResponse centralizes success/error JSON and matching body/header request IDs. ApiExceptionResponse covers internal, framework and custom-rendered exceptions independently of debug/Accept settings. Input errors reuse the response builder; the superseded ApiRouteErrors helper was removed. API setup failures now return JSON; web/CLI setup responses remain intact. Exception logs contain only a fixed message and request ID.

Foundation56/503 and all 15 API examples passed, including debug on/off exceptions, unsafe custom renderers, setup errors, request IDs and header allowlisting. php_review and code_review approved final P03.04, including the custom-reporting correction.

### P03.05 — authentication and authorization interfaces

PrincipalResolver and CapabilityAuthorizer are bound to deny-by-default adapters. Registered principal/capability middleware rejects missing or unauthorized identities before handlers. Tests cover submitted identity spoofing, stale principal replacement, revocation, wrong scope/capability, valid scoped passage and real HTTP registration/denial. No real sign-in or role/session storage is implemented.

### P03.06 — browser CSRF

The API default is now browser-oriented: encrypted cookies, existing private file sessions and mandatory session-bound X-CSRF-TOKEN for mutations. No Sec-Fetch-Site or unit-test bypass exists. Future signed webhook/service routes require separately reviewed stateless configuration; none are exempt today. Production bootstrap/sign-in routes remain unimplemented.

Foundation61/513 passed. Unit tests verify session-token rotation and rejection of query/body tokens. Real HTTP checks cover POST/PUT/PATCH/DELETE missing/wrong/unbound tokens, another session's token, valid success, cookie flags and a handler sentinel proving denied requests never perform business work. Session maintenance writes are expected; no application database is used in this step. Both reviewers approved after independent CSRF/HTTP4/5 checks.

### P03.07 — scoped request and login limits

RequestWindow uses private file-locked fixed windows across PHP processes. API requests are limited per session; the reusable login alias adds canonical identity/IP limits. Four environment defaults are documented in .env.example. Errors return safe 429/Retry-After or fail-closed 503 when storage/configuration is unusable. Shared-IP isolation and cookie-rotation resistance for login are verified; actual sign-in remains pending.

Foundation64/541 passed; focused window tests3/28 include eight concurrent PHP processes, threshold/reset, permissions, contention and corrupt-state refusal. Real HTTP checks cover configurable limits, normalized login identities, Retry-After, another identity on the same IP and another browser on the same IP. Private stale-file cleanup and host-locking acceptance remain explicit P15/operations requirements; this is single-host application throttling, not DDoS protection. Both reviewers approved the final empty-counter correction.

### P03.08 — durable command replay

The idempotent_commands migration and IdempotentCommand/CommandResult service atomically save database work and a bounded result. Same principal/operation/key/body replays without rerunning; different body conflicts. Concurrent claims serialize in MySQL. Failure, oversized results and damaged saved results fail safely. Raw bodies and principal identifiers are hashed rather than stored. Callers must authorize first and restrict callbacks to the provided database connection; external side effects require future durable outbox records.

The guarded demo reset now explicitly includes the idempotency table and its rollback coverage. Apply current migrations before using the reset. Transport records do not automatically expire. No product endpoint invokes this foundation yet; original body bytes must be retained for retries. See D22/API/transaction conventions for limits.

### P03.09 — stale-edit protection

ResourceVersion handles strict strong If-Match/ETag values; missing428, malformed400 and stale412 are shared safe API responses. VersionedUpdate compares and increments in one MySQL statement, preserving caller transaction rollback and rejecting protected columns. A migration initializes existing/new hotel settings to version1. Future controllers supply authorized row IDs and validated database-ready changes; this primitive does not run Eloquent events/mutators or replace authorization.

OpenAPI now has reusable version-tag/header/parameter definitions and a428 error. The exact signed64-bit ceiling is covered by PHP and JSON-schema tests. Fixture HTTP routes demonstrate response statuses and ETag; no real settings editing endpoint is added.

P03.09 verification: foundation69/561, real MySQL7/638, contract16 examples/13 tests passed. Independent PHP review repeated MySQL7/638 and version3/18; Python review confirmed schema boundaries and validation checks. P03.10 generated the browser contract client and Fetch wrapper (10 client tests at that step). Cumulative P04 is approved; P05 awaits its cumulative review.

P03.09 is approved by code_review and php_review; Python/schema review is also approved. Work stopped after this completed step at the user's request. The disposable database and credentials are removed. All changes are saved locally; no commit/push/deployment was performed.

### P04 — basic frontend and design primitives

P04.01–P04.10 add the labelled prototype frontend surface. Files: `resources/views/layout.blade.php` and `resources/views/preview/*.blade.php` (customer/tablet, kiosk, staff, kitchen, collection, cashier, bill, plus a components page), registered as `/preview/{mode}` review routes in `routes/web.php`.

- P04.02 `public/assets/css/tokens.css` carries the design palette, spacing, radii, type and touch tokens from `design/01-premium-design.md`; `global.css` consumes them and adds accessible control/focus/skip-link styles.
- P04.03–P04.08 primitives: `public/assets/js/components/ui/dialog.js` and `drawer.js` (focus trap, Escape, focus restore, re-entry guard, document-level handlers), `tabs.js` (roving tabindex, arrows/Home/End), status/state markup, `public/assets/js/lib/connection-banner.js` (offline vs unreachable vs provider states, abort passthrough, stale-probe guard), and `public/assets/js/lib/locale.js` (BigInt-exact KES `formatMoney`, `Africa/Nairobi` `formatDateTime`, display only). `public/locales/en.json` holds message keys.
- P04.09 each mode composes representative demo primitives (meal cards + cart, kiosk grid + steps, kitchen tickets, cashier methods, bill totals, collection numbers) and is labelled as a prototype.
- P04.10 target-viewport matrix (360×800, 768×1024, 1024×768, 1080×1920, 1440×900) plus 200% text zoom and keyboard focus checks live in `tests/browser/viewports.spec.js`; durable screenshots are under `tests/evidence/p04/`.

Verification: `npm run test:browser` 63 passed, `npm run test:js` 14 passed, PHPUnit 69/561, contract 16 examples/13 tests. Recorded P04.10 findings (prototype nav footprint, sticky-bar occlusion) remain open for later design/UI acceptance. No business endpoints, pricing logic, or live data are added; all demo content is labelled prototype content.

### P05 — owner setup and staff sign-in

P05.01 created the `staff_users`, `roles`, `staff_role_grants`, and `staff_sessions` tables (migration `2026_10_04_000004`). Staff identity is unique (case-insensitive email) and referenced history is preserved: role grants and sessions use `RESTRICT` foreign keys, and deactivation sets `active`/`deactivated_at` instead of deleting. Only the `owner` role is seeded; the remaining roles from `security/01-rbac.md` are proposed and arrive with role management in P06.

P05.06 adds the sign-in submitting/denied UX: `public/assets/js/lib/single-submit.js` disables the control, sets `aria-busy`, prevents a duplicate submit, and announces a pending status via a live region; the denied alert is focused on load and the email field carries an inline error. Verified by `tests/browser/staff-sign-in.spec.js`.

P05.10 ran the full battery (Foundation 76/586, browser 76, JS 14, contract 13, http-smoke 15, and eight real-MySQL database tests from clean schemas) and recorded S01/S02 evidence in `tests/evidence/p05/`.

P05.09 adds security audit events (`audit_events`, `SecurityAudit`): login success/failure, logout, and unlock success/failure are recorded with actor, IP, UTC time, and bounded non-secret context; credential-like keys (`password`, `token`, `session`, `cookie`, `auth`, `credential`, `csrf` and variants) are filtered and unknown event names rejected. Failed-login auditing is bounded per IP, and login/unlock throttling keys on the submitted email (sign-in) or the server-side staff id (unlock) plus IP. Full audit-trail fields, retention, and operational health remain P26 follow-ups.

P05.08 adds an inactivity lock and authenticated unlock: sessions idle beyond `STAFF_IDLE_MINUTES` (default 30) are denied by `SessionPrincipalResolver` until the staff member re-verifies their password at `/staff/lock` (`StaffUnlockController`), which rebinds the server session on success. A guest/customer session (`guest_id` only) resolves to no staff principal. Per-request activity touches `last_seen_at`; P05.09 owns login throttling refinement.

P05.07 adds sign-out and server-side session revocation: each sign-in records a revocable `staff_sessions` row keyed by `sha256(session id)`; `SessionPrincipalResolver` requires that row (unrevoked, unexpired, cookie-matched) and an active staff account. `POST /staff/sign-out` revokes the row and invalidates the local session, so a replayed old cookie cannot regain access. The server-side row has an absolute lifetime (equal to the session lifetime); inactivity locking is P05.08.

P05.05 adds staff sign-in (`/staff/sign-in`): credentials are verified against active accounts by `StaffAuthenticator` (case-insensitive email, non-specific errors, constant-cost dummy verify on unknown/inactive accounts to blunt timing enumeration, opportunistic rehash that never blocks a valid login). Successful sign-in rotates the session id and CSRF token (`session()->regenerate()`) before storing `staff_user_id`. `SessionPrincipalResolver` resolves the principal from the session and re-checks active state, failing closed on database errors; the web POST is throttled by `limit:login,email` keyed by email+IP for both JSON and form bodies. Session cookies are HttpOnly, SameSite=Lax, host-only, encrypted, and `secure` when `APP_URL` is HTTPS.

P05.04 pins password hashing (`config/hashing.php`: bcrypt, rounds 12, verify off, limit 72) behind `App\Support\PasswordHasher`, which enforces a 12-character minimum, a 72-byte maximum, rejects NUL bytes, annotates plaintext as sensitive, verifies via constant-time `password_verify`, and supports rehash detection. `OwnerBootstrap` uses it; plaintext is never stored (`password_hash` is hidden and no longer mass-assignable). See `PasswordHasherTest`.

P05.03 adds the first-install setup screen at `/setup` (enabled only when `INSTALLATION_SETUP_ENABLED=true`; default false). It validates hotel identity, an allow-listed timezone, a hard-forced KES currency, and test mode server-side, requires the same private `INSTALLER_SECRET` on the write, and creates the single installation identity transactionally. The setup screen is unauthenticated except for the installer secret, so enable it only during the install window and disable it afterwards; success redirects to the home page with that reminder.

P05.02 adds the guarded one-time owner bootstrap:

- `app:bootstrap-owner` creates the single owner principal using a private installer secret and a database singleton (`installation_bootstrap`, migration `2026_10_04_000005`). A second sequential or competing run is refused by the database unique slot.
- The secret is never a default and is compared with `hash_equals`; it must be at least 32 characters (`INSTALLER_SECRET`, generate with `openssl rand -hex 32`). The secret and password are never passed as command-line options — automation sets `OWNER_BOOTSTRAP_SECRET`/`OWNER_BOOTSTRAP_PASSWORD`, otherwise the command prompts with hidden input. Remove `INSTALLER_SECRET` after bootstrap.
- The password is hashed with the Laravel hasher (bcrypt by default) and never stored or echoed in plaintext; P05.04 formalizes verification.

Evidence: `OwnerBootstrapTest` on real MySQL 26.7.1 — 60 assertions covering missing/short secret, wrong secret, invalid password, one-time creation with a verifiable hash, sequential refusal, and two competing processes resolving to exactly one owner. Foundation PHPUnit 70/568.

### P06 — staff administration and hotel settings

P06.01 adds scoped staff administration: `StaffAdmin` (list with roles, transactional create with hashed password and role grants) behind `capability:staff.manage` on `/admin/staff`, enforced by `StaffCapabilityAuthorizer` (owner/manager only). A controller guard prevents a manager from granting the owner role (no self-escalation). Baseline roles are seeded by migration `000008`. Evidence: `StaffAdminTest` 65 assertions on real MySQL; browser anonymous-denial test.

P06.02 adds role grant/revoke with guards: no self-escalation, only an owner can grant or revoke the owner role, and the last active owner cannot be stripped (transaction + `lockForUpdate`, concurrency-tested). `granted_by` is recorded. Evidence: `StaffAdminTest` 104 assertions on real MySQL.

P06.03 adds staff deactivation: a transactional deactivation that refuses self-deactivation and last-active-owner removal (row-locked, concurrency-tested), revokes all server-side sessions immediately, and records a `staff_deactivated` audit event. Evidence: `StaffAdminTest` 131 assertions on real MySQL.

P06.04 adds the staff list/edit/status screens: an edit form (name/email with uniqueness), per-member Activate/Deactivate, and the required denied (capability middleware), empty ("No staff members yet" / "no staff to edit") and error (`role=alert`) states. Evidence: `StaffAdminTest` 152 assertions on real MySQL.

P06.05 adds versioned hotel identity and business-day settings behind `capability:settings.manage` (owner only): updates require `If-Match`, stale edits fail, and the version increments atomically. Evidence: `StaffAdminTest` 170 assertions on real MySQL.

P06.06 adds table configuration: unique active labels via a generated column plus unique index, deactivation instead of deletion, and a Tables section on the settings screen. Evidence: `StaffAdminTest` 193 assertions on real MySQL.

P06.07 adds station configuration (kitchen/bar) with a routing-metadata column and a Stations section on the settings screen, gated by `settings.manage`. Evidence: `StaffAdminTest` 219 assertions on real MySQL.

P06.08 adds allowlisted printer destinations (HTTPS plus host allowlist read through `config/printers.php`, unique destination, audit events) and a Printer destinations section. Evidence: `StaffAdminTest` 245 assertions on real MySQL.

P06.09 adds receipt identity settings and a redacted integrations status section (booleans only) on the settings screen. Evidence: `StaffAdminTest` 264 assertions on real MySQL.

P06.10 ran the full battery (Foundation 76/586, browser 78, JS 14, contract 16 examples/13 tests, http-smoke 16, and nine real-MySQL database tests from clean schemas) and recorded S27/S28 evidence in `tests/evidence/p06/`. Cumulative P06 review approved 5 October 2026.

### P07 — devices, tables, visits, and guests

P07.01 adds enrolled-device and device-session records (`devices`, `device_sessions`) storing only SHA-256 digests — never raw credentials or tokens — with revocable sessions. Evidence: `StaffAdminTest` 284 assertions on real MySQL.

P07.02 adds short-lived pairing and activation: a 128-bit code is shown once and stored only as a SHA-256 digest; activation is single-use and expires, and the pairing screen exposes no private data. Evidence: `StaffAdminTest` 300 assertions on real MySQL; browser pairing-screen and CSRF tests.

P07.03 adds the device activation screen (`/device/pair`, the single activation flow), which establishes the device session, and the administrative device list with revoke; revocation is owner/manager-only, audited, and deactivates the device immediately. Evidence: `StaffAdminTest` 313 assertions on real MySQL; browser 80.

P07.04 adds visits (`visits`, migration `000016`) with a MySQL-compatible active-table uniqueness guard (unique index on a generated `active_flag` column) and versioned close. Concurrent opens yield exactly one active visit; failed opens leave no partial rows. Evidence: `VisitServiceTest` on real MySQL covering concurrent opens, rollback and protected fields; Foundation 76/581; browser 80.

P07.05 adds guests (`guests`, migration `000017`) with server-assigned display numbers and labels that are unique inside one visit (`(visit_id, display_number)` and `(visit_id, label)` unique indexes, `RESTRICT` foreign key to visits). `App\Support\GuestService::add()` locks the visit row inside a `DatabaseTransaction`, refuses closed visits and unknown visits, bounds the optional guest name (1–150 characters, no control characters), and caps a visit at 32 guests. Guest identity is a record of its own — it is never derived from a tablet or device session, so replacing a device cannot renumber or reassign a guest; `GuestServiceTest` proves this by deleting every `device_sessions` row and re-reading the same guest. `POST /staff/visits/{visitId}/guests` sits behind `capability:visits.manage`, and the waiter table overview lists each visit's guests with an add-guest form.

This step also repaired three defects in the committed P07.04 surface: `StaffVisitController::index()` was missing although `GET /staff/tables` routes to it; the Blade close-visit form posted to `/staff/visits/close`, which no route defines; and `VisitServiceTest` drove a fixture file (`tests/Fixtures/visit-service-probe.php`) that did not exist against an API shape the shipped service never returned. The fixture now exists and both database tests match the shipped `VisitService`/`GuestService` contracts.

Verification status: `GuestServiceTest` (real MySQL: sequential and concurrent adds, invalid names, closed-visit refusal, rollback, device replacement, per-visit numbering) and `tests/browser/visit-guests.spec.js` were written but **not executed** — this session's environment has no PHP, Composer, MySQL server or downloadable Chromium. Syntax was checked with a PHP parser; the suites must be run before the cumulative P07 review.

P07.06 adds guest bindings (`guest_bindings`, migration `000018`): a device session holds at most one live binding (generated `active_flag` column with a unique index, `RESTRICT` foreign keys to guests, device sessions and the authorising staff user). `App\Support\GuestBindingService::bind()` locks the guest, its visit and the device session inside one `DatabaseTransaction`, refuses closed visits, unknown or inactive guests, revoked/expired device sessions and any device that is not a `tablet`, and revokes the device's previous binding so one tablet is never two guests. `revoke()` is idempotent and only removes the device's identity — orders, bills and guest records are untouched.

`resolveForDeviceSession()` is the identity path for every future guest endpoint: it takes **only** the authenticated device session and returns the guest, visit and table from the live binding. It accepts no guest, table or visit parameter, so a browser-supplied identity cannot widen or change a guest's scope; `GuestBindingTest` asserts this by resolving while a different guest id is supplied and proving the forged id is ignored. `POST /staff/guest-bindings` and `POST /staff/guest-bindings/{bindingId}/revoke` sit behind `capability:visits.manage`, and the waiter overview now lists each guest's bound tablets with bind/unbind controls drawn from `DeviceRegistry::activeSessions('tablet')`.

Verification status: `GuestBindingTest` (real MySQL: binding, two tablets per guest, rebinding replaces the previous binding, kiosk refused, revoked/expired sessions refused, closed visit refused, concurrent binds, idempotent revocation, history retained) and the extended `tests/browser/visit-guests.spec.js` were written but **not executed** — this session's environment has no PHP, Composer, MySQL server or downloadable Chromium. Syntax was checked with a PHP parser; the suites must be run before the cumulative P07 review.

P07.07 rebuilds `GET /staff/tables` as the S03 waiter table overview. `VisitService::overview()` returns every **active** table ordered by label, left-joined to its single open visit and to a `COUNT(*)` of that visit's guests, yielding `{tableId, label, visitId, openedAt, guestCount, version}`. It deliberately carries **no balance and no kitchen field**: billing arrives with P18/P19 and kitchen status with P16, so the screen shows an explicit `Placeholder — Not available yet (billing, P18)` cell instead of a number it cannot compute. Free tables offer an open-visit action in place; occupied ones link to their visit detail page. `VisitOverviewTest` covers the empty board, label ordering, live guest counts, the exact placeholder-safe field set, inactive tables dropping off the board, and a closed visit freeing its table.

P07.08 adds the visit detail screen behind `capability:visits.manage`: `GET /staff/visits/{visitId}` renders `resources/views/staff-visit.blade.php` from `VisitService::visit()` (the visit joined to its table label and owning waiter's name; `null` for a malformed or unknown id, which the controller turns into a 404). Each guest lists its bound tablets with **Unbind** and **Replace tablet** actions, plus a bind form offering only live `tablet` sessions from `DeviceRegistry::activeSessions('tablet')`. `VisitOverviewTest` proves the M4 shape: four guests and four tablets inside one visit, each tablet resolving its own guest at the same table with the guest count unchanged.

P07.09 adds locked table and waiter transfers. Migration `000019` adds `visits.owner_waiter_id` (nullable, indexed, `RESTRICT` foreign key to `staff_users`) and `VisitService::open()` records the opening waiter. `VisitService::transfer(visitId, tableId, waiterId, actorId, expectedVersion)` locks the visit row for the whole check-and-write, locks and re-checks the destination table before moving, enforces the expected version so a stale page cannot move a visit that already changed, refuses occupied or missing tables and unknown or deactivated waiters, maps MySQL error 1062 to `destination_occupied`, and bumps `visits.version`. Result codes: `transferred`, `unchanged`, `invalid_input`, `not_found`, `visit_closed`, `version_conflict`, `destination_occupied`, `destination_not_found`, `waiter_not_found`, `failed`. Guests, bindings and orders keep their identity — only the table or the owning waiter changes. `POST /staff/visits/{visitId}/transfers` sits behind `capability:visits.transfer`, which `StaffCapabilityAuthorizer` grants to **managers only**, and the transfer form is rendered only when the signed-in principal actually holds that capability. Each successful transfer writes a `visit_transferred` audit event; note that `SecurityAudit::record()` silently drops any event name outside its allowlist, so new events must be added there. `VisitTransferTest` covers waiter moves, table moves, a stale version, occupied/missing destinations, deactivated waiters, no-op transfers, a two-manager barrier move into one table (one wins, the loser is refused), and that the allowlist accepts `visit_transferred` while dropping an unknown name.

P07.10 closes the phase with device replacement. `GuestBindingService::revokeWithDeviceSession()` ends a binding **and** the device session it used inside one transaction, so a tablet that is handed over cannot keep resolving the guest and any local draft on it is stale by design; the guest keeps its number and the retired binding stays in the audit history. `POST /staff/guest-bindings/{bindingId}/replace` sits behind `capability:visits.manage` and records a `device_revoked` audit event. `GuestBindingTest::testReplacingATabletEndsTheOldSessionAndKeepsTheGuest` asserts the old session is revoked, the old tablet resolves no guest, the replacement resolves the same guest at the same number, and the guest list is never duplicated.

**Defect repaired across P07.04–P07.10 (5 October 2026):** `StaffVisitController` read the acting staff id from `$request->attributes->get('principal.id', '')` in nine places, but `RequirePrincipal` populates only `hotel.principal` (`RequirePrincipal::ATTRIBUTE`). Every actor id was therefore empty, so opening or closing a visit, adding a guest, binding a tablet, revoking a binding and transferring a visit would all have returned `invalid_input` at runtime even though the screens rendered. All nine reads now go through a single `actorId(Request $request)` helper that reads `RequirePrincipal::ATTRIBUTE` and takes `Principal::identifier()`, matching `StaffAdminController`. No other controller had the defect.

Verification status: `VisitOverviewTest`, `VisitTransferTest` and the replacement test in `GuestBindingTest` (all real MySQL), plus the extended `tests/browser/visit-guests.spec.js`, were written but **not executed** — this session's environment has no PHP, Composer, MySQL server or downloadable Chromium. What was executed here: a PHP-parser syntax check of every changed PHP file, `node --check` on the extended Playwright spec, `node --test tests/js/*.test.mjs` 14/14, `python3 api/validate_contract.py` 16 examples, `python3 -m unittest discover -s api` 13/13, a 247-link Markdown check, and a Blade open/close tag balance check on both screens. The repair above additionally needs one signed-in staff flow through `/staff/tables` and `/staff/visits/{id}` before the cumulative P07 review. The per-step split between what was executed and what was not, together with the exact re-run battery, is in [../../delivery/08-execution-evidence.md](../delivery/08-execution-evidence.md); under the project's mandatory reviewer gate the P07.05–P07.10 records count as *Blocked: evidence missing* until that battery runs and an independent reviewer has seen each step.

## Post-audit operational provisioning

Kitchen access is fail-closed by station. Assign each kitchen staff identity or kitchen device before use:

```sh
php artisan hotel:assign-kitchen-station staff <staff-uuid> <station-uuid>
php artisan hotel:assign-kitchen-station device <device-uuid> <station-uuid>
```

Print bridges use separate hashed credentials and may lease jobs only for explicitly assigned destinations. Provision each bridge once and immediately store the one-time token it prints:

```sh
php artisan hotel:create-print-bridge "Kitchen bridge" <destination-uuid>[,<destination-uuid>...]
```

There is no installation-wide print bearer token. Forced restore is refused unless the destination is an isolated installation with `RECOVERY_MODE=true`, `MPESA_MODE=simulator`, and fiscal output disabled. Verify restored totals/assets and provider exceptions there before a separately controlled cutover. Historical cash payouts and discounts that predate source/beneficiary attribution remain explicit reconciliation exceptions rather than being guessed.
