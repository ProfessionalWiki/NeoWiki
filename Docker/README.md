# NeoWiki Docker

NeoWiki is in the experimental proof of concept phase. It is not production ready, public interfaces will change,
big structural changes will happen, and key functionality is still missing.

This directory contains files for the Dockerized development environment and for the pre-built demo Docker image.

## Prerequisites

- Docker and Docker Compose

## Develop NeoWiki

For the developer workflow (`make dev`, `make phpunit`, etc.), see
[`../README.md`](../README.md). All commands run from the extension root, not from
`Docker/`.

## Try it out (prebuilt image)

Run NeoWiki against the published demo image without building locally. From the
extension root:

```bash
cd ..              # extension root
make up            # docker compose up -d, prebuilt ghcr image
make install-db
make load-neo4j-users
make import-demo-data
```

Then open http://localhost:8484 and log in as `AdminName` (default password
`AdminPassword`, configurable in `Docker/.env`).

## Server deployment

To deploy on a server with automatic HTTPS via Caddy:

1. Copy `Docker/.env.dist` to `Docker/.env` and change all values marked with
   `# Change for production`, including `MW_SERVER` (e.g. `https://wiki.example.com`).

2. Start all services including Caddy:
   ```bash
   docker compose --profile server up -d
   ```

3. Run the install/load steps from the try-it-out section above.

4. Access your wiki at the configured `MW_SERVER` URL.

## Reserved host ports

`make dev` allocates host ports from these ranges. Auto-allocation skips ports that
are already bound on the host.

| Variable           | Range       | Default | Service                    |
|--------------------|-------------|---------|----------------------------|
| `MW_SERVER_PORT`   | 8484-8499   | 8484    | MediaWiki HTTP             |
| `MAILCATCHER_PORT` | 8025-8040   | 8025    | Mailcatcher web UI         |
| `NEO_BROWSER_PORT` | (see below) | 7474    | Neo4j Browser (opt-in)     |
| `NEO_BOLT_PORT`    | (see below) | 7687    | Neo4j Bolt endpoint (opt-in) |

Neo4j ports are only exposed to the host when you run `make dev-tools` (which adds
the `Docker/docker-compose.tools.yml` overlay). They use the conventional defaults
(7474, 7687) and are not auto-allocated, so `make dev-tools` is best used with one
worktree at a time. Override `NEO_BROWSER_PORT` / `NEO_BOLT_PORT` if you need to run
two tools-mode worktrees simultaneously.

The MariaDB and (default) Neo4j ports are not exposed to the host. Reach them from
inside the stack via `make bash` or `docker compose exec`.

## Files

- `Dockerfile` — multi-stage build with `production-mw` (prebuilt demo), `final-mw`
  (release tag), and `dev-mw` (the dev image with mounted NeoWiki source).
- `docker-compose.yml` — base services (`mediawiki`, `db`, `neo`, plus profile-gated
  `test_neo`, `node`, `mailcatcher`, `caddy`).
- `docker-compose.dev.yml` — dev overlay; switches `mediawiki` to the dev image,
  bind-mounts the NeoWiki source, and sets `MW_MODE=dev`.
- `docker-compose.tools.yml` — opt-in overlay that exposes Neo4j to the host.
- `SettingsTemplate.php` — `LocalSettings.php` that branches on `MW_MODE`.
- `.env.dist` — tracked defaults; auto-copied to `.env` on first `make dev`.
- `scripts/set-port.sh` — host port allocator used by `make dev`.
- `fs_overlay/` — files copied into the image at build time (apache config,
  `wait-for-it.sh`, `dev-entrypoint.sh`).
