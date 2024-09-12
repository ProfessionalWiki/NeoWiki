
ci: test cs
test: phpunit
cs: phpcs stan

phpunit:
ifdef filter
	docker-compose run --rm php vendor/bin/phpunit -c phpunit.xml.dist --filter $(filter)
else
	docker-compose run --rm php vendor/bin/phpunit -c phpunit.xml.dist
endif

phpcs:
	docker-compose run --rm php vendor/bin/phpcs -p -s --standard=phpcs.xml

stan:
	docker-compose run --rm php vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G

stan-baseline:
	docker-compose run --rm php vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G --generate-baseline

psalm:
	docker-compose run --rm php vendor/bin/psalm --config=psalm.xml --no-diff

psalm-baseline:
	docker-compose run --rm php vendor/bin/psalm --config=psalm.xml --set-baseline=psalm-baseline.xml

baseline:
	$(MAKE) stan-baseline
	$(MAKE) psalm-baseline

composer-install: install
install:
	docker-compose run --rm php /usr/bin/composer install

composer-update: update
update:
	docker-compose run --rm php /usr/bin/composer update

neojs-install:
	docker-compose run --rm node npm install

neojs-update:
	docker-compose run --rm node npm update

neojs-test:
	docker-compose run --rm node npm run test

neojs-test-watch:
	docker-compose run --rm node npm run test:watch

neojs-lint:
	docker-compose run --rm node npm run lint

neojs-build:
	docker-compose run --rm node npm run build

neojs-build-watch:
	docker-compose run --rm node npm run build:watch
