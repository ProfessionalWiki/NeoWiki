# NeoWiki extension dev environment.
# Single entry point for both the Docker dev stack and developer tooling.
# Public contributors and PW devs run targets directly from here. Issue #120.

include Docker/.env
export

# ---- Project namespace and ports ---------------------------------------------

# Derive a unique project name from the extension directory.
# Main checkout: mediawiki/extensions/NeoWiki/            -> neowiki-neowiki
# Worktree:      mediawiki/extensions/NeoWiki-feature-x/  -> neowiki-neowiki-feature-x
PROJECT_NAME := $(shell echo "neowiki-$(notdir $(CURDIR))" | tr A-Z a-z)

PORT_RANGE_START := 8484
PORT_RANGE_END := 8499

# ---- Compose invocations -----------------------------------------------------

DC := docker compose -p $(PROJECT_NAME) --project-directory Docker
DC_DEV := $(DC) -f Docker/docker-compose.yml -f Docker/docker-compose.dev.yml --profile dev

IS_PODMAN := $(shell docker info 2>&1 | grep -qi podman && echo 1 || echo 0)
ifeq ($(IS_PODMAN),1)
	EXEC_USER :=
else
	EXEC_USER := --user $(shell id -u):$(shell id -g)
endif

EXEC_MW := $(DC_DEV) exec -T $(EXEC_USER) mediawiki
EXEC_MW_ROOT := $(DC_DEV) exec -T mediawiki
EXEC_NODE := $(DC_DEV) exec -T $(EXEC_USER) -e npm_config_cache=/tmp/.npm node

# Detect when this Makefile is invoked from inside the mediawiki container.
# Inside the container, PHP/composer are local; outside, they are reached via exec.
INSIDE_CONTAINER := $(shell ([ -f /.dockerenv ] || [ -f /run/.containerenv ]) && echo 1 || echo 0)

# ---- Help --------------------------------------------------------------------

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*##"; printf "Targets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  %-22s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# ---- Lifecycle (host only) ---------------------------------------------------

.PHONY: up dev stop down logs ps bash

up: ## Bring up try-it-out stack (no profile, prebuilt image)
	$(DC) up -d

dev: ensure-port ## Bring up dev stack (build image, install, seed, wait for health)
	@$(MAKE) --no-print-directory _dev-impl

_dev-impl:
	$(DC_DEV) up -d --build
	@$(MAKE) --no-print-directory _wait-mw
	@$(MAKE) --no-print-directory _first-run-seed
	@echo ""
	@echo "Dev wiki ready at: http://localhost:$$MW_SERVER_PORT"
	@echo "Project:           $(PROJECT_NAME)"

stop: ## Stop containers (preserves volumes)
	$(DC_DEV) stop

down: ## Stop and remove containers (preserves volumes)
	$(DC_DEV) down

logs: ## Tail logs from all services
	$(DC_DEV) logs -f

ps: ## Show service status
	$(DC_DEV) ps

bash: ## Shell into the mediawiki container
	$(DC_DEV) exec mediawiki bash

# ---- Port allocation ---------------------------------------------------------

# Set MW_SERVER_PORT in Docker/.env if not already set.
# Precedence: port=<flag> > MW_SERVER_PORT env > existing .env > auto-allocate.
.PHONY: ensure-port
ensure-port:
ifdef port
	@./Docker/scripts/set-port.sh $(port)
else
	@./Docker/scripts/set-port.sh "$${MW_SERVER_PORT:-}"
endif

# ---- Health gate -------------------------------------------------------------

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

# ---- First-run seed ----------------------------------------------------------

# Idempotent: skips if the database already has a wiki installed.
.PHONY: _first-run-seed
_first-run-seed:
	@if $(DC_DEV) exec -T db sh -c "mariadb -u $$MARIADB_USER -p$$MARIADB_PASSWORD $$MARIADB_DATABASE -e 'SELECT 1 FROM page LIMIT 1' 2>/dev/null" >/dev/null 2>&1; then \
		echo "Wiki already initialized; skipping install-db."; \
	else \
		$(MAKE) --no-print-directory install-db; \
		$(MAKE) --no-print-directory load-neo4j-users; \
		$(MAKE) --no-print-directory composer-install; \
		$(MAKE) --no-print-directory import-demo-data; \
	fi
	@# TS install/build are handled by the node sidecar on startup (npm install && npm run build:watch).

# ---- DB and Neo4j init -------------------------------------------------------

.PHONY: install-db load-neo4j-users wait-for-neo4j

install-db:
	$(EXEC_MW_ROOT) bash -c '/wait-for-it.sh db:3306 -t 60'
	$(EXEC_MW_ROOT) mv LocalSettings.php __LocalSettings.php
	$(EXEC_MW_ROOT) \
		php maintenance/install.php --dbuser $(MARIADB_USER) --dbpass $(MARIADB_PASSWORD) \
			--dbname $(MARIADB_DATABASE) --dbserver db:3306 --lang en \
			--pass $(MW_ADMIN_PASSWORD) \
			--server $(MW_SERVER) \
			SiteName AdminName
	$(EXEC_MW_ROOT) rm LocalSettings.php
	$(EXEC_MW_ROOT) mv __LocalSettings.php LocalSettings.php
	$(MAKE) --no-print-directory wait-for-neo4j
	$(EXEC_MW_ROOT) php maintenance/run.php update --quick

