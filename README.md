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

- Docker
- [ddev](https://docs.ddev.com/en/stable/users/install/ddev-installation/)
- GNU Make

To work on NeoWiki (edit code, run tests, see changes live), bring up the dev environment:

```bash
ddev start
```

The first start on a fresh clone does everything: it clones MediaWiki core, installs the
composer dependencies, installs the wiki, and seeds the demo data (expect a few minutes;
later starts are fast). The wiki serves at `https://neowiki.ddev.site` — no port to pick,
no image to build. Log in as `AdminName` with the password `AdminPassword`.

Mail is caught by ddev's built-in Mailpit (`ddev launch -m` opens its UI). The `node`
service runs `npm run build:watch`, so TypeScript changes under `resources/ext.neowiki/`
rebuild automatically. Neo4j Browser is at `https://neo4j-neowiki.ddev.site`.

See [.ddev/README.md](.ddev/README.md) for the service URLs, credentials, and how the
pieces fit together.

### Running tests and tools

```bash
make phpunit              # full PHPUnit suite
make phpunit filter=Foo   # single test class
make cs                   # phpcs + phpstan
make tsci                 # vitest + build + lint
make bash                 # shell into the web container
make logs                 # tail logs
make reset                # wipe DB + Neo and reseed demo data
make import-demo-data     # load the latest demo data, overriding your changes
```

For all targets, run `make help`.

### Per-worktree dev environments

Each clone or worktree is a self-contained environment with its own hostname, derived
from the directory name: a worktree `NeoWiki-feature-x` serves at
`https://neowiki-feature-x.ddev.site`. Run `ddev start` from any checkout; there is no
port coordination between parallel environments.

### Customizing dev config

Create `.ddev/mw/LocalSettings.local.php` (gitignored) for per-checkout overrides. Common
uses:

- Loading additional MediaWiki extensions for an integration test (clone them into
  `Docker/mediawiki/extensions/`)
- Custom debug toggles
- Hook overrides for a specific feature branch

Example:

```php
<?php
wfLoadExtension( 'SomeExtension' );
$wgDebugLogGroups['neowiki'] = '/tmp/neowiki-debug.log';
```

### Try-it-out and server deployment

For the prebuilt try-it-out stack or server deployment with Caddy (docker compose, no
ddev), see [Installation](docs/operations/installation.md).
