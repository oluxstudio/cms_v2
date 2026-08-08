#!/usr/bin/env bash
# ── Apply a .env change WITHOUT deploying new code ──────────────────────────
# Container restart does NOT reload env vars — services must be recreated,
# and the cached config must be rebuilt. This encodes that lesson:
#
#   nano .env && ./env-apply.sh
set -euo pipefail

cd "$(dirname "$0")"

docker compose -f docker-compose.prod.yml up -d --force-recreate app queue scheduler
docker compose -f docker-compose.prod.yml exec -T app php artisan config:cache
echo "✓ .env applied (app, queue, scheduler recreated; config re-cached)"
