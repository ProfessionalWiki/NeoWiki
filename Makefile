.PHONY: ci test cs phpunit phpcs stan psalm

ci: test cs
test: phpunit
cs: phpcs stan #TODO: psalm
tsci: ts-ci

phpunit:
ifdef filter
	php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --filter $(filter)
else
	php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist
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

get-neo:
	cd Neo && $(MAKE) neojs-install neojs-build

ts-install:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app/resources/ext.neowiki -u node node:20 npm install

ts-update:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app/resources/ext.neowiki -u node node:20 npm update

ts-build:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app/resources/ext.neowiki -u node node:20 npm run build

ts-build-watch:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app/resources/ext.neowiki -u node node:20 npm run build:watch

ts-ci:
	$(MAKE) ts-test && $(MAKE) ts-build && $(MAKE) ts-lint

ts-test:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app/resources/ext.neowiki -u node node:20 npm run test

ts-test-watch:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app/resources/ext.neowiki -u node node:20 npm run test:watch

ts-coverage:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app/resources/ext.neowiki -u node node:20 npm run coverage

ts-lint:
	docker run -it --rm -v "$(CURDIR)":/home/node/app -w /home/node/app/resources/ext.neowiki -u node node:20 npm run lint
