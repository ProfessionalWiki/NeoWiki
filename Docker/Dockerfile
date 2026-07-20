FROM php:8.3-apache AS configured-mw

# Install needed system dependencies
RUN set -eux; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		git \
    	zip unzip libzip-dev \
		# Added for PHP 7.4+
		libonig-dev \
		# Added for image thumbnailing
		imagemagick \
		# Added for SVG handling
		librsvg2-bin \
		# Required for PDFHandler
		ghostscript \
		xpdf-utils \
		# Required for SyntaxHighlighting
		python3 \
		# Required for Scribunto
		liblua5.1-0-dev \
	; \
	rm -rf /var/lib/apt/lists/*

# Install the remaining PHP extensions we need
COPY --from=docker.io/mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
	apcu \
	calendar \
	gd \
	intl \
	luasandbox \
	mbstring \
	mysqli \
	opcache \
	zip

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer


COPY fs_overlay/ /

RUN a2enmod rewrite proxy proxy_http && a2enconf short-url

FROM configured-mw AS production-mw

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --chown=www-data:www-data mediawiki /var/www/html/w
COPY --chown=www-data:www-data composer.local.json /var/www/html/w/composer.local.json

RUN cd w && composer config --global audit.block-insecure false && composer update --no-interaction --optimize-autoloader

WORKDIR /var/www/html/w


FROM production-mw AS final-mw

LABEL org.opencontainers.image.source="https://github.com/ProfessionalWiki/NeoWiki"
LABEL org.opencontainers.image.title="NeoWiki Experimental PoC Demo"
LABEL org.opencontainers.image.licenses="GPL-2.0-or-later"
LABEL org.opencontainers.image.authors="Professional Wiki <https://Professional.Wiki>"

COPY SettingsTemplate.php /var/www/html/w/LocalSettings.php


FROM configured-mw AS dev-mw
# DEV-ONLY image. Includes editor/debug tooling and exposes exception details.
# Do NOT push to a public registry or use for any production-facing deployment.

# Use the dev-tuned php.ini (errors visible)
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# fs_overlay/usr/local/etc/php/conf.d/opcache-recommended.ini sets
# opcache.validate_timestamps=0 (production tuning). Re-enable timestamp
# validation in dev so PHP edits land on the next request without a restart.
# The 'zz-' prefix ensures this loads after the production tuning.
RUN echo 'opcache.validate_timestamps=1' > "$PHP_INI_DIR/conf.d/zz-dev-opcache.ini"

# Bake MW core source in for fast first-boot. NeoWiki source plus MW root's
# vendor/ and composer.lock are bind-mounted at runtime by docker-compose.dev.yml,
# so we deliberately do NOT run composer install here — the entrypoint runs it
# once on first boot against the bind-mounted vendor, with composer-merge-plugin
# now able to see extensions/NeoWiki/composer.json (which is also bind-mounted).
# This keeps the host's Docker/mediawiki/vendor/ in sync with what MW is loading.
COPY --chown=www-data:www-data mediawiki /var/www/html/w
COPY --chown=www-data:www-data composer.local.json /var/www/html/w/composer.local.json
# LocalSettings.php is the SettingsTemplate; it switches on MW_MODE at runtime.
# Per-worktree overrides go in LocalSettings.local.php (bind-mounted from the host
# by docker-compose.dev.yml), which is the file users actually edit.
COPY --chown=www-data:www-data SettingsTemplate.php /var/www/html/w/LocalSettings.php

RUN composer config --global audit.block-insecure false

# Tools useful inside the dev container
RUN apt-get update && apt-get install -y --no-install-recommends \
	vim nano less make \
	&& rm -rf /var/lib/apt/lists/*

# Dev entrypoint runs composer install on mounted NeoWiki extension when vendor missing,
# then execs apache. Idempotent.
COPY fs_overlay/dev-entrypoint.sh /usr/local/bin/dev-entrypoint.sh
RUN chmod +x /usr/local/bin/dev-entrypoint.sh

WORKDIR /var/www/html/w

ENTRYPOINT ["/usr/local/bin/dev-entrypoint.sh"]
CMD ["apache2-foreground"]
