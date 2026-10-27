# NeoWiki Docker

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
overlay, which works with either stack, for the stores the stack runs; `make dev-tools` adds it to the
dev stack. These ports are not auto-allocated, so the overlay is best used with one stack at a time.
Override `NEO_BROWSER_PORT` / `NEO_BOLT_PORT` / `QLEVER_PORT` / `OXIGRAPH_PORT` if you need to run two
tools-mode stacks simultaneously.

The MariaDB, (default) Neo4j, QLever and Oxigraph ports are not exposed to the host. Reach
them from inside the stack via `make bash` or `docker compose exec`.

## Backing service addresses

Where the wiki reaches MariaDB and Neo4j, defaulting to this stack's own `db` and `neo` services.

| Variable       | Default | Notes                                                    |
|----------------|---------|----------------------------------------------------------|
| `MARIADB_HOST` | `db`    | Also where `make install-db` installs                    |
| `MARIADB_PORT` | `3306`  |                                                          |
| `NEO4J_SCHEME` | `bolt`  | Plaintext. Any host outside this stack wants `bolt+s` or `neo4j+s` |
| `NEO4J_HOST`   | `neo` when that service runs | Both the read and the write URL. Empty leaves Neo4j unconfigured |
| `NEO4J_PORT`   | `7687`  | The port the wiki dials, not the host publish port `NEO_BOLT_PORT` |

Set them in the environment rather than in `Docker/.env`: a value there becomes a makefile
assignment, which a later `MARIADB_HOST=... make dev` cannot override.

An address outside this stack reaches the wiki, `make install-db` and the first-run seed, but not
the rest of the tooling. The perf snapshot targets still act on the bundled `neo` and `db`,
`make remove` still wipes only this stack's volumes, and the `mediawiki` service still waits on the
bundled `db`, and on `neo` when it runs, being healthy. An external Neo4j needs no `neo` service. An
external server also has to already carry the database, user and Neo4j account the bundled services
create for themselves.

`make reset` is the sharp edge: it wipes the bundled volumes and then reinstalls and reimports the
demo data over whatever `MARIADB_HOST` and `NEO4J_HOST` point at, so against a populated external
server it destroys data rather than being inert.

## Optional services

The core of both stacks is `mediawiki` and `db`; everything else is optional. The targets that start a
stack (`make dev`, `make dev-tools`, `make up`, `make demo` and `make upgrade`) accept a `services=`
comma-list naming the optional services to run:

| Service       | Needed for                                                              |
|---------------|-------------------------------------------------------------------------|
| `qlever`      | SPARQL queries: the wiki projects RDF into it on every save             |
| `neo`         | Cypher queries: the wiki projects into Neo4j on every save              |
| `oxigraph`    | A second SPARQL store, kept in sync the same way                        |
| `node`        | Frontend work: watches and rebuilds the TS bundle (`dist/`); dev only   |
| `mailcatcher` | Testing outgoing email (web UI on `MAILCATCHER_PORT`); dev only         |

Without the flag, `qlever`, `node` and `mailcatcher` run. `services=none` runs core only, a wiki
without query surfaces. The selection is not persisted, so pass it whenever you start the stack.
Examples:

```sh
make dev                             # default set
make dev services=node               # frontend work: watcher only
make dev services=none               # core only
make dev services=node,qlever,neo    # SPARQL and Cypher, plus frontend
make upgrade services=qlever,neo     # a demo stack that keeps Neo4j
```

Rerunning `make dev` with a different selection stops newly deselected services, keeping their
volumes, and reconfigures the wiki to match. The demo stack's targets leave deselected services
running, so run `make down` before deselecting a service there. Details:

- Without `node`, `make dev` builds the frontend bundle once in a throwaway container, so the UI
  works; the `ts-*` targets start the watcher on demand. `make ts-build` refreshes a bundle left
  stale by a later `git pull`.
- The test-only backends (`test_neo`, `test_qlever`, `test_oxigraph`) are independent of this selection:
  `make phpunit` starts them on demand, and `make test-backends-stop` stops them again.
- Setting `COMPOSE_PROFILES` directly still works and is unioned with the selection; the profile names
  equal the service names.

## QLever SPARQL store

