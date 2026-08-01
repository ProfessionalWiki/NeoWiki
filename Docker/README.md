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
are already bound on the host.

| Variable           | Range       | Default | Service                    |
|--------------------|-------------|---------|----------------------------|
| `MW_SERVER_PORT`   | 8484-8499   | 8484    | MediaWiki HTTP             |
| `MAILCATCHER_PORT` | 8025-8040   | 8025    | Mailcatcher web UI         |
| `NEO_BROWSER_PORT` | (see below) | 7474    | Neo4j Browser (opt-in)     |
| `NEO_BOLT_PORT`    | (see below) | 7687    | Neo4j Bolt endpoint (opt-in) |
| `QLEVER_PORT`      | (see below) | 7019    | QLever SPARQL endpoint (opt-in) |
| `OXIGRAPH_PORT`    | (see below) | 7878    | Oxigraph SPARQL endpoint (opt-in) |

Neo4j, QLever and Oxigraph ports are only exposed to the host when you run `make dev-tools`
(which adds the `Docker/docker-compose.tools.yml` overlay). They use the services' conventional
ports and are not auto-allocated, so `make dev-tools` is best used with one worktree at a time.
Override `NEO_BROWSER_PORT` / `NEO_BOLT_PORT` / `QLEVER_PORT` / `OXIGRAPH_PORT` if you need to
run two tools-mode worktrees simultaneously.

The MariaDB, (default) Neo4j, QLever and Oxigraph ports are not exposed to the host. Reach
them from inside the stack via `make bash` or `docker compose exec`.

## QLever SPARQL store (dev)

The dev stack bundles a [QLever](https://github.com/ad-freiburg/qlever) SPARQL 1.1 graph
store as a working example of NeoWiki's SPARQL projection plugin (issue #586). It is a
dev-only sidecar (in `docker-compose.dev.yml`, like `test_neo`); the base "try-it-out" /
demo stack does not run it. `SettingsTemplate.php` points `$wgNeoWikiSparqlStores` at it
(`http://qlever:7019/`) only in dev mode, so every page save and `RebuildGraphDatabases.php`
also projects the page's RDF into QLever as named graphs.

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

To query it from the host, expose the endpoint with `make dev-tools` (or add the
`docker-compose.tools.yml` overlay), which maps `QLEVER_PORT` (default 7019):

```sh
curl http://localhost:7019/ \
  --data-urlencode 'query=SELECT (COUNT(*) AS ?n) WHERE { GRAPH ?g { ?s ?p ?o } }' \
  -H 'Accept: application/sparql-results+json'
```

Without the tools overlay, run the same query from inside the stack, e.g.
`docker compose exec qlever curl ...` or from the `mediawiki` container against
`http://qlever:7019/`. Writes require the `QLEVER_ACCESS_TOKEN` Bearer token; reads do not.

### `test_qlever` (SPARQL query system test)

A second, dedicated QLever store — `test_qlever` — backs the SPARQL query system test
(`QueryQLeverEndToEndTest`), isolated from the demo `qlever` store the way `test_neo` is
isolated from `neo`: the test writes and deletes Subjects, so it must not touch the demo
data. `phpunit.xml.dist` points the test at it via `QLEVER_TEST_URL=http://test_qlever:7019/`
(fixed token `neowiki_test_token`); it is reached in-network only, with no host port. Unlike
the demo store it runs **without** `--persist-updates`: it is ephemeral test scaffolding that
each run clears (`DROP ALL`), so keeping updates in memory is enough. `make phpunit` brings it
up as part of the dev stack; in CI it is started explicitly (see `.github/workflows/ci-php.yml`),
because its multi-step bring-up cannot be expressed as a GitHub Actions service container.

## Oxigraph SPARQL store (dev)

The dev stack also bundles [Oxigraph](https://github.com/oxigraph/oxigraph), a second SPARQL engine,
so the projection plugin can be exercised against more than one implementation. Unlike QLever it is
not wired up by default: `SettingsTemplate.php` points the wiki at QLever, so nothing writes here
until you repoint `$wgNeoWikiSparqlStores` at it, with `updateUrl` `http://oxigraph:7878/update` and
`queryUrl` `http://oxigraph:7878/query`.

It needs none of QLever's bring-up: no index to build, and `--location` is the whole of persistence —
given one, the store is durable with no further flag. Data lives in the `oxigraph-data` named volume,
which no target empties on its own:

```sh
make down
docker volume rm <project>_oxigraph-data   # <project> is the name `make dev` prints
make dev
```

The service runs with `--union-default-graph`, which makes an unscoped query see every named graph, as
QLever does unconditionally. Without it Oxigraph follows SPARQL 1.1, where an unscoped query sees only
the default graph — always empty here, since NeoWiki writes pages into named graphs.

Expose it on the host with `make dev-tools` (or the `docker-compose.tools.yml` overlay), which maps
`OXIGRAPH_PORT` (default 7878):

```sh
curl http://localhost:7878/query \
  --data-urlencode 'query=SELECT (COUNT(*) AS ?n) WHERE { GRAPH ?g { ?s ?p ?o } }' \
  -H 'Accept: application/sparql-results+json'
```

Oxigraph has no authentication, so neither reads nor writes take a token — and `make dev-tools`
publishes the port on every interface.

### `test_oxigraph` (SPARQL query system test)

The system test runs against both engines, and `test_oxigraph` is its Oxigraph store — isolated from
the demo `oxigraph` service the way `test_qlever` is from `qlever`. `phpunit.xml.dist` points the test
at it via `OXIGRAPH_TEST_URL=http://test_oxigraph:7878`, a base URL the test derives both paths from;
it is reached in-network only, with no host port. It runs with no `--location`, so the store is in
memory: ephemeral scaffolding each run clears, like `test_qlever` without `--persist-updates`.

It deliberately runs **without** `--union-default-graph`: with `test_qlever` always unioning, the pair
proves NeoWiki's queries work under either semantics, not just the forgiving one. The test asserts
that difference, so losing it fails rather than passing quietly. `make phpunit` brings it up as part
of the dev stack; in CI it is started explicitly (see `.github/workflows/ci-php.yml`).

## Files

- `Dockerfile` — multi-stage build: `production-mw` (MediaWiki + NeoWiki on the
  production `php.ini`; intermediate, no `LocalSettings.php`), `final-mw` (the prebuilt
  demo image published as `ghcr.io/professionalwiki/neowiki:latest`, which bakes in
  `LocalSettings.php`), and `dev-mw` (the dev image with mounted NeoWiki source).
- `docker-compose.yml` — base "try-it-out" services (`mediawiki`, `db`, `neo`) plus
  the profile-gated `caddy` (the `server` profile, for HTTPS hosting).
- `docker-compose.dev.yml` — dev overlay; switches `mediawiki` to the dev image,
  bind-mounts the NeoWiki source, sets `MW_MODE=dev`, and adds the dev-only sidecars
  `test_neo`, `qlever` and `oxigraph` (SPARQL stores, see above), `node`, and `mailcatcher`.
- `docker-compose.tools.yml` — opt-in overlay that exposes Neo4j, QLever and Oxigraph to the host.
- `SettingsTemplate.php` — `LocalSettings.php` that branches on `MW_MODE`.
- `.env.dist` — tracked defaults; auto-copied to `.env` on first `make dev`.
- `scripts/set-port.sh` — host port allocator used by `make dev`.
- `fs_overlay/` — files copied into the image at build time (apache config,
  `wait-for-it.sh`, `dev-entrypoint.sh`).
