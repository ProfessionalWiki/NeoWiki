
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

install:
	docker-compose run --rm php /usr/bin/composer install

update:
	docker-compose run --rm php /usr/bin/composer update