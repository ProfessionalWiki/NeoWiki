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

Neo4j and QLever ports are only exposed to the host when you run `make dev-tools` (which
adds the `Docker/docker-compose.tools.yml` overlay). They use conventional defaults
(7474, 7687, 7019) and are not auto-allocated, so `make dev-tools` is best used with one
worktree at a time. Override `NEO_BROWSER_PORT` / `NEO_BOLT_PORT` / `QLEVER_PORT` if you
need to run two tools-mode worktrees simultaneously.

The MariaDB, (default) Neo4j and QLever ports are not exposed to the host. Reach them
from inside the stack via `make bash` or `docker compose exec`.

## QLever SPARQL store (dev)

The dev stack bundles a [QLever](https://github.com/ad-freiburg/qlever) SPARQL 1.1 graph
store as a working example of NeoWiki's SPARQL projection plugin (issue #586). It is a
dev-only sidecar (in `docker-compose.dev.yml`, like `test_neo`); the base "try-it-out" /
demo stack does not run it. `SettingsTemplate.php` points `$wgNeoWikiSparqlStores` at it
(`http://qlever:7019/`, `native` projection) only in dev mode, so every page save and
`RebuildGraphDatabases.php` also projects the page's RDF into QLever as a named graph.

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

## Files

- `Dockerfile` — multi-stage build: `production-mw` (MediaWiki + NeoWiki on the
  production `php.ini`; intermediate, no `LocalSettings.php`), `final-mw` (the prebuilt
  demo image published as `ghcr.io/professionalwiki/neowiki:latest`, which bakes in
  `LocalSettings.php`), and `dev-mw` (the dev image with mounted NeoWiki source).
- `docker-compose.yml` — base "try-it-out" services (`mediawiki`, `db`, `neo`) plus
  the profile-gated `caddy` (the `server` profile, for HTTPS hosting).
- `docker-compose.dev.yml` — dev overlay; switches `mediawiki` to the dev image,
  bind-mounts the NeoWiki source, sets `MW_MODE=dev`, and adds the dev-only sidecars
  `test_neo`, `qlever` (SPARQL store, see above), `node`, and `mailcatcher`.
- `docker-compose.tools.yml` — opt-in overlay that exposes Neo4j and QLever to the host.
- `SettingsTemplate.php` — `LocalSettings.php` that branches on `MW_MODE`.
- `.env.dist` — tracked defaults; auto-copied to `.env` on first `make dev`.
- `scripts/set-port.sh` — host port allocator used by `make dev`.
- `fs_overlay/` — files copied into the image at build time (apache config,
  `wait-for-it.sh`, `dev-entrypoint.sh`).
