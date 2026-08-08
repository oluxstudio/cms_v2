#!/usr/bin/env bash
# ── Production deploy script ─────────────────────────────────────────────────
# Runs ON THE SERVER (used by both the GitHub Actions deploy job and manual
# deploys) — pulls the CI-built image and restarts the stack. Never builds.
#
#   ./deploy.sh              # deploy :latest
#   ./deploy.sh sha-<commit> # deploy an exact tested build
#
# Prereq for manual runs: one-time `docker login ghcr.io` with a read-only
# token (the CI job logs in with its own token automatically).
set -e

cd "$(dirname "$0")"

git pull --ff-only origin main

# ── Env contract: every key in .env.example must exist in the server .env.
# Fails loudly BEFORE touching containers, instead of the app 500ing later.
echo "── Checking .env against the .env.example contract"
missing=$(comm -23 \
  <(grep -oP '^[A-Z0-9_]+(?==)' .env.example | sort -u) \
  <(grep -oP '^[A-Z0-9_]+(?==)' .env | sort -u))
if [ -n "$missing" ]; then
  echo "✗ .env is missing keys defined in .env.example:"
  echo "$missing"
  exit 1
fi

export IMAGE_TAG="${1:-latest}"
echo "── Deploying ghcr.io/oluxstudio/cms_v2:${IMAGE_TAG}"

docker compose -f docker-compose.prod.yml pull app
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
docker compose -f docker-compose.prod.yml exec -T app php artisan queue:restart
docker image prune -f

# ── Health check on the env-driven bind (defaults mirror the compose file).
bind=$(grep -oP '^APP_HTTP_BIND=\K.*' .env || true)
port=$(grep -oP '^APP_HTTP_PORT=\K.*' .env || true)
url="http://${bind:-127.0.0.1}:${port:-8080}"
echo "── Health check: ${url}"
if curl -fsS --max-time 10 -o /dev/null "$url"; then
  echo "✓ Deployed ${IMAGE_TAG} — app responding on ${url}"
else
  echo "✗ App is NOT responding on ${url} — check: docker compose -f docker-compose.prod.yml logs app"
  exit 1
fi
