#!/bin/sh
# Installs dependencies when the lockfile changed, then rebuilds the UI on every save (UI_WATCH=0 builds once).
set -e
STAMP=node_modules/.package-lock.stamp
if [ ! -f "$STAMP" ] || ! cmp -s package-lock.json "$STAMP"; then
    npm ci --no-audit --no-fund
    cp package-lock.json "$STAMP"
fi
if [ "${UI_WATCH:-1}" = "0" ]; then
    exec npm run -s build:assets
fi
exec npm run -s watch
