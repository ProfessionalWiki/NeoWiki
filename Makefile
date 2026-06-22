# NeoWiki extension dev environment.
# Single entry point for both the Docker dev stack and developer tooling.
# Issue #120.

# Bootstrap a local .env from .env.dist on first run.
ifeq ($(wildcard Docker/.env),)
$(shell cp Docker/.env.dist Docker/.env)
endif

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

DC := docker compose -p $(PROJECT_NAME) -f Docker/docker-compose.yml
DC_DEV := $(DC) -f Docker/docker-compose.dev.yml
DC_TOOLS := $(DC_DEV) -f Docker/docker-compose.tools.yml

IS_PODMAN := $(shell (docker --version 2>/dev/null | grep -qi podman || command -v podman >/dev/null 2>&1) && echo 1 || echo 0)
ifeq ($(IS_PODMAN),1)
	EXEC_USER :=
else
	EXEC_USER := --user $(shell id -u):$(shell id -g)
endif

EXEC_MW := $(DC) exec -T $(EXEC_USER) mediawiki
EXEC_MW_ROOT := $(DC) exec -T mediawiki
EXEC_NODE := $(DC_DEV) exec -T $(EXEC_USER) -e npm_config_cache=/tmp/.npm node

# Detect when this Makefile is invoked from inside the mediawiki container.
# Inside the container, PHP/composer are local; outside, they are reached via exec.
INSIDE_CONTAINER := $(shell ([ -f /.dockerenv ] || [ -f /run/.containerenv ]) && echo 1 || echo 0)

# ---- Help --------------------------------------------------------------------

.PHONY: help
help:
	@awk 'BEGIN {FS = ":.*##"; printf "Targets:\n"} /^[a-zA-Z_-]+:.*?##/ { printf "  %-22s %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

# ---- Lifecycle (host only) ---------------------------------------------------

.PHONY: up pull demo dev dev-tools _dev-tools-impl down remove logs ps bash

up: ## Bring up try-it-out stack (no profile, prebuilt image)
	$(DC) up -d

pull: ## Pull the latest prebuilt demo image
	$(DC) pull

demo: ## One-command demo: pull image, start stack, install + seed (idempotent)
	$(DC) pull
	$(DC) up -d
	@$(MAKE) --no-print-directory _wait-mw
	@$(MAKE) --no-print-directory _first-run-seed-demo
	@echo ""
	@echo "Demo wiki ready at: http://localhost:$$MW_SERVER_PORT"
	@echo "Log in as AdminName (password: $$MW_ADMIN_PASSWORD)."

dev: bootstrap ensure-port ## Bring up dev stack (build image, install, seed, wait for health)
	@$(MAKE) --no-print-directory _dev-impl

dev-tools: bootstrap ensure-port ## Like 'dev' but also exposes Neo4j Browser/Bolt to host
	@$(MAKE) --no-print-directory _dev-tools-impl

_dev-tools-impl:
	$(DC_TOOLS) up -d --build
	@$(MAKE) --no-print-directory _wait-mw
	@$(MAKE) --no-print-directory _first-run-seed
	@echo ""
	@echo "Dev wiki ready at:    http://localhost:$$MW_SERVER_PORT"
	@echo "Neo4j Browser:        http://localhost:$${NEO_BROWSER_PORT:-7474}"
	@echo "Neo4j Bolt endpoint:  bolt://localhost:$${NEO_BOLT_PORT:-7687}"
	@echo "Project:              $(PROJECT_NAME)"

# ---- Bootstrap (one-time, idempotent) ----------------------------------------

# Populates the gitignored prerequisites that the build context needs:
# Docker/mediawiki/ (a vendored MediaWiki core checkout) and an empty
# Docker/LocalSettings.local.php for the per-worktree override bind-mount.
#
# Only the bundled extensions/skins that Docker/SettingsTemplate.php loads are
# fetched as submodules, not MediaWiki's full bundle, to keep the clone fast and
# small. When you wfLoadExtension/wfLoadSkin something new there, add its
# submodule to the list below.
.PHONY: bootstrap
bootstrap: ## Clone MW core into Docker/mediawiki/ and prep gitignored files (idempotent)
	@if [ ! -d Docker/mediawiki/.git ]; then \
		echo "Cloning MediaWiki $${MW_BRANCH:-REL1_43} into Docker/mediawiki/..."; \
		git clone --depth 1 \
			--branch "$${MW_BRANCH:-REL1_43}" \
			"$${MW_GIT_URL:-https://github.com/wikimedia/mediawiki}" \
			Docker/mediawiki; \
		echo "Fetching the bundled extensions/skins NeoWiki loads..."; \
		git -C Docker/mediawiki submodule update --init --recursive --depth 1 \
			extensions/CodeEditor \
			extensions/ParserFunctions \
			extensions/Scribunto \
			extensions/SyntaxHighlight_GeSHi \
			extensions/VisualEditor \
			extensions/WikiEditor \
			skins/MonoBook \
			skins/Timeless \
			skins/Vector; \
	fi
	@touch Docker/LocalSettings.local.php

