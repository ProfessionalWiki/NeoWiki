# NeoWiki

[NeoWiki](https://neowiki.ai) is a collaborative knowledge management system on top of MediaWiki and graph databases.

[![Mastodon](https://img.shields.io/mastodon/follow/116122313808578574)](https://mastodon.social/@NeoWiki)
[![Bluesky](https://img.shields.io/bluesky/followers/NeoWiki.bsky.social)](https://bsky.app/profile/neowiki.bsky.social)
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

This builds a dev-mode image, brings up the stack (mediawiki, db, neo, test_neo, node
watcher, mailcatcher), runs first-time install and seed, and waits until the wiki is
reachable. It prints the URL when ready (the default is `http://localhost:8484` but
the actual port is auto-allocated; see [Reserved ports](Docker/README.md#reserved-host-ports)).

Mailcatcher web UI is at the port `make dev` printed (default `8025`,
configurable via `MAILCATCHER_PORT` in `Docker/.env`).

The `node` sidecar runs `npm run build:watch`, so TypeScript changes under
`resources/ext.neowiki/` rebuild automatically.

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
```

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

`perf-generate` takes `pages=N` plus optional `subjects=10` and `seed=1`. It emits one Schema
and `pages` × `subjects` Subjects carrying 12 Statements each, three of them relations to
Subjects on other pages. The same options always produce the same dump, and no two seeds mint
the same Subject id, so dumps from different seeds can share one wiki.

`perf-import` is the benchmark: its last line reports elapsed time, pages/sec and
Subjects/sec. Snapshot and restore make the result reusable — `perf/` is gitignored, and every
worktree stack uses the same wiki id, so a snapshot restores into any of them.

Three things to know about a restored wiki:

- It has the MediaWiki schema the snapshot was taken with, so run `make update-dot-php` after
  restoring.
- It has the snapshot's Neo4j index state, and a stack that has never run
  `make rebuild-graph-databases` has no `Subject(id)` index. Rebuild before snapshotting, or
  every measured write pays a full-graph scan.
- It has no QLever data: that index is filled at runtime over SPARQL, not snapshotted. Run
  `make rebuild-graph-databases` when the SPARQL store has to match.

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
$wgDebugLogGroups['neowiki'] = '/tmp/neowiki-debug.log';
```

### Try-it-out and server deployment

For the prebuilt try-it-out stack or server deployment with Caddy, see
[Installation](docs/operations/installation.md).
