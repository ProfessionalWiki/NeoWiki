#!/bin/bash
set -e

NW_DIR=/var/www/html/w/extensions/NeoWiki

# If the NeoWiki extension is bind-mounted and vendor is missing, run composer install.
if [ -d "$NW_DIR" ] && [ ! -d "$NW_DIR/vendor" ]; then
    echo "[dev-entrypoint] NeoWiki vendor missing, running composer install..."
    (cd "$NW_DIR" && composer install --no-interaction --optimize-autoloader)
fi

# Apply any local settings overlay if present (gitignored, per-worktree).
LOCAL_OVERRIDE=/var/www/html/w/LocalSettings.local.php
if [ ! -f "$LOCAL_OVERRIDE" ]; then
    # Touch a no-op file so a require_once in LocalSettings.php does not fail.
    touch "$LOCAL_OVERRIDE"
    chown www-data:www-data "$LOCAL_OVERRIDE"
fi

exec "$@"
