#!/bin/bash
set -e

NW_DIR=/var/www/html/w/extensions/NeoWiki

# If the NeoWiki extension is bind-mounted and vendor is missing, run composer install.
if [ -d "$NW_DIR" ] && [ ! -d "$NW_DIR/vendor" ]; then
    echo "[dev-entrypoint] NeoWiki vendor missing, running composer install..."
    (cd "$NW_DIR" && composer install --no-interaction --optimize-autoloader)
    # vendor/ is created as root (PID 1). Make it host-deletable so developers
    # do not need sudo to clean up. Re-installations should use
    # `make composer-install`, which runs as the host UID via `docker compose
    # exec --user`, producing host-owned files.
    chown -R www-data:www-data "$NW_DIR/vendor"
    chmod -R ugo+rwX "$NW_DIR/vendor"
fi

# LocalSettings.local.php is bind-mounted from the host (see docker-compose.dev.yml
# and the bootstrap make target, which guarantees the host file exists). The
# require_once in SettingsTemplate.php is gated on file_exists, so an empty file
# is harmless.

exec "$@"
