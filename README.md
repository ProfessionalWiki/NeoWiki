# NeoWiki

[NeoWiki](https://professional.wiki/en/neowiki) is a collaborative knowledge management system on top of MediaWiki and graph databases.

[![Mastodon](https://img.shields.io/mastodon/follow/116122313808578574)](https://mastodon.social/@NeoWiki)
[![Bluesky](https://img.shields.io/bluesky/followers/NeoWiki.bsky.social)](https://bsky.app/profile/neowiki.bsky.social)
[![X](https://img.shields.io/twitter/follow/NeoWikiAI)](https://x.com/NeoWikiAI)

## Technical Documentation

See [docs/](./docs/), especially [docs/Glossary.md](./docs/Glossary.md).

## Development

To work on NeoWiki (edit code, run tests, see changes live), bring up the bundled dev stack:

```bash
make dev
```

This builds a dev-mode image, brings up the stack (mediawiki, db, neo, test_neo, node
watcher, mailcatcher), runs first-time install and seed, and waits until the wiki is
reachable. It prints the URL when ready.

Mailcatcher web UI: `http://localhost:1080` (configurable via `MAILCATCHER_PORT` in
`Docker/.env`).

The `node` sidecar runs `npm run build:watch`, so TypeScript changes under
`resources/ext.neowiki/` rebuild automatically.

### Running tests and tools

```bash
make phpunit              # full PHPUnit suite
make phpunit filter=Foo   # single test class
make cs                   # phpcs + phpstan
make tsci                 # vitest + build + lint
make bash                 # shell into the mediawiki container
make logs                 # tail logs
make reset                # wipe DB + Neo and reseed demo data
```

For all targets, run `make help`.

### Per-worktree dev environments

Each clone or worktree is a self-contained stack. Run `make dev` from any NeoWiki
checkout and it will allocate its own port and project namespace, so multiple worktrees
can run side by side without collision.

To override the port: `make dev port=8488` or `MW_SERVER_PORT=8488 make dev`.

### Customizing dev config

Create `Docker/LocalSettings.local.php` (gitignored) for per-worktree overrides. Common
uses:

- Loading additional MediaWiki extensions for an integration test
- Custom debug toggles
- Hook overrides for a specific feature branch

Example:

```php
<?php
wfLoadExtension( 'BlueSpiceFoundation' );
$wgDebugLogGroups['neowiki'] = '/tmp/neowiki-debug.log';
```

### Try-it-out and server deployment

For a prebuilt try-it-out stack or server deployment with Caddy, see
[`Docker/README.md`](./Docker/README.md).
