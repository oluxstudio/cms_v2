#!/bin/sh
# Sandboxed builder for client-uploaded Nuxt templates.
#
# Runs in its own container with NO app secrets, NO database access and NO
# app storage: it only sees /builds (a volume shared with the queue worker).
# Protocol per job dir /builds/jobs/{key}/:
#   app/      the app source (written by the CMS)
#   REQUEST   contains the public base URL; its presence asks for a build
#   RUNNING   claimed by this loop while building
#   out/      the static build (on success) + DONE marker
#   FAILED    marker on failure; build.log holds the output either way
set -u
TIMEOUT="${BUILD_TIMEOUT:-1200}"

# A restart mid-build leaves RUNNING behind: report those as failed.
for running in /builds/jobs/*/RUNNING; do
  [ -f "$running" ] || continue
  dir=$(dirname "$running")
  echo "builder restarted during the build" >> "$dir/build.log"
  touch "$dir/FAILED"; rm -f "$running"
done

while true; do
  for req in /builds/jobs/*/REQUEST; do
    [ -f "$req" ] || continue
    dir=$(dirname "$req")
    mv "$req" "$dir/RUNNING" 2>/dev/null || continue
    base=$(cat "$dir/RUNNING")
    echo "building $(basename "$dir") → $base"
    (
      cd "$dir/app" &&
      timeout "$TIMEOUT" npm install --no-audit --no-fund --loglevel=error &&
      NUXT_PUBLIC_DATA_MODE=api NUXT_APP_BASE_URL="$base" timeout "$TIMEOUT" npx --no-install nuxi generate
    ) > "$dir/build.log" 2>&1
    status=$?
    if [ "$status" -eq 0 ] && [ -f "$dir/app/.output/public/index.html" ]; then
      rm -rf "$dir/out" && cp -R "$dir/app/.output/public" "$dir/out" && touch "$dir/DONE"
    else
      echo "exit status $status" >> "$dir/build.log"
      touch "$dir/FAILED"
    fi
    rm -rf "$dir/app/node_modules" "$dir/RUNNING"
  done
  sleep 5
done
