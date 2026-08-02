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
# The `test` profile holds the test-only backends: test_neo and test_qlever.
DC_TEST := $(DC_DEV) --profile test

# Detect the engine from what `docker` actually is (its version string), not from
# whether a `podman` binary happens to exist: a stray podman binary alongside real
# Docker must not flip this, or exec would run as container-root and write
# root-owned files into bind mounts. `docker --version` is daemon-independent, so it
# is safe to evaluate on every make invocation (unlike `docker info`).
IS_PODMAN := $(shell docker --version 2>/dev/null | grep -qi podman && echo 1 || echo 0)
ifeq ($(IS_PODMAN),1)
	# Rootless Podman maps container-root to the host user, so bind-mount writes
	# already land with host ownership. Forcing --user would map into the subuid range.
	EXEC_USER :=
	# The node sidecar runs as container-root, which Podman remaps to the host user.
	NODE_USER := 0:0
else
	# Rootful Docker does no UID remap: run exec as the host uid:gid so files written
	# into bind mounts are owned by the host user, not root.
	EXEC_USER := --user $(shell id -u):$(shell id -g)
	# The node sidecar is a long-running service, so its user is set at compose-up
	# time (not via exec): run it as the host uid:gid so node_modules/ and dist/ are
	# host-owned, matching EXEC_USER. Otherwise the host-user TS targets cannot write
	# them. Exported so `docker compose` interpolates ${NODE_USER} in the dev compose.
	NODE_USER := $(shell id -u):$(shell id -g)
endif
export NODE_USER

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

.PHONY: up pull demo dev dev-tools _dev-tools-impl down remove logs ps bash _preflight doctor

# Fail fast on a broken Docker runtime (Docker or Compose missing, daemon down or
# denied) before the lifecycle targets do expensive work. Source: Docker/scripts/preflight.sh.
_preflight:
	@./Docker/scripts/preflight.sh

doctor: ## Diagnose dev-environment prerequisites (Docker runtime)
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

dev: _preflight bootstrap ensure-port ## Bring up dev stack (build image, install, seed, wait for health)
	@$(MAKE) --no-print-directory _dev-impl

dev-tools: _preflight bootstrap ensure-port ## Like 'dev' but also exposes Neo4j Browser/Bolt to host
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
	$(DC_TEST) ps

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
test-scripts: ## Run shell-script tests (set-port.sh, preflight.sh, etc.)
	@./Docker/tests/test-set-port.sh
	@./Docker/tests/test-preflight.sh

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

.PHONY: install-db load-neo4j-users wait-for-neo4j setup-test-neo test-backends

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

# Runs before every PHP test target.
#
# The already-running check is a speed optimization, not a correctness requirement: the seed
# is idempotent. Without it every `make phpunit filter=X` would pay a few seconds for the
# compose up and the cypher-shell JVM.
#
# Inside the mediawiki container there is no compose to drive, so it can only report that the
# backends are missing.
test-backends: ## Start and seed the test-only backends (the PHP test targets do this for you)
ifeq ($(INSIDE_CONTAINER),1)
	@if ! /wait-for-it.sh test_neo:7689 -t 1 >/dev/null 2>&1 \
		|| ! /wait-for-it.sh test_qlever:7019 -t 1 >/dev/null 2>&1; then \
		echo "The test-only backends are not running. Start them on the host with" >&2; \
		echo "'make test-backends', or run the PHP test targets from the host." >&2; \
		exit 1; \
	fi
else
	@if [ "$$(docker ps --filter label=com.docker.compose.project=$(PROJECT_NAME) \
			--format '{{.Label "com.docker.compose.service"}}' \
			| grep -cE '^(test_neo|test_qlever)$$')" = "2" ]; then \
		exit 0; \
	fi; \
	$(DC_TEST) up -d; \
	$(MAKE) --no-print-directory setup-test-neo; \
	$(EXEC_MW_ROOT) bash -c '/wait-for-it.sh test_qlever:7019 -t 120'
endif

# Dev-only: wait for and seed the test_neo instance. Not called from prod or CI flows.
setup-test-neo:
	$(EXEC_MW_ROOT) bash -c '/wait-for-it.sh test_neo:7689 -t 60'
	$(DC_TEST) exec -T test_neo bash -c \
		"echo \"CREATE USER mediawiki_read IF NOT EXISTS SET PASSWORD 'mediawiki_read' CHANGE NOT REQUIRED; GRANT ROLE reader TO mediawiki_read;\" | cypher-shell -u neo4j -p password -a bolt://localhost:7689"

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

