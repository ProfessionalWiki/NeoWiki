# NeoWiki tooling entry point.
#
# The dev environment runs on ddev (`ddev start`; see .ddev/README.md). The
# targets in the "Development" sections wrap the day-to-day tooling so
# `make phpunit`, `make cs`, etc. exec into the ddev containers.
#
# The demo / try-it-out stack (prebuilt image) and the production image build
# use docker compose directly and are independent of ddev — see the "Demo" and
# "Production image" sections and Docker/README.md.

# Bootstrap a local .env from .env.dist on first run (demo stack settings).
ifeq ($(wildcard Docker/.env),)
$(shell cp Docker/.env.dist Docker/.env)
endif

include Docker/.env
export

# ---- Demo stack (docker compose, prebuilt image) ------------------------------

DC := docker compose -p neowiki-demo -f Docker/docker-compose.yml
EXEC_DEMO_MW := $(DC) exec -T mediawiki

# ---- Dev environment (ddev) ----------------------------------------------------

# The checkout is bind-mounted into the MediaWiki core tree; run tools from
# there so relative paths behave exactly as in CI's nested layout.
EXT_IN_MW := Docker/mediawiki/extensions/NeoWiki

# ddev derives the project name from the checkout directory; its per-project
# compose resources (the Neo4j volumes in `make reset`) are prefixed with it.
DDEV_PROJECT := $(shell echo "$(notdir $(CURDIR))" | tr 'A-Z' 'a-z')

# ---- Help --------------------------------------------------------------------

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*##"; printf "Targets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  %-22s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# ---- Dev lifecycle (ddev) ------------------------------------------------------

.PHONY: dev bash logs reset

dev: ## Bring up the dev environment (alias for `ddev start`)
	ddev start

bash: ## Shell into the web container (alias for `ddev ssh`)
	ddev ssh

logs: ## Tail web container logs (other services: `ddev logs -s neo` etc.)
	ddev logs -f

reset: ## Wipe DB + Neo4j data and reseed demo data (recreates the dev environment)
	ddev delete --omit-snapshot --yes
	@# ddev delete already removes the project's volumes; this is a best-effort
	@# fallback for ddev versions that leave custom-service volumes behind.
	-@docker volume rm ddev-$(DDEV_PROJECT)_neo4j-data ddev-$(DDEV_PROJECT)_test-neo4j-data 2>/dev/null || true
	ddev start

# ---- Composer ------------------------------------------------------------------

.PHONY: composer-install composer-update

composer-install: ## Install composer deps for NeoWiki
	ddev composer install --optimize-autoloader

composer-update: ## Update composer deps for NeoWiki
	ddev composer update

# ---- PHP tests and code quality ------------------------------------------------

.PHONY: phpunit perf phpcs stan psalm cs ci test stan-baseline psalm-baseline

ci: test cs ## Run all PHP CI checks
test: phpunit ## Run PHP test suite

cs: phpcs stan ## Run code style checks (phpcs + phpstan)

phpunit: ## Run PHPUnit (use filter=X for a single test)
ifdef filter
	ddev exec bash -c 'cd $(EXT_IN_MW) && php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --filter $(filter)'
else
	ddev exec bash -c 'cd $(EXT_IN_MW) && php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist'
endif

perf: ## Run performance test group
	ddev exec bash -c 'cd $(EXT_IN_MW) && php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --group Performance'

# The ruleset must be passed as a relative path: with an absolute one, phpcs
# registers the sniff standards under a second spelling next to the
# composer-installed_paths one and fatals on duplicate sniff classes.
phpcs:
	ddev exec bash -c 'cd $(EXT_IN_MW) && vendor/bin/phpcs -p -s --standard=phpcs.xml'

stan:
	ddev exec bash -c 'cd $(EXT_IN_MW) && vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G'

stan-baseline:
	ddev exec bash -c 'cd $(EXT_IN_MW) && vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G --generate-baseline'