_dev-impl:
	$(DC_DEV) up -d --build
	@$(MAKE) --no-print-directory _wait-mw
	@$(MAKE) --no-print-directory _first-run-seed
	@echo ""
	@echo "Dev wiki ready at: http://localhost:$$MW_SERVER_PORT"
	@echo "Project:           $(PROJECT_NAME)"

down: ## Stop and remove containers (preserves volumes)
	$(DC) down --remove-orphans

remove: ## Stop and remove containers AND volumes (deletes all data)
	$(DC) down --volumes --remove-orphans

logs: ## Tail logs from all services
	$(DC_DEV) logs -f

ps: ## Show service status
	$(DC_DEV) ps

bash: ## Shell into the mediawiki container
	$(DC_DEV) exec mediawiki bash

# ---- Port allocation ---------------------------------------------------------

# Allocate host ports into Docker/.env.
# Precedence: port=<flag> > existing .env value (reused if still free) > range scan.
# Skipped when our compose stack is already up, so re-running `make dev` does not
# trigger a port change and force-recreate the running mediawiki container.
.PHONY: ensure-port
ensure-port:
ifdef port
	@./Docker/scripts/set-port.sh $(port)
else
	@if $(DC_DEV) ps -q mediawiki 2>/dev/null | grep -q .; then \
		echo "Stack already up; reusing MW_SERVER_PORT=$$MW_SERVER_PORT MAILCATCHER_PORT=$$MAILCATCHER_PORT"; \
	else \
		./Docker/scripts/set-port.sh ""; \
	fi
endif

# ---- Shell-script tests ------------------------------------------------------

# Runs the bash test suites for Docker/scripts/. Requires python3 on the host;
# does not need docker. Kept out of the default PHP/TS test targets so the host
# can opt in.
.PHONY: test-scripts
test-scripts: ## Run shell-script tests (set-port.sh, etc.)
	@./Docker/tests/test-set-port.sh

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
		$(MAKE) --no-print-directory setup-test-neo; \
		$(MAKE) --no-print-directory composer-install; \
		$(MAKE) --no-print-directory import-demo-data; \
	fi
	@# TS install/build are handled by the node sidecar on startup (npm install && npm run build:watch).

# Demo variant of the seed: no dev-only steps (test_neo, composer install). Uses
# $(DC) since the demo stack has no dev overlay. Idempotent like _first-run-seed.
.PHONY: _first-run-seed-demo
_first-run-seed-demo:
	@if $(DC) exec -T db sh -c "mariadb -u $$MARIADB_USER -p$$MARIADB_PASSWORD $$MARIADB_DATABASE -e 'SELECT 1 FROM page LIMIT 1' 2>/dev/null" >/dev/null 2>&1; then \
		echo "Wiki already initialized; skipping install."; \
	else \
		$(MAKE) --no-print-directory install-db; \
		$(MAKE) --no-print-directory load-neo4j-users; \
		$(MAKE) --no-print-directory import-demo-data; \
	fi

# ---- DB and Neo4j init -------------------------------------------------------

.PHONY: install-db load-neo4j-users wait-for-neo4j setup-test-neo

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

load-neo4j-users:
	$(MAKE) --no-print-directory wait-for-neo4j
	$(DC) exec -T neo bash -c \
		"echo \"CREATE USER $(NEO4J_USERNAME_READ) SET PASSWORD '$(NEO4J_PASSWORD_READ)' CHANGE NOT REQUIRED; GRANT ROLE reader TO $(NEO4J_USERNAME_READ);\" | cypher-shell -u neo4j -p $(NEO4J_PASSWORD) -a bolt://localhost:7687"

# Dev-only: wait for and seed the test_neo instance. Not called from prod or CI flows.
setup-test-neo:
	$(EXEC_MW_ROOT) bash -c '/wait-for-it.sh test_neo:7689 -t 60'
	$(DC_DEV) exec -T test_neo bash -c \
		"echo \"CREATE USER mediawiki_read SET PASSWORD 'mediawiki_read' CHANGE NOT REQUIRED; GRANT ROLE reader TO mediawiki_read;\" | cypher-shell -u neo4j -p password -a bolt://localhost:7689"

# ---- Composer ----------------------------------------------------------------

.PHONY: composer-install composer-update

composer-install: ## Install composer deps for NeoWiki
ifeq ($(INSIDE_CONTAINER),1)
	composer install --optimize-autoloader
else
	$(DC_DEV) exec -T -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer $(EXEC_USER) mediawiki \
		bash -c 'cd extensions/NeoWiki && make composer-install' < /dev/null
endif

