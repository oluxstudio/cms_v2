#!/usr/bin/env bash
# ── Apply the current code to the LOCAL app (Sail containers) ───────────────
# What a production deploy does, for the dev box: dependencies, migrations,
# admin assets, caches, queue workers, and the built-in template check.
# Run by `./ship.sh local "…"`, or on its own: ./scripts/apply-local.sh
#
# Always as the `sail` user (root-owned files break web writes), and never
# with --env=testing (there is no .env.testing — it would hit the real DB).
set -euo pipefail
cd "$(dirname "$0")/.."

APP="${LOCAL_APP:-backend-cms-app-1}"
run() { docker exec -u sail "$APP" "$@"; }
say() { printf '\n\033[1m── %s\033[0m\n' "$*"; }
ok()  { printf '\033[32m✓ %s\033[0m\n' "$*"; }

docker ps --format '{{.Names}}' | grep -qx "$APP" || { echo "✗ $APP isn't running — start it with ./vendor/bin/sail up -d"; exit 1; }

say "PHP dependencies"
run composer install --no-interaction --prefer-dist --quiet
ok "composer"

say "Database migrations"
run php artisan migrate --force

say "Admin assets"
if [ ! -d node_modules ] || [ package-lock.json -nt node_modules/.package-lock.json ]; then
    run npm install --no-audit --no-fund --silent
fi
run npm run build --silent >/dev/null
ok "vite build"

say "Caches & workers"
run php artisan optimize:clear >/dev/null
run php artisan queue:restart
ok "caches cleared, queue workers restarting"

say "Built-in templates"
run php artisan templates:check-updates

ok "local app is on $(git rev-parse --short HEAD)"