psalm:
	ddev exec bash -c 'cd $(EXT_IN_MW) && vendor/bin/psalm --config=psalm.xml --no-diff'

psalm-baseline:
	ddev exec bash -c 'cd $(EXT_IN_MW) && vendor/bin/psalm --config=psalm.xml --set-baseline=psalm-baseline.xml'

# ---- TypeScript (runs in the node watcher service) -----------------------------

# The node service runs `npm install && npm run build:watch` on startup. Targets
# that depend on node_modules being populated should depend on _wait-node so the
# first invocation after `ddev start` does not race the initial install.
.PHONY: _wait-node
_wait-node:
	@for i in $$(seq 1 60); do \
		if [ -f resources/ext.neowiki/node_modules/.package-lock.json ]; then \
			exit 0; \
		fi; \
		sleep 1; \
	done; \
	echo "Timed out waiting for node_modules; the node service may not have started." >&2; exit 1

.PHONY: ts-install ts-update ts-build ts-build-watch ts-test ts-test-watch ts-coverage ts-lint ts-ci tsci

tsci: ts-ci ## Run TS test + build + lint
ts-ci:
	$(MAKE) --no-print-directory ts-test
	$(MAKE) --no-print-directory ts-build
	$(MAKE) --no-print-directory ts-lint

ts-install: ## npm install for NeoWiki frontend
	ddev exec -s node npm install

ts-update: ## npm update for NeoWiki frontend
	ddev exec -s node npm update

ts-build: _wait-node ## Build TS bundle (one-shot; the watcher runs as a service)
	ddev exec -s node npm run build

ts-build-watch: _wait-node ## Run the TS build watcher one-shot (the node service already runs this)
	ddev exec -s node npm run build:watch

ts-test: _wait-node ## Run vitest (use filter=X for a single test)
ifdef filter
	ddev exec -s node npm run test -- $(filter)
else
	ddev exec -s node npm run test
endif

ts-test-watch: _wait-node ## Run vitest in watch mode
	ddev exec -s node npm run test:watch

ts-coverage: _wait-node ## TS test coverage report
	ddev exec -s node npm run coverage

ts-lint: _wait-node ## Run TS linter
	ddev exec -s node npm run lint

# ---- Maintenance (dev wiki) -----------------------------------------------------

.PHONY: import-demo-data rebuild-graph-databases update-dot-php

import-demo-data: ## Import the NeoWiki demo subjects
	ddev exec php Docker/mediawiki/maintenance/run.php NeoWiki:ImportDemoData

rebuild-graph-databases: ## Rebuild Neo4j projection from MariaDB
	ddev exec php Docker/mediawiki/maintenance/run.php NeoWiki:RebuildGraphDatabases

update-dot-php: ## Run MW maintenance/update.php
	ddev exec php Docker/mediawiki/maintenance/run.php update --quick

# ---- Shell-script tests ---------------------------------------------------------

# Runs the bash test suite for Docker/scripts/. Does not need docker.
.PHONY: test-scripts
test-scripts: ## Run shell-script tests (preflight.sh)
	@./Docker/tests/test-preflight.sh

# ---- Demo / try-it-out stack (prebuilt image; no ddev) ---------------------------

.PHONY: up pull demo down remove _preflight doctor

# Fail fast on a broken Docker runtime (Docker or Compose missing, daemon down or
# denied) before the lifecycle targets do expensive work. Source: Docker/scripts/preflight.sh.
_preflight:
	@./Docker/scripts/preflight.sh

doctor: ## Diagnose demo-stack prerequisites (Docker runtime)
	@PREFLIGHT_VERBOSE=1 ./Docker/scripts/preflight.sh

up: _preflight ## Bring up try-it-out stack (no profile, prebuilt image)
	$(DC) up -d

pull: _preflight ## Pull the latest prebuilt demo image
	$(DC) pull

