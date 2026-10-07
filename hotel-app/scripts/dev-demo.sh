#!/usr/bin/env bash
# Local demo on SQLite (development / evaluation only — production uses MySQL).
#
#   scripts/dev-demo.sh            create .env if missing, reset + seed the demo DB
#   scripts/dev-demo.sh --serve    ... then serve on 0.0.0.0:8000
#
# Demo staff sign in as <role>@demo.test with password demo-password-2026
# (owner, manager, cashier, waiter, kitchen-lead, kitchen-staff, menu-editor, auditor).
set -euo pipefail
cd "$(dirname "$0")/.."

db="$PWD/storage/app/private/hotel.sqlite"
mkdir -p "$(dirname "$db")"

if [ ! -f .env ]; then
  cat > .env <<ENV
APP_NAME="Hotel System"
APP_ENV=local
APP_URL=${APP_URL:-http://127.0.0.1:8000}
APP_KEY=
DB_DRIVER=sqlite
DB_DATABASE=$db
STAFF_IDLE_MINUTES=240
PRINTER_BRIDGE_HOSTS=bridge.local
MPESA_MODE=simulator
FISCAL_ENABLED=false
LOGIN_LIMIT_MAX=30
REQUEST_LIMIT_MAX=600
ENV
  php artisan key:generate --force -q
  echo "Created .env (SQLite demo)."
fi

[ -d vendor ] || composer install --no-interaction -q

rm -f "$db" && touch "$db"
php artisan migrate --force -q
php artisan hotel:demo-seed

if [ "${1:-}" = "--serve" ]; then
  exec php -S 0.0.0.0:8000 -t public scripts/dev-router.php
fi
