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
