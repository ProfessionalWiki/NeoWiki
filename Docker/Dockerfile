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

RUN a2enmod rewrite && a2enconf short-url

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
