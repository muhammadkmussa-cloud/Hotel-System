# Backend source-file blueprint

Planned Laravel/PHP paths aligned with [DirectAdmin deployment](../architecture/05-directadmin-layout.md) and C25. This is an inventory for progressive implementation, not existing application code. Pin the selected framework and dependencies in P01.02.

```text
hotel-app/                      # Laravel project root, private on the host
  artisan                       # private CLI entry for migrations/scheduled jobs
  composer.json
  composer.lock
  .env                          # private runtime secrets, never committed
  .env.example                  # safe placeholders only
  vendor/                       # locked production dependencies
  bootstrap/
    app.php
    providers.php
    cache/                      # private writable framework cache
  app/
    Http/
      Controllers/
      Middleware/
      Requests/                 # request validation plus scope checks
      Resources/                # documented JSON projections
    Actions/                    # transactional domain use cases
    Services/                   # ordering, billing, payment orchestration
    Models/                     # MySQL persistence; no browser-derived money
    Policies/                   # role/resource/lifecycle authorization
    Adapters/                   # M-PESA, fiscal and print-bridge boundaries
    Jobs/                       # bounded retryable external work
    Console/Commands/           # bounded job runner and maintenance commands
    Providers/
    Support/                    # exact money, identifiers, clocks
  config/
  routes/
    web.php
    api.php                     # configure existing /api/v1 contract explicitly
    console.php
  resources/views/              # Blade HTML with plain CSS/JS frontend
  contracts/                    # eventual OpenAPI and generated-client inputs
  database/
    migrations/
    factories/
    seeders/                    # demo fixtures guarded from live use
  storage/
    app/private/                # original uploads, never public via symlink
    framework/                  # sessions, compiled views and framework cache
    logs/
  public/                       # web-only contents mapped to domain public_html
    index.php
    .htaccess                   # only when supported by the actual host
    assets/
    media/                      # published raster derivatives only
    service-worker.js
  tests/                        # development only; excluded from host release
```

Use Laravel's existing routing, middleware, Form Requests, policies, migration runner, configuration, and database transactions. Thin controllers call explicit actions/services. Database queries use bound parameters and appropriate locks; the framework does not replace financial/idempotency guards. External provider payloads remain inside adapters. Preserve the existing API envelope and generated JavaScript contract.

Keep a standard private Laravel project root. The web server exposes only `hotel-app/public` through the configured domain root or a provider-supported public_html mapping; see the deployment document for fallback constraints. Never expose the Laravel root or put .env in private_html. Build dependencies locally if hosting lacks Composer/SSH, matching host PHP/extensions.

DirectAdmin cron invokes bounded Artisan commands with leases and overlap protection. No permanent worker, Redis, container, or root privileges are required on the host. The hotel print bridge remains a separate local installation with tested drivers. No frontend framework or starter kit is selected merely by adopting Laravel.

References: [Laravel structure](https://laravel.com/framework/docs/13.x/structure), [deployment](https://laravel.com/framework/docs/13.x/deployment).