phpunit: test-backends ## Run PHPUnit (use filter=X for a single test)
ifeq ($(INSIDE_CONTAINER),1)
ifdef filter
	composer phpunit -- --filter $(filter) < /dev/null
else
	composer phpunit < /dev/null
endif
else
ifdef filter
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make phpunit filter=$(filter)' < /dev/null
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make phpunit' < /dev/null
endif
endif

perf: test-backends ## Run performance test group
ifeq ($(INSIDE_CONTAINER),1)
	composer phpunit -- --group Performance < /dev/null
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
	$(MAKE) --no-print-directory import-demo-data

import-demo-data: ## Import the NeoWiki demo subjects
	$(EXEC_MW_ROOT) php maintenance/run.php NeoWiki:ImportDemoData

rebuild-graph-databases: ## Rebuild every configured backend's projection from MariaDB
	$(EXEC_MW_ROOT) php maintenance/run.php NeoWiki:RebuildGraphDatabases

update-dot-php: ## Run MW maintenance/update.php
	$(EXEC_MW_ROOT) php maintenance/run.php update --quick

smoke-test: ## Hit the running wiki from outside and verify it responds (CI smoke test)
	bash Docker/tests/smoke.sh

# ---- Performance test data ---------------------------------------------------

# Build, benchmark and restore big synthetic wikis. Artifacts land in the gitignored perf/.
# Usage and caveats are in README.md#performance-test-data. perf-snapshot and perf-restore
# drive the containers, so unlike the other two they run on the host only.

PERF_DIR := perf
PERF_DUMP := $(PERF_DIR)/dump.xml
PERF_SNAPSHOT_DIR := $(PERF_DIR)/snapshot
PERF_SQL := $(PERF_SNAPSHOT_DIR)/mediawiki.sql
PERF_NEO := $(PERF_SNAPSHOT_DIR)/neo4j.dump

# `neo4j-admin database dump` and `load` refuse to touch a database the server has
# mounted, so both are bracketed by STOP/START DATABASE against the system database.
NEO_CYPHER := $(DC) exec -T neo cypher-shell -u $(NEO4J_USERNAME) -p $(NEO4J_PASSWORD)

# `docker compose exec` lands in the neo container as root, while the server runs as
# neo4j. Loading as root leaves a store the server cannot write, which then fails to
# start, so neo4j-admin runs as the owning user.
NEO_ADMIN := $(DC) exec -T --user neo4j neo neo4j-admin

# Shell fragments rather than targets of their own: make runs any recipe line that mentions
# $(MAKE) even under --dry-run, so recursing into a stop/start target would let
# `make -n perf-snapshot` truncate the snapshot and `make -n perf-restore` overwrite the live
# store for real.
NEO_STOP = $(NEO_CYPHER) -d system 'STOP DATABASE neo4j WAIT' < /dev/null

# START reports success even when the store it mounted is unusable, so prove the database
# actually answers queries: a silently broken restore is worse than a failed one.
NEO_START = { $(NEO_CYPHER) -d system 'START DATABASE neo4j WAIT' < /dev/null \
	&& $(NEO_CYPHER) -d neo4j 'RETURN 1' < /dev/null > /dev/null; } \
	|| { echo "The neo4j database did not come back online." >&2; exit 1; }

.PHONY: perf-generate perf-import perf-snapshot perf-restore
.PHONY: _require-pages _require-snapshot

_require-pages:
	@[ -n "$(pages)" ] || { echo "Usage: make perf-generate pages=N [subjects=10] [seed=1]" >&2; exit 1; }

# Generated beside the final name and moved into place on success, for the same reason as the
# snapshot halves: the generator truncates its output at fopen and writes the header — which
# advertises the full intended page and Subject counts — before the first page. Writing straight
# to the final name would leave an abandoned or disk-full run as a truncated dump that perf-import's
# existence check accepts, and importDump then commits every complete page in it before failing.
perf-generate: _require-pages ## Generate a synthetic wiki XML dump (pages=N [subjects=10] [seed=1])
	@mkdir -p $(PERF_DIR)
