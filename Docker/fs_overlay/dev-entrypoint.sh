#!/bin/bash
set -e

NW_DIR=/var/www/html/w/extensions/NeoWiki
MW_ROOT=/var/www/html/w
NW_MERGED_MARKER="$MW_ROOT/vendor/.neowiki-merged"

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

# composer-merge-plugin can only see extensions/NeoWiki/composer.json once the
# bind mount is up, which is after the build-time composer install in the
# Dockerfile. Run composer update at MW root once so the lock file regenerates
# with NeoWiki's merged deps. The vendor directory is bind-mounted back to
# the host so the merged state is also visible to IDEs and AI tooling. Marker
# file makes this idempotent across container restarts.
if [ -d "$NW_DIR" ] && [ ! -f "$NW_MERGED_MARKER" ]; then
    echo "[dev-entrypoint] merging NeoWiki composer deps into MW root vendor..."
    (cd "$MW_ROOT" && composer update --no-interaction --optimize-autoloader)
    # vendor/ is bind-mounted back to host. Make it host-deletable.
    chown -R www-data:www-data "$MW_ROOT/vendor"
    chmod -R ugo+rwX "$MW_ROOT/vendor"
    touch "$NW_MERGED_MARKER"
fi

# LocalSettings.local.php is bind-mounted from the host (see docker-compose.dev.yml
# and the bootstrap make target, which guarantees the host file exists). The
# require_once in SettingsTemplate.php is gated on file_exists, so an empty file
# is harmless.

exec "$@"
