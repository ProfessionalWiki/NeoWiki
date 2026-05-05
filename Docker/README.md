# NeoWiki Docker

Try NeoWiki locally or deploy it to a server.

NeoWiki is in the experimental proof of concept phase. It is not production ready, public interfaces will change,
big structural changes will happen, and key functionality is still missing.

## Prerequisites

- Docker and Docker Compose

## Local setup

1. Start the containers:
   ```bash
   docker compose up -d
   ```

2. Wait for all containers to be healthy:
   ```bash
   docker compose ps
   ```

3. Initialize the database and Neo4j:
   ```bash
   make install-db
   make load-neo4j-users
   ```

4. Load demo data:
   ```bash
   make import-demo-data
   ```

5. Open http://localhost:8484

To log in, use username `AdminName` and the password from `.env` (`AdminPassword` by default).

## Server deployment

To deploy on a server with automatic HTTPS via Caddy:

1. Edit `.env` and change all values marked with `# Change for production`, including
   `MW_SERVER` (e.g., `https://wiki.example.com`)

2. Start all services including Caddy:
   ```bash
   docker compose --profile server up -d
   ```

3. Follow steps 2-4 from the local setup above

4. Access your wiki at your configured `MW_SERVER` URL

## Develop NeoWiki

To work on NeoWiki itself (edit code, run tests, see changes live):

```bash
cd Docker
make dev
```

This builds a dev-mode image, brings up the dev stack (mediawiki, db, neo, test_neo,
node watcher, mailcatcher), runs first-time install/seed, and waits until the wiki is
reachable. It will print the URL when ready.

Mailcatcher web UI: `http://localhost:1080` (configurable via `MAILCATCHER_PORT`).

The `node` sidecar runs `npm run build:watch` continuously, so TypeScript changes
under `resources/ext.neowiki/` rebuild automatically. No separate watcher is needed.

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

### Per-worktree dev environments

Each clone or worktree of NeoWiki is a self-contained stack. Run `make dev` from any
NeoWiki checkout's `Docker/` directory and it will allocate its own port and project
namespace. Multiple worktrees can run side by side without collision.

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
