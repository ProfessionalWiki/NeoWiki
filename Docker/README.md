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

Neo4j ports are only exposed to the host when you run `make dev-tools` (which adds
the `Docker/docker-compose.tools.yml` overlay). They use the conventional defaults
(7474, 7687) and are not auto-allocated, so `make dev-tools` is best used with one
worktree at a time. Override `NEO_BROWSER_PORT` / `NEO_BOLT_PORT` if you need to run
two tools-mode worktrees simultaneously.

The MariaDB and (default) Neo4j ports are not exposed to the host. Reach them from
inside the stack via `make bash` or `docker compose exec`.

## Files

- `Dockerfile` — multi-stage build: `production-mw` (MediaWiki + NeoWiki on the
  production `php.ini`; intermediate, no `LocalSettings.php`), `final-mw` (the prebuilt
  demo image published as `ghcr.io/professionalwiki/neowiki:latest`, which bakes in
  `LocalSettings.php`), and `dev-mw` (the dev image with mounted NeoWiki source).
- `docker-compose.yml` — base "try-it-out" services (`mediawiki`, `db`, `neo`) plus
  the profile-gated `caddy` (the `server` profile, for HTTPS hosting).
- `docker-compose.dev.yml` — dev overlay; switches `mediawiki` to the dev image,
  bind-mounts the NeoWiki source, sets `MW_MODE=dev`, and adds the dev-only sidecars
  `test_neo`, `node`, and `mailcatcher`.
- `docker-compose.tools.yml` — opt-in overlay that exposes Neo4j to the host.
- `SettingsTemplate.php` — `LocalSettings.php` that branches on `MW_MODE`.
- `.env.dist` — tracked defaults; auto-copied to `.env` on first `make dev`.
- `scripts/set-port.sh` — host port allocator used by `make dev`.
- `fs_overlay/` — files copied into the image at build time (apache config,
  `wait-for-it.sh`, `dev-entrypoint.sh`).
