# NeoWiki Docker

NeoWiki is in the experimental proof of concept phase. It is not production ready, public interfaces will change,
big structural changes will happen, and key functionality is still missing.

This directory contains files for the Dockerized development environment and for the pre-built demo Docker image.

## Installing and running

This file is a reference for the `Docker/` directory itself. For instructions:

- Demo / try-it-out stack and server (Caddy) deployment: see
  [Installation](../docs/operations/installation.md).
- Developer workflow (`make dev`, `make phpunit`, etc.): see [`../README.md`](../README.md).
  All commands run from the extension root, not from `Docker/`.

## Reserved host ports

`make dev` allocates host ports from these ranges. Auto-allocation skips ports that
are already bound on the host. The demo stack allocates nothing: it uses the values in
`Docker/.env` as they stand.

| Variable           | Range       | Default | Service                    |
|--------------------|-------------|---------|----------------------------|
| `MW_SERVER_PORT`   | 8484-8499   | 8484    | MediaWiki HTTP             |
| `MAILCATCHER_PORT` | 8025-8040   | 8025    | Mailcatcher web UI         |
| `NEO_BROWSER_PORT` | (see below) | 7474    | Neo4j Browser (opt-in)     |
| `NEO_BOLT_PORT`    | (see below) | 7687    | Neo4j Bolt endpoint (opt-in) |
| `QLEVER_PORT`      | (see below) | 7019    | QLever SPARQL endpoint (opt-in) |
| `OXIGRAPH_PORT`    | (see below) | 7878    | Oxigraph SPARQL endpoint (opt-in) |

Neo4j, QLever and Oxigraph ports are only exposed to the host by the `Docker/docker-compose.tools.yml`
overlay, which works with either stack; `make dev-tools` adds it to the dev stack. Oxigraph also needs
`COMPOSE_PROFILES=oxigraph`. These ports are not auto-allocated, so the overlay is best used with one
stack at a time. Override `NEO_BROWSER_PORT` / `NEO_BOLT_PORT` / `QLEVER_PORT` / `OXIGRAPH_PORT` if you
need to run two tools-mode stacks simultaneously.

The MariaDB, (default) Neo4j, QLever and Oxigraph ports are not exposed to the host. Reach
them from inside the stack via `make bash` or `docker compose exec`.

## Backing service addresses

Where the wiki reaches MariaDB and Neo4j, defaulting to this stack's own `db` and `neo` services.

| Variable       | Default | Notes                                                    |
|----------------|---------|----------------------------------------------------------|
| `MARIADB_HOST` | `db`    | Also where `make install-db` installs                    |
| `MARIADB_PORT` | `3306`  |                                                          |
| `NEO4J_SCHEME` | `bolt`  | Plaintext. Any host outside this stack wants `bolt+s` or `neo4j+s` |
| `NEO4J_HOST`   | `neo`   | Both the read and the write URL                          |
| `NEO4J_PORT`   | `7687`  | The port the wiki dials, not the host publish port `NEO_BOLT_PORT` |

Set them in the environment rather than in `Docker/.env`: a value there becomes a makefile
assignment, which a later `MARIADB_HOST=... make dev` cannot override.

An address outside this stack reaches the wiki, `make install-db` and the first-run seed, but not
the rest of the tooling. `make load-neo4j-users` and the perf snapshot targets still act on the
bundled `neo` and `db`, `make remove` still wipes only this stack's volumes, and the `mediawiki`
service still waits on both bundled services being healthy. An external server also has to already
carry the database, user and Neo4j accounts the bundled services create for themselves.

`make reset` is the sharp edge: it wipes the bundled volumes and then reinstalls and reimports the
demo data over whatever `MARIADB_HOST` and `NEO4J_HOST` point at, so against a populated external
server it destroys data rather than being inert.

## Optional services

The dev stack's core is `mediawiki`, `db` and `neo`; everything else is optional. `make dev` (and
`make dev-tools`) accept a `services=` comma-list naming the optional services to run:

| Service       | Needed for                                                              |
|---------------|-------------------------------------------------------------------------|
| `node`        | Frontend work: watches and rebuilds the TS bundle (`dist/`)             |
| `qlever`      | SPARQL/RDF work: the wiki projects RDF into it on every save            |
| `mailcatcher` | Testing outgoing email (web UI on `MAILCATCHER_PORT`)                   |
| `oxigraph`    | Second SPARQL engine; needs manual wiring via `LocalSettings.local.php` |

Without the flag, `node`, `qlever` and `mailcatcher` run by default; `oxigraph` runs only when named.
`services=none` runs core only. Examples:

