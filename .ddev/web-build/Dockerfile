# Install the luasandbox PHP extension, which Scribunto's luaautodetect engine
# prefers and Docker/Dockerfile installs for production — without it development
# falls back to LuaStandalone, whose bundled interpreters are x86-only and cannot
# exec on arm64, so no Lua module renders at all.
#
# Building it from PECL is the route DDEV documents for extensions deb.sury.org
# does not package, and it packages no luasandbox. Debian's php-luasandbox is
# built for PHP 8.4 (phpapi-20240924), so it will not load here either. The
# install-php-extensions that Docker/Dockerfile uses is not an option: it drives
# the official PHP images' docker-php-ext-* scaffolding, which this image, built
# on deb.sury.org packages, does not have.
#
# php${DDEV_PHP_VERSION}-dev upgrades php-fpm, which stops to ask about the
# php-fpm.conf DDEV has modified and fails the build on the unanswerable prompt.
# confold keeps DDEV's copies, which matters most for pool.d/www.conf: it carries
# DDEV's socket path and clear_env, and the maintainer's version would break FPM.
RUN (apt-get update || true) && DEBIAN_FRONTEND=noninteractive apt-get install -y \
		-o Dpkg::Options::="--force-confold" --no-install-recommends --no-install-suggests \
		build-essential \
		liblua5.1-0-dev \
		php-pear \
		php${DDEV_PHP_VERSION}-dev
RUN pecl install luasandbox
RUN echo "extension=luasandbox.so" > /etc/php/${DDEV_PHP_VERSION}/mods-available/luasandbox.ini
RUN phpenmod -v ${DDEV_PHP_VERSION} luasandbox
