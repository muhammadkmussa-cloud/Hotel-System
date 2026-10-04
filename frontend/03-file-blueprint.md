# Frontend source-file blueprint

**Planned files only.** HTML, CSS, and plain JavaScript are confirmed C20. Paths follow the [DirectAdmin layout](../architecture/05-directadmin-layout.md); no framework, TypeScript build, or frontend bundler is required by this plan.

```text
public_html/
  assets/
    css/
      tokens.css
      global.css
      layouts.css
      components.css
    js/
      main.js                   # native module entry
      app/router.js             # screen routes and on-demand module loading
      app/session.js            # principal and capability display
      layouts/                  # reusable DOM layout modules
      features/catalogue/
      features/customiser/
      features/cart/
      features/table-service/
      features/checkout/
      features/kiosk/
      features/cashier/
      features/kitchen/
      features/collection/
      features/menu-admin/
      features/staff-admin/
      features/reports/
      components/ui/            # accessible DOM primitives
      components/food/
      lib/api-client.js         # Fetch, CSRF, command identity, standard errors
      lib/events-client.js      # short polling, cursor recovery, refresh
      lib/draft-storage.js      # session-bound drafts and clearing
      lib/money-display.js      # formatting only
      lib/locale.js
      generated/api-client.js   # generated contract; browser-compatible JS
    locales/en.json
    locales/sw.json             # enable only after human review
    images/
    fonts/
  media/                        # published validated derivatives
  service-worker.js             # public asset cache only
hotel-app/
  resources/views/              # Blade templates render plain HTML
    customer.blade.php
    kiosk.blade.php
    staff.blade.php
    kitchen.blade.php
    collection.blade.php
```

Templates use escaped data through Laravel Blade; authorization stays in backend use cases. Shared DOM modules render text safely and expose explicit mount/cleanup behaviour. Routes and APIs are served through `public_html/index.php` as described in the backend blueprint. Keep tests in the development repository outside the public upload.

Each feature owns its UI, API calls, and meaningful tests. Load only the modules needed for the active mode. Every routed feature needs loading, denied, empty, disconnected, and error states. Do not create empty files just to satisfy this inventory.
