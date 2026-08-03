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

Neo4j and QLever ports are only exposed to the host by the `Docker/docker-compose.tools.yml`
overlay, which works with either stack and which `make dev-tools` adds to the dev stack. They use
conventional defaults (7474, 7687, 7019) and are not auto-allocated, so the overlay is best used
with one stack at a time. Override `NEO_BROWSER_PORT` / `NEO_BOLT_PORT` / `QLEVER_PORT` if you
need to run two tools-mode stacks simultaneously.

The MariaDB, (default) Neo4j and QLever ports are not exposed to the host. Reach them
from inside the stack via `make bash` or `docker compose exec`.

## QLever SPARQL store

Both the demo stack and the dev stack bundle a [QLever](https://github.com/ad-freiburg/qlever)
SPARQL 1.1 graph store as a working example of NeoWiki's SPARQL projection plugin (issue #586). The
service is defined in `docker-compose.yml`, which both stacks build on, and which passes the wiki
the store's endpoint as `QLEVER_URL`. `SettingsTemplate.php` points `$wgNeoWikiSparqlStores` at that
endpoint, so every page save and `RebuildGraphDatabases.php` also projects the page's RDF into
QLever as named graphs.

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
(`QuerySparqlEndToEndTest`), isolated from the `qlever` store the way `test_neo` is
isolated from `neo`: the test writes and deletes Subjects, so it must not touch the wiki's
data. `phpunit.xml.dist` points the test at it via `QLEVER_TEST_URL=http://test_qlever:7019/`
(fixed token `neowiki_test_token`); it is reached in-network only, with no host port. Unlike
the `qlever` store it runs **without** `--persist-updates`: it is ephemeral test scaffolding that
each run clears (`DROP ALL`), so keeping updates in memory is enough. `make phpunit` brings it
up on demand via the `test` profile; in CI it is started explicitly (see
`.github/workflows/ci-php.yml`), because its multi-step bring-up cannot be expressed as a
GitHub Actions service container.

## The `test` profile

`test_neo` and `test_qlever` are only for the PHP test suite, so `make dev` does not start
them. `make phpunit` and `make perf` start and seed them on demand; the first run after the
stack comes up waits for Neo4j to boot. `make test-backends` starts them on their own.

Run these from the host. Inside the mediawiki container there is no compose to drive, so they
report the backends as missing instead of starting them.

## Files

- `Dockerfile` — multi-stage build: `production-mw` (MediaWiki + NeoWiki on the
  production `php.ini`; intermediate, no `LocalSettings.php`), `final-mw` (the prebuilt
  demo image published as `ghcr.io/professionalwiki/neowiki:latest`, which bakes in
  `LocalSettings.php`), and `dev-mw` (the dev image with mounted NeoWiki source).
- `docker-compose.yml` — the demo stack's services (`mediawiki`, `db`, `neo`, `qlever`
  — SPARQL store, see above) plus the profile-gated `caddy` (the `server` profile, for
  HTTPS hosting). The dev overlay builds on this file, so its services are in both stacks.
- `docker-compose.dev.yml` — dev overlay; switches `mediawiki` to the dev image,
  bind-mounts the NeoWiki source, sets `MW_MODE=dev`, and adds the dev-only sidecars
  `node` and `mailcatcher`, plus `test_neo` and `test_qlever` behind the `test` profile.
- `docker-compose.tools.yml` — opt-in overlay that exposes Neo4j and QLever to the host,
  usable with either stack.
- `SettingsTemplate.php` — `LocalSettings.php` that branches on `MW_MODE`.
- `.env.dist` — tracked defaults; auto-copied to `.env` on first `make dev`.
- `scripts/set-port.sh` — host port allocator used by `make dev`.
- `fs_overlay/` — files copied into the image at build time (apache config,
  `wait-for-it.sh`, `dev-entrypoint.sh`).