wait-for-neo4j:
	$(EXEC_MW_ROOT) bash -c '/wait-for-it.sh neo:7687 -t 60'
	$(EXEC_MW_ROOT) bash -c '/wait-for-it.sh test_neo:7689 -t 60'

load-neo4j-users:
	$(MAKE) --no-print-directory wait-for-neo4j
	$(DC_DEV) exec -T neo bash -c \
		"echo \"CREATE USER $(NEO4J_USERNAME_READ) SET PASSWORD '$(NEO4J_PASSWORD_READ)' CHANGE NOT REQUIRED; GRANT ROLE reader TO $(NEO4J_USERNAME_READ);\" | cypher-shell -u neo4j -p $(NEO4J_PASSWORD) -a bolt://localhost:7687"
	$(DC_DEV) exec -T test_neo bash -c \
		"echo \"CREATE USER mediawiki_read SET PASSWORD 'mediawiki_read' CHANGE NOT REQUIRED; GRANT ROLE reader TO mediawiki_read;\" | cypher-shell -u neo4j -p password -a bolt://localhost:7689"

# ---- Composer ----------------------------------------------------------------

.PHONY: composer-install composer-update

composer-install: ## Install composer deps for NeoWiki
ifeq ($(INSIDE_CONTAINER),1)
	composer install --optimize-autoloader
else
	$(DC_DEV) exec -T -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer $(EXEC_USER) mediawiki \
		bash -c 'cd extensions/NeoWiki && make composer-install'
endif

composer-update: ## Update composer deps for NeoWiki
ifeq ($(INSIDE_CONTAINER),1)
	composer update
else
	$(DC_DEV) exec -T -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer $(EXEC_USER) mediawiki \
		bash -c 'cd extensions/NeoWiki && make composer-update'
endif

# ---- PHP code quality (dual-mode: works inside or outside container) ---------

.PHONY: phpunit perf phpcs stan psalm cs ci test stan-baseline psalm-baseline

ci: test cs ## Run all PHP CI checks
test: phpunit ## Run PHP test suite

cs: phpcs stan ## Run code style checks (phpcs + phpstan)

phpunit: ## Run PHPUnit (use filter=X for a single test)
ifeq ($(INSIDE_CONTAINER),1)
ifdef filter
	php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --filter $(filter) < /dev/null
else
	php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist < /dev/null
endif
else
ifdef filter
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make phpunit filter=$(filter)'
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make phpunit'
endif
endif

perf: ## Run performance test group
ifeq ($(INSIDE_CONTAINER),1)
	php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --group Performance < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make perf'
endif

phpcs:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/phpcs -p -s --standard=$$(pwd)/phpcs.xml < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make phpcs'
endif

stan:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make stan'
endif

stan-baseline:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G --generate-baseline < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make stan-baseline'
endif

psalm:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/psalm --config=psalm.xml --no-diff < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make psalm'
endif

psalm-baseline:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/psalm --config=psalm.xml --set-baseline=psalm-baseline.xml < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make psalm-baseline'
endif

# ---- TypeScript (always runs in the node sidecar) ----------------------------

.PHONY: ts-install ts-update ts-build ts-build-watch ts-test ts-test-watch ts-coverage ts-lint ts-ci tsci

tsci: ts-ci ## Run TS test + build + lint
ts-ci:
	$(MAKE) --no-print-directory ts-test
	$(MAKE) --no-print-directory ts-build
	$(MAKE) --no-print-directory ts-lint

ts-install: ## npm install for NeoWiki frontend
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm install'

ts-update: ## npm update for NeoWiki frontend
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm update'

ts-build: ## Build TS bundle (one-shot; the watcher runs as a sidecar)
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run build'

ts-build-watch: ## Run the TS build watcher one-shot (the node sidecar already runs this)
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run build:watch'

ts-test: ## Run vitest (use filter=X for a single test)
ifdef filter
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run test -- $(filter)'
else
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run test'
endif

ts-test-watch: ## Run vitest in watch mode
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run test:watch'

ts-coverage: ## TS test coverage report
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run coverage'

ts-lint: ## Run TS linter
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run lint'

# ---- Maintenance -------------------------------------------------------------

.PHONY: reset import-demo-data rebuild-graph-databases update-dot-php

reset: ## Wipe DB + Neo4j volumes and reseed demo data (containers stay)
	$(DC_DEV) down --volumes
	$(DC_DEV) up -d
	@$(MAKE) --no-print-directory _wait-mw
	$(MAKE) --no-print-directory install-db
	$(MAKE) --no-print-directory load-neo4j-users
	$(MAKE) --no-print-directory import-demo-data

import-demo-data: ## Import the NeoWiki demo subjects
	$(EXEC_MW_ROOT) php extensions/NeoWiki/maintenance/ImportDemoData.php

rebuild-graph-databases: ## Rebuild Neo4j projection from MariaDB
	$(EXEC_MW_ROOT) php extensions/NeoWiki/maintenance/RebuildGraphDatabases.php

update-dot-php: ## Run MW maintenance/update.php
	$(EXEC_MW_ROOT) php maintenance/run.php update --quick

# ---- Production image --------------------------------------------------------

.PHONY: wiki-production-image
wiki-production-image: ## Build the prebuilt ghcr image
	docker build Docker --file Docker/Dockerfile --pull --target final-mw -t ghcr.io/professionalwiki/neowiki:latest