Both stacks run a [QLever](https://github.com/ad-freiburg/qlever) SPARQL 1.1 graph store by default.
The bundled Makefile passes its endpoint to the wiki as `QLEVER_URL`, and `SettingsTemplate.php`
points `$wgNeoWikiSparqlStores` at it, so every page save and `RebuildGraphDatabases.php` projects the
page's RDF into QLever as named graphs.

Two entries point at that one store: the `native` projection and the `EDM` one defined by the demo
data's `Mapping:EDM` page. Each writes its own per-page named graphs, so a query can join across them;
the demo data's `EDM queries` page shows such queries. The `EDM` entry needs that Mapping page, so
`make import-demo-data` is part of the setup: without it every Subject save logs an unknown-projection
error and a rebuild reports every page as failed.

The projected data lives on the `qlever-index-data` named volume, so it survives
`docker compose restart` and `make down` / `make dev`. If `qlever` does not start, run `make pull`,
then `make dev`. If its log then says the index is too old or in a previous format, run `make down`,
`docker volume rm <project>_qlever-index-data` (`<project>` is the name `make dev` prints),
`make dev` and `make rebuild-graph-databases`.

To query it from the host, add the `docker-compose.tools.yml` overlay, which maps `QLEVER_PORT`
(default 7019) — on the dev stack `make dev-tools` does that for you:

```sh
# <project> is this stack's compose project name, which `docker compose ls` lists.
docker compose -p <project> --env-file Docker/.env \
  -f Docker/docker-compose.yml -f Docker/docker-compose.tools.yml up -d qlever

curl http://localhost:7019/ \
  --data-urlencode 'query=SELECT (COUNT(*) AS ?n) WHERE { GRAPH ?g { ?s ?p ?o } }' \
  -H 'Accept: application/sparql-results+json'
```

Without the tools overlay, run the same query from inside the stack, e.g.
`docker compose exec qlever curl ...` or from the `mediawiki` container against
`http://qlever:7019/`. The wiki's own query surfaces reach it either way. Writes require the
`QLEVER_ACCESS_TOKEN` Bearer token; reads do not.

### `test_qlever` (SPARQL query system test)

This second QLever store is isolated from `qlever` the way `test_neo` is from `neo`, because the
SPARQL query system test (`QueryQLeverEndToEndTest`) writes and deletes Subjects. It has no host
port and runs without `--persist-updates`, since each test run clears it. `make phpunit` brings
it up on demand via the `test` profile; CI starts it in `.github/workflows/ci-php.yml`.

## Oxigraph SPARQL store

`services=oxigraph` adds an [Oxigraph](https://github.com/oxigraph/oxigraph) SPARQL 1.1 graph store.
The bundled Makefile passes its address to the wiki as `OXIGRAPH_URL`, and `SettingsTemplate.php` gives
it the two projections the [QLever store](#qlever-sparql-store) holds, as the stores `oxigraph-native`
and `oxigraph-EDM`. With QLever running too, the query surfaces read QLever.

Data lives in the `oxigraph-data` named volume, which `make remove` clears along with the wiki's
own data.

The service runs with `--union-default-graph`, so an unscoped query sees every named graph. Oxigraph
0.5 returns a triple once per graph holding it, and both projections hold the `rdfs:label` of a
Subject whose Schema is mapped, so use `SELECT DISTINCT`.

The service sits only on the `no-egress` network, which has no route out of the stack and which only
the wiki shares. A federated query fails; see
[Restricting federation](../docs/operations/installation.md#restricting-federation).

To query it from the host, add the `docker-compose.tools.yml` overlay, which maps `OXIGRAPH_PORT`
(default 7878) — on the dev stack `make dev-tools` does that for you:

```sh
# <project> is this stack's compose project name, which `docker compose ls` lists.
COMPOSE_PROFILES=oxigraph docker compose -p <project> --env-file Docker/.env \
  -f Docker/docker-compose.yml -f Docker/docker-compose.tools.yml up -d oxigraph

curl http://localhost:7878/query \
  --data-urlencode 'query=SELECT (COUNT(*) AS ?n) WHERE { GRAPH ?g { ?s ?p ?o } }' \
  -H 'Accept: application/sparql-results+json'
```

Oxigraph has no authentication, so the overlay publishes this port on loopback only, unlike the Neo4j
and QLever ones. The overlay also puts Oxigraph on the default network, where it federates: use the
overlay only on a stack no one else can query.

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

## Demo image tags

`.github/workflows/build-docker.yml` publishes the demo image as `ghcr.io/professionalwiki/neowiki`:

- `master` on every push to master.
- The git tag's name on each version tag: a release, such as `0.1.0`, or a pre-release, such as `0.1.0-rc.1`. A
  release also moves `latest`, which is therefore the most recently published release, not the highest version.

Each holds an amd64 and an arm64 image, which the package also lists on their own, as in `master-arm64` or
`0.1.0-amd64`.

Push version tags one at a time: a push of more than three starts no workflow.

A version tag's commit names its image in `Docker/image-tag`, which `scripts/tag-release.sh` writes, and the workflow
publishes nothing for a tag that file does not name. The bundled Makefile runs the image the file names, and `master` in
a git checkout without it; see [Installation](../docs/operations/installation.md#method-a-docker). To run another
image, put its tag on every `make` command, as in `make upgrade NEOWIKI_IMAGE_TAG=master`. A `docker compose` command
run without the Makefile gets `latest` unless `NEOWIKI_IMAGE_TAG` is set.

## Files

- `Dockerfile` — multi-stage build: `production-mw` (MediaWiki + NeoWiki on the
  production `php.ini`; intermediate, no `LocalSettings.php`), `final-mw` (the prebuilt
  [demo image](#demo-image-tags), which bakes in `LocalSettings.php`), and `dev-mw` (the
  dev image with mounted NeoWiki source).
- `docker-compose.yml` — the demo stack's services: `mediawiki` and `db`, the graph stores
  `oxigraph`, `neo` and `qlever`, each behind the profile of its name (see
  [Optional services](#optional-services)), and `caddy` behind the `server` profile, for HTTPS
  hosting. The dev overlay builds on this file, so these services are in both stacks.
- `docker-compose.dev.yml` — dev overlay; switches `mediawiki` to the dev image, bind-mounts the
  NeoWiki source, sets `MW_MODE=dev`, adds the dev-only sidecars `node` and `mailcatcher`, and gates
  them behind their `services=` profiles (see [Optional services](#optional-services));
  `test_neo`, `test_qlever` and `test_oxigraph` sit behind the `test` profile.
- `docker-compose.tools.yml` — opt-in overlay that exposes Neo4j, QLever and Oxigraph to the
  host, usable with either stack.
- `SettingsTemplate.php` — `LocalSettings.php` that branches on `MW_MODE`.
- `.env.dist` — tracked defaults; auto-copied to `.env` on first `make dev`.
- `scripts/set-port.sh` — host port allocator used by `make dev`.
- `fs_overlay/` — files copied into the image at build time (apache config,
  `wait-for-it.sh`, `dev-entrypoint.sh`).