```sh
make dev                        # default set
make dev services=node          # frontend work: watcher only
make dev services=none          # core only
make dev services=qlever,node   # SPARQL plus frontend
```

Rerunning `make dev` with a different selection reconfigures a running stack: newly-deselected services
stop (their volumes survive for reselection) and the wiki's config follows, so a deselected
store or mail host is never configured. Details:

- Without `node`, `make dev` builds the frontend bundle once in a throwaway container, so the UI
  works; the `ts-*` targets start the watcher on demand. `make ts-build` refreshes a bundle left
  stale by a later `git pull`.
- The test-only backends (`test_neo`, `test_qlever`, `test_oxigraph`) are independent of this selection:
  `make phpunit` starts them on demand, and `make test-backends-stop` stops them again.
- Setting `COMPOSE_PROFILES` directly still works and is unioned with the selection; the profile names
  equal the service names.

## QLever SPARQL store

Both the demo stack and the dev stack run a [QLever](https://github.com/ad-freiburg/qlever)
SPARQL 1.1 graph store by default as a working example of NeoWiki's SPARQL projection plugin
(issue #586). The bundled Makefile passes the store's endpoint to the wiki as `QLEVER_URL`.
`SettingsTemplate.php` points `$wgNeoWikiSparqlStores` at that endpoint, so every page save and
`RebuildGraphDatabases.php` also projects the page's RDF into QLever as named graphs.

Two entries point at that one endpoint: the `native` projection and the `EDM` one defined
by the demo data's `Mapping:EDM` page. Each writes its own per-page named graphs, so one
index holds both and a query can join across them; the demo data's `EDM queries` page shows
such queries.

The `EDM` entry needs that Mapping page, so `make import-demo-data` is part of the setup:
without it every Subject save logs an unknown-projection error and a rebuild reports every
page as failed. On a stack that predates the second entry, run
`make rebuild-graph-databases` to fill the EDM graphs for pages already saved.

The server runs with `--persist-updates`, which is **mandatory**: without it QLever keeps
SPARQL updates only in memory and loses them on restart. With it, updates are written to
`neowiki.update-triples` on the `qlever-index-data` named volume and reloaded on startup,
so the projected data survives `docker compose restart` and `make down` / `make dev`. The
index itself is built empty once (guarded by a sentinel file so a restart never re-indexes
and wipes the updates); NeoWiki fills it at runtime over the SPARQL endpoint, not from a
source file. A named volume (not a bind mount) keeps the index clear of host SELinux
labeling and ownership issues under rootless Podman.

To query it from the host, add the `docker-compose.tools.yml` overlay, which maps `QLEVER_PORT`
(default 7019) — on the dev stack `make dev-tools` does that for you:

```sh
# <project> is this stack's compose project name, which `docker compose ls` lists.
docker compose -p <project> --env-file Docker/.env \
  -f Docker/docker-compose.yml -f Docker/docker-compose.tools.yml up -d

curl http://localhost:7019/ \
  --data-urlencode 'query=SELECT (COUNT(*) AS ?n) WHERE { GRAPH ?g { ?s ?p ?o } }' \
  -H 'Accept: application/sparql-results+json'
```

Without the tools overlay, run the same query from inside the stack, e.g.
`docker compose exec qlever curl ...` or from the `mediawiki` container against
`http://qlever:7019/`. The wiki's own query surfaces reach it either way. Writes require the
`QLEVER_ACCESS_TOKEN` Bearer token; reads do not.

### `test_qlever` (SPARQL query system test)

A second, dedicated QLever store — `test_qlever` — backs the SPARQL query system test
(`QueryQLeverEndToEndTest`), isolated from the `qlever` store the way `test_neo` is
isolated from `neo`: the test writes and deletes Subjects, so it must not touch the wiki's
data. `phpunit.xml.dist` points the test at it via `QLEVER_TEST_URL=http://test_qlever:7019/`
(fixed token `neowiki_test_token`); it is reached in-network only, with no host port. Unlike
the `qlever` store it runs **without** `--persist-updates`: it is ephemeral test scaffolding that
each run clears (`DROP ALL`), so keeping updates in memory is enough. `make phpunit` brings it
up on demand via the `test` profile; in CI it is started explicitly (see
`.github/workflows/ci-php.yml`), because its multi-step bring-up cannot be expressed as a
GitHub Actions service container.

## Oxigraph SPARQL store

Both stacks also carry [Oxigraph](https://github.com/oxigraph/oxigraph), a second SPARQL engine, so
the projection plugin can be exercised against more than one implementation. Nothing points at it by
default, so it sits behind the `oxigraph` compose profile and neither stack starts it on its own:

```sh
COMPOSE_PROFILES=oxigraph make dev
```

`COMPOSE_PROFILES` is not persisted: a later plain `make dev` deselects Oxigraph again and stops it,
though its data volume survives.

`SettingsTemplate.php` points `$wgNeoWikiSparqlStores` at `QLEVER_URL`, so nothing reaches Oxigraph until
you repoint it: `updateUrl` `http://oxigraph:7878/update`, `queryUrl` `http://oxigraph:7878/query`. On the
dev stack put that in `Docker/LocalSettings.local.php`, which the dev overlay bind-mounts; the demo stack
has no such file, so use the commented-out `LocalSettings.php` mount in `docker-compose.yml`. Oxigraph
cannot refuse `SERVICE` requests, so repointing the wiki here enables federation — see
[Restricting federation](../docs/operations/installation.md#restricting-federation).

There is no index to build, unlike QLever. Data lives in the `oxigraph-data` named volume, which
`make remove` clears along with the wiki's own data.

The service runs with `--union-default-graph`, so an unscoped query sees every named graph, matching QLever.

To query it from the host, add the `docker-compose.tools.yml` overlay, which maps `OXIGRAPH_PORT`
(default 7878) — on the dev stack `COMPOSE_PROFILES=oxigraph make dev-tools` does that for you:

```sh
# <project> is this stack's compose project name, which `docker compose ls` lists.
COMPOSE_PROFILES=oxigraph docker compose -p <project> --env-file Docker/.env \
  -f Docker/docker-compose.yml -f Docker/docker-compose.tools.yml up -d

curl http://localhost:7878/query \
  --data-urlencode 'query=SELECT (COUNT(*) AS ?n) WHERE { GRAPH ?g { ?s ?p ?o } }' \
  -H 'Accept: application/sparql-results+json'
```

Oxigraph has no authentication, so the overlay publishes this port on loopback only, unlike the Neo4j
and QLever ones.

### `test_oxigraph` (SPARQL query system test)

The system test runs against both engines, and `test_oxigraph` is its Oxigraph store — isolated from
the `oxigraph` service the way `test_qlever` is from `qlever`. `phpunit.xml.dist` points the test
at it via `OXIGRAPH_TEST_BASE_URL=http://test_oxigraph:7878`; it is reached in-network only, with no
host port. It runs with no `--location`, so the store is in memory: ephemeral scaffolding that each
run clears, like `test_qlever` without `--persist-updates`. It runs without `--union-default-graph`;
adding the flag breaks the test.

`make phpunit` brings it up on demand via the `test` profile; in CI it is started explicitly (see
`.github/workflows/ci-php.yml`).

## The `test` profile

`test_neo`, `test_qlever` and `test_oxigraph` are only for the PHP test suite, so `make dev` does
not start them. `make phpunit` and `make perf` start and seed them on demand; the first run after
the stack comes up waits for Neo4j to boot. `make test-backends` starts them on their own.

Run these from the host. Inside the mediawiki container there is no compose to drive, so they
report the backends as missing instead of starting them.

## Files

- `Dockerfile` — multi-stage build: `production-mw` (MediaWiki + NeoWiki on the
  production `php.ini`; intermediate, no `LocalSettings.php`), `final-mw` (the prebuilt
  demo image published as `ghcr.io/professionalwiki/neowiki:latest`, which bakes in
  `LocalSettings.php`), and `dev-mw` (the dev image with mounted NeoWiki source).
- `docker-compose.yml` — the demo stack's services (`mediawiki`, `db`, `neo`, `qlever`
  — SPARQL store, see above) plus the profile-gated `caddy` (the `server` profile, for
  HTTPS hosting) and `oxigraph` (the `oxigraph` profile, the second SPARQL store, see
  above). The dev overlay builds on this file, so these services are in both stacks.
- `docker-compose.dev.yml` — dev overlay; switches `mediawiki` to the dev image, bind-mounts the
  NeoWiki source, sets `MW_MODE=dev`, adds the dev-only sidecars `node` and `mailcatcher`, and gates
  them plus `qlever` behind their `services=` profiles (see [Optional services](#optional-services));
  `test_neo`, `test_qlever` and `test_oxigraph` sit behind the `test` profile.
- `docker-compose.tools.yml` — opt-in overlay that exposes Neo4j, QLever and Oxigraph to the
  host, usable with either stack.
- `SettingsTemplate.php` — `LocalSettings.php` that branches on `MW_MODE`.
- `.env.dist` — tracked defaults; auto-copied to `.env` on first `make dev`.
- `scripts/set-port.sh` — host port allocator used by `make dev`.
- `fs_overlay/` — files copied into the image at build time (apache config,
  `wait-for-it.sh`, `dev-entrypoint.sh`).