composer-update: ## Update composer deps for NeoWiki
ifeq ($(INSIDE_CONTAINER),1)
	composer update
else
	$(DC_DEV) exec -T -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer $(EXEC_USER) mediawiki \
		bash -c 'cd extensions/NeoWiki && make composer-update' < /dev/null
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
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make phpunit filter=$(filter)' < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make phpunit' < /dev/null
endif
endif

perf: ## Run performance test group
ifeq ($(INSIDE_CONTAINER),1)
	php ../../tests/phpunit/phpunit.php -c phpunit.xml.dist --group Performance < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make perf' < /dev/null
endif

phpcs:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/phpcs -p -s --standard=$$(pwd)/phpcs.xml < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make phpcs' < /dev/null
endif

stan:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make stan' < /dev/null
endif

stan-baseline:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=2G --generate-baseline < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make stan-baseline' < /dev/null
endif

psalm:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/psalm --config=psalm.xml --no-diff < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make psalm' < /dev/null
endif

psalm-baseline:
ifeq ($(INSIDE_CONTAINER),1)
	vendor/bin/psalm --config=psalm.xml --set-baseline=psalm-baseline.xml < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make psalm-baseline' < /dev/null
endif

# ---- TypeScript (always runs in the node sidecar) ----------------------------

# The node sidecar runs `npm install && npm run build:watch` on startup. Targets
# that depend on node_modules being populated should depend on _wait-node so the
# first invocation after `make dev` does not race the sidecar's initial install.
.PHONY: _wait-node
_wait-node:
	@for i in $$(seq 1 60); do \
		if [ -f resources/ext.neowiki/node_modules/.package-lock.json ]; then \
			exit 0; \
		fi; \
		sleep 1; \
	done; \
	echo "Timed out waiting for node_modules; the node sidecar may not have started." >&2; exit 1

.PHONY: ts-install ts-update ts-build ts-build-watch ts-test ts-test-watch ts-coverage ts-lint ts-ci tsci

tsci: ts-ci ## Run TS test + build + lint
ts-ci:
	$(MAKE) --no-print-directory ts-test
	$(MAKE) --no-print-directory ts-build
	$(MAKE) --no-print-directory ts-lint

ts-install: ## npm install for NeoWiki frontend
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm install' < /dev/null

ts-update: ## npm update for NeoWiki frontend
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm update' < /dev/null

ts-build: _wait-node ## Build TS bundle (one-shot; the watcher runs as a sidecar)
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run build' < /dev/null

ts-build-watch: _wait-node ## Run the TS build watcher one-shot (the node sidecar already runs this)
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run build:watch' < /dev/null

ts-test: _wait-node ## Run vitest (use filter=X for a single test)
ifdef filter
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run test -- $(filter)' < /dev/null
else
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run test' < /dev/null
endif

ts-test-watch: _wait-node ## Run vitest in watch mode
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run test:watch' < /dev/null

ts-coverage: _wait-node ## TS test coverage report
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run coverage' < /dev/null

ts-lint: _wait-node ## Run TS linter
	$(EXEC_NODE) sh -c 'cd /workspace/resources/ext.neowiki && npm run lint' < /dev/null

# ---- Maintenance -------------------------------------------------------------

.PHONY: reset import-demo-data rebuild-graph-databases update-dot-php smoke-test

# Wipe and reseed the dev stack. The teardown (make remove) is stack-agnostic —
# --remove-orphans reaps the dev sidecars too — while the up must be the dev
# variant, since reset rebuilds the dev stack (note setup-test-neo below).
reset: ## Wipe DB + Neo4j volumes and reseed demo data (recreates the dev stack)
	$(MAKE) --no-print-directory remove
	$(DC_DEV) up -d
	@$(MAKE) --no-print-directory _wait-mw
	$(MAKE) --no-print-directory install-db
	$(MAKE) --no-print-directory load-neo4j-users
	$(MAKE) --no-print-directory setup-test-neo
	$(MAKE) --no-print-directory import-demo-data

import-demo-data: ## Import the NeoWiki demo subjects
	$(EXEC_MW_ROOT) php maintenance/run.php NeoWiki:ImportDemoData

rebuild-graph-databases: ## Rebuild Neo4j projection from MariaDB
	$(EXEC_MW_ROOT) php maintenance/run.php NeoWiki:RebuildGraphDatabases

update-dot-php: ## Run MW maintenance/update.php
	$(EXEC_MW_ROOT) php maintenance/run.php update --quick

smoke-test: ## Hit the running wiki from outside and verify it responds (CI smoke test)
	bash Docker/tests/smoke.sh

# ---- Production image --------------------------------------------------------

.PHONY: wiki-production-image
wiki-production-image: ## Build the prebuilt ghcr image
	docker build Docker --file Docker/Dockerfile --pull --target final-mw -t ghcr.io/professionalwiki/neowiki:latest
