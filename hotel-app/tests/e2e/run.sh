#!/usr/bin/env bash
# Resets the local SQLite demo database, seeds it, and runs the HTTP E2E suite
# against a server already listening on ${E2E_BASE:-http://127.0.0.1:8000}.
set -uo pipefail
cd "$(dirname "$0")/../.."
db=storage/app/private/hotel.sqlite
rm -f "$db" && touch "$db"
php artisan migrate --force -q
php artisan hotel:demo-seed > /dev/null
cd tests/e2e
status=0
for s in pages.py flow_table.py flow_money.py; do
  echo "== $s"
  python3 "$s" "${E2E_BASE:-http://127.0.0.1:8000}" | grep -v '^PASS' || status=1
done
exit $status
