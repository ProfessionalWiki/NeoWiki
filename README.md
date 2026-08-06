# NeoWiki

[NeoWiki](https://neowiki.ai) is a collaborative knowledge management system on top of MediaWiki and graph databases.

[![Mastodon](https://img.shields.io/mastodon/follow/116122313808578574)](https://mastodon.social/@NeoWiki)
[![Bluesky](https://img.shields.io/bluesky/followers/neowiki.ai)](https://bsky.app/profile/neowiki.ai)
[![X](https://img.shields.io/twitter/follow/NeoWikiAI)](https://x.com/NeoWikiAI)

## Installation

NeoWiki is not production ready yet. We support two installation use cases:

* A **demo environment** for evaluation and experimentation. See [installation.md](docs/operations/installation.md).
* A **development environment** with configuration and tools for enhancing NeoWiki. See the "Development" section below.

## Technical Documentation

See [docs/](./docs/), especially [docs/glossary.md](./docs/glossary.md).

## Development

Prerequisites:

- Docker (or a compatible runtime such as Podman)
- Docker Compose v2+ (with the `docker compose` subcommand, not the legacy standalone `docker-compose` v1. Verify with `docker compose version`)
- GNU Make

To work on NeoWiki (edit code, run tests, see changes live), bring up the bundled dev stack:

```bash
make dev
```

This builds a dev-mode image, brings up the stack (mediawiki, db, neo, qlever, node
watcher, mailcatcher), runs first-time install and seed, and waits until the wiki is
reachable. It prints the URL when ready (the default is `http://localhost:8484` but
the actual port is auto-allocated; see [Reserved ports](Docker/README.md#reserved-host-ports)).
To run a smaller or different set of these optional services, see
[Optional services](Docker/README.md#optional-services).

Mailcatcher web UI is at the port `make dev` printed (default `8025`,
configurable via `MAILCATCHER_PORT` in `Docker/.env`).

The `node` sidecar runs `npm run build:watch`, so TypeScript changes under
`resources/ext.neowiki/` rebuild automatically. The watcher transpiles but does not
type-check — for that, rely on your IDE while editing, and on `make ts-build` (or
`make tsci`) and CI before pushing. `npm run tsc:watch` runs a standalone type-check
watcher if you want one.

To also expose Neo4j Browser and the Bolt endpoint to the host (single-worktree use),
use `make dev-tools` instead. URLs print when the stack comes up.

### Running tests and tools

```bash
make phpunit              # full PHPUnit suite
make phpunit filter=Foo   # single test class
make cs                   # phpcs + phpstan
make tsci                 # vitest + build + lint
make bash                 # shell into the mediawiki container
make logs                 # tail logs
make reset                # wipe DB + Neo and reseed demo data
make import-demo-data     # load the latest demo data, overriding your changes
make test-backends-stop   # stop the test-only backends (make phpunit restarts them on demand)
```

The first `make phpunit` after `make dev` starts the test-only backends, so it is slower than
later runs.

For all targets, run `make help`.

### Performance test data

Build a large synthetic wiki to measure against the targets in
[ADR 29](docs/adr/029-scalability-targets.md):

```bash
make perf-generate pages=1000   # write perf/dump.xml
make perf-import                # import it, timing the write path
make perf-snapshot              # save MariaDB + Neo4j to perf/snapshot/
make perf-restore               # load perf/snapshot/ over this stack's data
```

`perf-generate` also takes `subjects` (default 10) and `seed` (default 1). It emits one Schema and
`pages` × `subjects` Subjects carrying 12 Statements each, three of them relations to Subjects on
other pages. The same options always produce the same dump, and no two seeds produce the same
Subject page titles or Subject ids, so dumps from different seeds can share one wiki. Pages are
written as a save would store them, so budget about 27 KB of dump per page.

`perf-import` runs with link table updates off, so `Special:Search` and WhatLinksHere will not find
the imported pages. Everything NeoWiki reads is projected during the import, so the wiki it produces
is fit for measuring reads as well; the number the last line reports is a write-path cost.
`make reset` returns the stack to demo data.

Snapshot a wiki nothing else is writing to — the MariaDB and Neo4j halves are not captured at the
same instant. A snapshot restores into any worktree stack, since they all use the same database
name. After `perf-restore`:

- Run `make update-dot-php`: the MediaWiki schema is the snapshot's, not this checkout's.
- QLever is not part of the snapshot, and `make rebuild-graph-databases` only adds to it, so a
  SPARQL store that has to match the restored wiki must start empty. `make remove` clears it
  along with the wiki's own data; to empty only QLever, drop its volume while the stack is down:

  ```bash
  make down                                     # also removes the qlever container
  docker volume rm <project>_qlever-index-data  # <project> is the name `make dev` prints
  make dev
  make perf-restore
  make rebuild-graph-databases
  ```

### Per-worktree dev environments

Each clone or worktree is a self-contained stack. Run `make dev` from any NeoWiki
checkout and it will allocate its own port and project namespace, so multiple worktrees
can run side by side without collision. See [Reserved host ports](Docker/README.md#reserved-host-ports)
for the auto-allocation ranges.

To override the MediaWiki port: `make dev port=8488` or `MW_SERVER_PORT=8488 make dev`.

### Customizing dev config

Create `Docker/LocalSettings.local.php` (gitignored) for per-worktree overrides. Common
uses:

- Loading additional MediaWiki extensions for an integration test
- Custom debug toggles
- Hook overrides for a specific feature branch

Example:

```php
<?php
wfLoadExtension( 'SomeExtension' );
$wgDebugLogGroups['NeoWiki'] = '/tmp/neowiki-debug.log';
```

### Try-it-out and server deployment

For the prebuilt try-it-out stack or server deployment with Caddy, see
[Installation](docs/operations/installation.md).
