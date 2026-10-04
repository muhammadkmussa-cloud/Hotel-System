# Local Laravel foundation

The local foundation includes Laravel 13.34.0, locked dependencies and private installation configuration. A plain HTML home page confirms the foundation runs; all hotel business features are later steps. This is not a usable hotel application yet.

Keep this entire directory private on DirectAdmin. Expose **only `public/`** through the provider-supported domain document root or public_html mapping described in [the deployment specification](../architecture/05-directadmin-layout.md). Neither `private_html` nor the repository root is safe for private application storage. No production domain is hardcoded; each owner supplies their own configuration during setup.

- `bootstrap/`: Laravel application construction and provider registration.
- `routes/`: a read-only home page and private configuration-check command; no business endpoints yet.
- `config/`: private per-installation settings, read through Laravel configuration.
- `resources/views/`: escaped Blade HTML for the first home page.
- `storage/` and `bootstrap/cache/`: private writable runtime paths, generated contents ignored.
- `artisan`: private CLI entry; never expose it as a web endpoint.

No public_html copy or symlink is created locally; every installer must verify their actual HTTP/HTTPS roots before deployment. Do not serve the repository or this private root with a web server.

Bootstrap/entry layout follows the [Laravel 13 application skeleton](https://github.com/laravel/laravel/tree/13.x); the domain business features remain unbuilt.

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

The URL used internally to bootstrap setup commands falls back to synthetic localhost for malformed/missing URLs; the guard still rejects the missing/invalid raw APP_URL, so this is never permission to serve an unconfigured installation. Origin configuration is not yet host-header/proxy enforcement; those request safeguards belong to P03. File sessions keep the first local page independent of a database. Production HTTPS sessions have secure, HTTP-only, same-site cookies. Staff authentication/session policy is still P05 work.

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

The XML configuration discovers only *Test.php classes under tests/Feature, bootstraps Composer's autoloader, stores PHPUnit cache privately in .phpunit.cache, and fails empty/risky/warning/deprecation runs. It does not bootstrap the real application or force an SQLite fallback. Existing standalone regression scripts stay available. Actual MySQL integration testing remains P02 work.

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

Tests-first failed for the absent helper, then focused checks passed 3 tests/234 assertions. Checks cover rounding below/at/above halves, PHP integer boundaries, deterministic reordering, invalid inputs and 35 total/recipient-count combinations including zero, fewer minor units than recipients and PHP_INT_MAX. Final foundation and independent review evidence is recorded in the build plan.
