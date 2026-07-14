#!/usr/bin/env bash
# Post-start hook: install the dependencies MediaWiki and NeoWiki need, then
# install + seed the wiki on first run.
#
# Runs inside the ddev web container on every `ddev start`; every step is
# idempotent. The MediaWiki core checkout itself is created by the pre-start
# hook (.ddev/setup/clone-mediawiki.sh); the NeoWiki checkout is bind-mounted
# into it at extensions/NeoWiki (.ddev/docker-compose.extension-mount.yaml).

set -e

cd /var/www/html

if [ ! -f Docker/mediawiki/index.php ]; then
	echo "Docker/mediawiki is missing or empty; the pre-start clone must have failed. Re-run 'ddev start'." >&2
	exit 1
fi

# MediaWiki's composer.json merges extensions' dependencies through
# composer-merge-plugin; this include file points it at NeoWiki's.
if [ ! -e Docker/mediawiki/composer.local.json ]; then
	cp Docker/composer.local.json Docker/mediawiki/composer.local.json
fi

# Not a plain -d check: core tracks vendor/ as a submodule, so a fresh clone
# already has it as an empty directory.
if [ ! -f Docker/mediawiki/vendor/autoload.php ]; then
	echo "Installing MediaWiki composer dependencies (includes NeoWiki's, via composer-merge-plugin)..."
	composer --working-dir=Docker/mediawiki config --global audit.block-insecure false
	composer --working-dir=Docker/mediawiki update --no-interaction --optimize-autoloader
fi

# The extension's own vendor/ carries the dev tools (phpcs, phpstan, psalm).
if [ ! -d vendor ]; then
	echo "Installing NeoWiki composer dependencies (dev tools)..."
	composer install --no-interaction --optimize-autoloader
fi

write_settings() {
	# Replace the installer-generated settings with the ddev-aware ones. A real
	# file (not a symlink), so nothing outside the core checkout is referenced
	# through it — tooling that copies the tree gets a self-contained file.
	printf '<?php require __DIR__ . "/../../.ddev/mw/LocalSettings.ddev.php";\n' \
		> Docker/mediawiki/LocalSettings.php
}

# Gate the install on the database, not on a file: the database volume is what
# `ddev delete` removes, while the core checkout — including a leftover
# LocalSettings.php — survives on the host.
if ! mysql -h db -u db -pdb db -e 'SELECT 1 FROM page LIMIT 1' >/dev/null 2>&1; then
	echo "First run: installing MediaWiki..."
	# The installer refuses to run when a LocalSettings.php already exists
	# (e.g. left behind by a wiped environment).
	rm -f Docker/mediawiki/LocalSettings.php
	php Docker/mediawiki/maintenance/run.php install \
		--dbtype=mysql --dbserver=db --dbname=db --dbuser=db --dbpass=db \
		--server="$DDEV_PRIMARY_URL" --scriptpath= \
		--pass=AdminPassword "NeoWiki Dev" AdminName

	write_settings

	php Docker/mediawiki/maintenance/run.php update --quick

	echo "Seeding demo data..."
	php Docker/mediawiki/maintenance/run.php NeoWiki:ImportDemoData

	echo "Wiki installed and seeded."
else
	if [ ! -e Docker/mediawiki/LocalSettings.php ]; then
		write_settings
	fi
	# Keep the database schema in step with the checked-out code; switching
	# branches can change it. Cheap when there is nothing to do.
	php Docker/mediawiki/maintenance/run.php update --quick
fi

echo ""
echo "NeoWiki is ready: $DDEV_PRIMARY_URL (log in as AdminName / AdminPassword)"