ifeq ($(INSIDE_CONTAINER),1)
	@rm -f $(PERF_DUMP).part
	php ../../maintenance/run.php NeoWiki:GeneratePerformanceDump \
		--pages $(pages) \
		--subjects-per-page $(or $(subjects),10) \
		--seed $(or $(seed),1) \
		--output $(PERF_DUMP).part < /dev/null
	@mv $(PERF_DUMP).part $(PERF_DUMP)
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make perf-generate pages=$(pages) subjects=$(subjects) seed=$(seed)' < /dev/null
endif

perf-import: ## Import perf/dump.xml, reporting elapsed time and throughput
ifeq ($(INSIDE_CONTAINER),1)
	@set -e; \
	[ -f $(PERF_DUMP) ] || { echo "$(PERF_DUMP) not found; run 'make perf-generate pages=N' first." >&2; exit 1; }; \
	pages=$$(head -5 $(PERF_DUMP) | grep -o ' pages="[0-9]*"' | tr -dc 0-9); \
	subjects=$$(head -5 $(PERF_DUMP) | grep -o ' total-subjects="[0-9]*"' | tr -dc 0-9); \
	start=$$(date +%s.%N); \
	php ../../maintenance/run.php importDump --no-updates $(CURDIR)/$(PERF_DUMP) < /dev/null; \
	end=$$(date +%s.%N); \
	awk -v s=$$start -v e=$$end -v p="$$pages" -v n="$$subjects" 'BEGIN { \
		d = e - s; \
		printf "\nImported in %.1f s", d; \
		if ( p > 0 && d > 0 ) printf ": %d pages (%.2f/sec), %d Subjects (%.2f/sec)", p, p / d, n, n / d; \
		printf "\n"; \
	}'
else
	$(EXEC_MW) bash -c 'cd extensions/NeoWiki && make perf-import' < /dev/null
endif

# Both halves are written beside their final names and moved into place only once both are
# complete, so a run that fails or is interrupted leaves the previous snapshot untouched. A
# host-side redirect creates its file the moment the line starts, so writing straight to the
# final names would leave a truncated half that _require-snapshot accepts and perf-restore
# then loads over the live data.
#
# The moves sit inside the same shell as the dump, ahead of the EXIT trap that restarts Neo4j:
# `set -e` still skips them when a dump fails, but a restart that comes back unhealthy — the one
# thing the trap can fail on — then still fails the target without discarding a snapshot whose
# halves are both complete.
perf-snapshot: ## Snapshot MariaDB + Neo4j into perf/snapshot/
	@mkdir -p $(PERF_SNAPSHOT_DIR)
	@rm -f $(PERF_SQL).part $(PERF_NEO).part
	$(DC) exec -T db mariadb-dump -u root -p$(MARIADB_ROOT_PASSWORD) \
		--single-transaction --add-drop-database --databases $(MARIADB_DATABASE) > $(PERF_SQL).part < /dev/null
	@set -e; \
		neo_start() { $(NEO_START); }; \
		trap neo_start EXIT; \
		$(NEO_STOP); \
		$(NEO_ADMIN) database dump neo4j --to-stdout > $(PERF_NEO).part < /dev/null; \
		mv $(PERF_SQL).part $(PERF_SQL); \
		mv $(PERF_NEO).part $(PERF_NEO)
	@echo "Snapshot written to $(PERF_SNAPSHOT_DIR)/"

_require-snapshot:
	@[ -s $(PERF_SQL) ] && [ -s $(PERF_NEO) ] \
		|| { echo "No snapshot in $(PERF_SNAPSHOT_DIR)/; run 'make perf-snapshot' first." >&2; exit 1; }

perf-restore: _require-snapshot ## Restore perf/snapshot/, replacing this stack's MariaDB and Neo4j data
	$(DC) exec -T db mariadb -u root -p$(MARIADB_ROOT_PASSWORD) < $(PERF_SQL)
	@set -e; \
		neo_start() { $(NEO_START); }; \
		trap neo_start EXIT; \
		$(NEO_STOP); \
		$(NEO_ADMIN) database load neo4j --from-stdin --overwrite-destination < $(PERF_NEO)
	@echo "Restored. Run 'make update-dot-php' to bring the MediaWiki schema up to date with this checkout."

# ---- Production image --------------------------------------------------------

.PHONY: wiki-production-image
wiki-production-image: ## Build the prebuilt ghcr image
	docker build Docker --file Docker/Dockerfile --pull --target final-mw -t ghcr.io/professionalwiki/neowiki:latest
