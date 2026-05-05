# NeoWiki bundled Docker dev environment.
# Public contributors and PW devs both use this. Issue #120.

include .env
export

# ---- Project namespace and ports ---------------------------------------------

# Derive a unique project name from the parent directory of Docker/.
# Main checkout: mediawiki/extensions/NeoWiki/Docker/    -> neowiki-NeoWiki
# Worktree:      mediawiki/extensions/NeoWiki-feature-x/Docker/ -> neowiki-NeoWiki-feature-x
PROJECT_NAME := neowiki-$(notdir $(realpath $(CURDIR)/..))

# Port allocation: precedence is flag > env > .env > auto-allocate (8484-8499).
PORT_RANGE_START := 8484
PORT_RANGE_END := 8499

# ---- Compose invocations -----------------------------------------------------

DC := docker compose -p $(PROJECT_NAME)
DC_DEV := $(DC) -f docker-compose.yml -f docker-compose.dev.yml --profile dev

EXEC := $(DC_DEV) exec -T mediawiki

# Detect podman vs docker for user mapping in exec.
IS_PODMAN := $(shell docker info 2>&1 | grep -qi podman && echo 1 || echo 0)
ifeq ($(IS_PODMAN),1)
	EXEC_USER :=
else
	EXEC_USER := --user $(shell id -u):$(shell id -g)
endif

# ---- Help --------------------------------------------------------------------

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*##"; printf "Targets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  %-22s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# ---- Lifecycle ---------------------------------------------------------------

.PHONY: up dev stop down logs ps bash

up: ## Bring up try-it-out stack (no profile, prebuilt image)
	$(DC) up -d

dev: ensure-port ## Bring up dev stack (build dev image, install, seed, wait for health)
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

# Set MW_SERVER_PORT in .env if not already set.
# Precedence: port=<flag> > MW_SERVER_PORT env > existing .env > auto-allocate.
.PHONY: ensure-port
ensure-port:
ifdef port
	@./scripts/set-port.sh $(port)
else
	@./scripts/set-port.sh "$${MW_SERVER_PORT:-}"
endif

# ---- Health gate -------------------------------------------------------------

.PHONY: _wait-mw
_wait-mw:
	@echo "Waiting for MediaWiki to be reachable on port $$MW_SERVER_PORT..."
	@for i in $$(seq 1 90); do \
		if curl -fsSL "http://localhost:$$MW_SERVER_PORT/index.php/Special:Version" 2>/dev/null | grep -q 'NeoWiki\|Jeroen'; then \
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
		$(MAKE) --no-print-directory ts-install; \
		$(MAKE) --no-print-directory ts-build; \
		$(MAKE) --no-print-directory import-demo-data; \
	fi

# ---- DB and Neo4j init -------------------------------------------------------

.PHONY: install-db load-neo4j-users wait-for-neo4j

install-db:
	$(DC_DEV) exec -T mediawiki bash -c '/wait-for-it.sh db:3306 -t 60'
	$(DC_DEV) exec -T mediawiki mv LocalSettings.php __LocalSettings.php
	$(DC_DEV) exec -T mediawiki \
		php maintenance/install.php --dbuser $(MARIADB_USER) --dbpass $(MARIADB_PASSWORD) \
			--dbname $(MARIADB_DATABASE) --dbserver db:3306 --lang en \
			--pass $(MW_ADMIN_PASSWORD) \
			--server $(MW_SERVER) \
			SiteName AdminName
	$(DC_DEV) exec -T mediawiki rm LocalSettings.php
	$(DC_DEV) exec -T mediawiki mv __LocalSettings.php LocalSettings.php
	$(MAKE) --no-print-directory wait-for-neo4j
	$(DC_DEV) exec -T mediawiki php maintenance/run.php update --quick

wait-for-neo4j:
	$(DC_DEV) exec -T mediawiki bash -c '/wait-for-it.sh neo:7687 -t 60'
	$(DC_DEV) exec -T mediawiki bash -c '/wait-for-it.sh test_neo:7689 -t 60'

load-neo4j-users:
	$(MAKE) --no-print-directory wait-for-neo4j
	$(DC_DEV) exec -T neo bash -c \
		"echo \"CREATE USER $(NEO4J_USERNAME_READ) SET PASSWORD '$(NEO4J_PASSWORD_READ)' CHANGE NOT REQUIRED; GRANT ROLE reader TO $(NEO4J_USERNAME_READ);\" | cypher-shell -u neo4j -p $(NEO4J_PASSWORD) -a bolt://localhost:7687"
	$(DC_DEV) exec -T test_neo bash -c \
		"echo \"CREATE USER mediawiki_read SET PASSWORD 'mediawiki_read' CHANGE NOT REQUIRED; GRANT ROLE reader TO mediawiki_read;\" | cypher-shell -u neo4j -p password -a bolt://localhost:7689"

# ---- Dev work ----------------------------------------------------------------

.PHONY: composer-install composer-update ts-install ts-build phpunit cs tsci

composer-install: ## Install composer deps for NeoWiki
	$(DC_DEV) exec -T -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer mediawiki \
		bash -c 'cd extensions/NeoWiki && composer install --optimize-autoloader'

composer-update: ## Update composer deps for NeoWiki
	$(DC_DEV) exec -T -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer mediawiki \
		bash -c 'cd extensions/NeoWiki && composer update'

ts-install: ## npm install for NeoWiki frontend
	$(DC_DEV) exec -T -e npm_config_cache=/tmp/.npm node \
		sh -c 'cd /workspace/resources/ext.neowiki && npm install'

ts-build: ## Build TS bundle (one-shot; the watcher runs as a sidecar)
	$(DC_DEV) exec -T -e npm_config_cache=/tmp/.npm node \
		sh -c 'cd /workspace/resources/ext.neowiki && npm run build'

phpunit: ## Run PHPUnit (use filter=X for a single test)
ifdef filter
	$(EXEC) bash -c 'cd extensions/NeoWiki && make phpunit filter=$(filter)'
else
	$(EXEC) bash -c 'cd extensions/NeoWiki && make phpunit'
endif

cs: ## Run code style checks (phpcs + phpstan)
	$(EXEC) bash -c 'cd extensions/NeoWiki && make cs'

tsci: ## Run TS test + build + lint
	$(EXEC) bash -c 'cd extensions/NeoWiki && make tsci'

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
	$(DC_DEV) exec -T mediawiki php extensions/NeoWiki/maintenance/ImportDemoData.php

rebuild-graph-databases: ## Rebuild Neo4j projection from MariaDB
	$(DC_DEV) exec -T mediawiki php extensions/NeoWiki/maintenance/RebuildGraphDatabases.php

update-dot-php: ## Run MW maintenance/update.php
	$(DC_DEV) exec -T mediawiki php maintenance/run.php update --quick

# ---- Production image (existing target) --------------------------------------

.PHONY: wiki-production-image
wiki-production-image: ## Build the prebuilt ghcr image
	docker build . --file Dockerfile --pull --target final-mw -t ghcr.io/professionalwiki/neowiki:latest