demo: _preflight ## One-command demo: pull image, start stack, install + seed (idempotent)
	$(DC) pull
	$(DC) up -d
	@$(MAKE) --no-print-directory _wait-mw
	@$(MAKE) --no-print-directory _first-run-seed-demo
	@echo ""
	@echo "Demo wiki ready at: http://localhost:$$MW_SERVER_PORT"
	@echo "Log in as AdminName (password: $$MW_ADMIN_PASSWORD)."

down: ## Stop and remove demo-stack containers (preserves volumes)
	$(DC) down --remove-orphans

remove: ## Stop and remove demo-stack containers AND volumes (deletes all data)
	$(DC) down --volumes --remove-orphans

.PHONY: _wait-mw
_wait-mw:
	@echo "Waiting for MediaWiki to be reachable on port $$MW_SERVER_PORT..."
	@for i in $$(seq 1 90); do \
		if curl -sSo /dev/null "http://localhost:$$MW_SERVER_PORT/" 2>/dev/null; then \
			echo "Reachable."; exit 0; \
		fi; \
		sleep 1; \
	done; \
	echo "Timed out waiting for MediaWiki." >&2; exit 1

# Idempotent first-run seed: skips if the database already has a wiki installed.
.PHONY: _first-run-seed-demo
_first-run-seed-demo:
	@if $(DC) exec -T db sh -c "mariadb -u $$MARIADB_USER -p$$MARIADB_PASSWORD $$MARIADB_DATABASE -e 'SELECT 1 FROM page LIMIT 1' 2>/dev/null" >/dev/null 2>&1; then \
		echo "Wiki already initialized; skipping install."; \
	else \
		$(MAKE) --no-print-directory install-db; \
		$(MAKE) --no-print-directory load-neo4j-users; \
		$(MAKE) --no-print-directory _demo-import-demo-data; \
	fi

# ---- Demo-stack DB and Neo4j init (also used by the image-build CI) --------------

.PHONY: install-db load-neo4j-users wait-for-neo4j _demo-import-demo-data smoke-test

install-db:
	$(EXEC_DEMO_MW) bash -c '/wait-for-it.sh db:3306 -t 60'
	$(EXEC_DEMO_MW) mv LocalSettings.php __LocalSettings.php
	$(EXEC_DEMO_MW) \
		php maintenance/install.php --dbuser $(MARIADB_USER) --dbpass $(MARIADB_PASSWORD) \
			--dbname $(MARIADB_DATABASE) --dbserver db:3306 --lang en \
			--pass $(MW_ADMIN_PASSWORD) \
			--server $(MW_SERVER) \
			SiteName AdminName
	$(EXEC_DEMO_MW) rm LocalSettings.php
	$(EXEC_DEMO_MW) mv __LocalSettings.php LocalSettings.php
	$(MAKE) --no-print-directory wait-for-neo4j
	$(EXEC_DEMO_MW) php maintenance/run.php update --quick

wait-for-neo4j:
	$(EXEC_DEMO_MW) bash -c '/wait-for-it.sh neo:7687 -t 60'

load-neo4j-users:
	$(MAKE) --no-print-directory wait-for-neo4j
	$(DC) exec -T neo bash -c \
		"echo \"CREATE USER $(NEO4J_USERNAME_READ) SET PASSWORD '$(NEO4J_PASSWORD_READ)' CHANGE NOT REQUIRED; GRANT ROLE reader TO $(NEO4J_USERNAME_READ);\" | cypher-shell -u neo4j -p $(NEO4J_PASSWORD) -a bolt://localhost:7687"

_demo-import-demo-data:
	$(EXEC_DEMO_MW) php maintenance/run.php NeoWiki:ImportDemoData

smoke-test: ## Hit the running demo wiki from outside and verify it responds (CI smoke test)
	bash Docker/tests/smoke.sh

# ---- Production image ----------------------------------------------------------

.PHONY: wiki-production-image
wiki-production-image: ## Build the prebuilt ghcr image
	docker build Docker --file Docker/Dockerfile --pull --target final-mw -t ghcr.io/professionalwiki/neowiki:latest
