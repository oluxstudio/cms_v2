#!/usr/bin/env bash
# ── Production deploy script ─────────────────────────────────────────────────
# Runs ON THE SERVER (used by both the GitHub Actions deploy job and manual
# deploys) — pulls the CI-built image and restarts the stack. Never builds.
#
#   cd /var/www/cms && ./deploy.sh              # deploy :latest
#   cd /var/www/cms && ./deploy.sh sha-<commit> # deploy an exact tested build
#
# Prereq for manual runs: one-time `docker login ghcr.io` with a read-only
# token (the CI job logs in with its own token automatically).
set -e

cd "$(dirname "$0")"

git pull --ff-only origin main

export IMAGE_TAG="${1:-latest}"
echo "Deploying ghcr.io/oluxstudio/cms_v2:${IMAGE_TAG}"

docker compose -f docker-compose.prod.yml pull app
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize:clear
docker compose -f docker-compose.prod.yml exec -T app php artisan optimize
docker compose -f docker-compose.prod.yml exec -T app php artisan queue:restart
docker image prune -f

echo "Deployed ${IMAGE_TAG} ✔"
