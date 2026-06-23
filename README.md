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

See [docs/](./docs/), especially [docs/concepts/glossary.md](./docs/concepts/glossary.md).

## Development

Prerequisites:

- Docker and Docker Compose
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
