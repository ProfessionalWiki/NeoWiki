.PHONY: ci test cs phpunit phpcs stan psalm

ci: test cs
test: phpunit
cs: phpcs stan psalm

phpunit:
ifdef filter
	../../vendor/bin/phpunit -c phpunit.xml.dist --filter $(filter)
else
	../../vendor/bin/phpunit -c phpunit.xml.dist
endif

perf:
	php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --group Performance

phpcs:
	vendor/bin/phpcs -p -s --standard=$(shell pwd)/phpcs.xml

stan:
	vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G

stan-baseline:
	vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G --generate-baseline

psalm:
	vendor/bin/psalm --config=psalm.xml --no-diff

psalm-baseline:
	vendor/bin/psalm --config=psalm.xml --set-baseline=psalm-baseline.xml

test-frontend-docker:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app -u node node:20 npm install && npm run test
