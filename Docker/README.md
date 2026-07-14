# NeoWiki Docker

NeoWiki is in the experimental proof of concept phase. It is not production ready, public interfaces will change,
big structural changes will happen, and key functionality is still missing.

This directory contains files for the pre-built demo Docker image and its compose stack.
The development environment is separate and runs on ddev — see [`../.ddev/README.md`](../.ddev/README.md).

## Installing and running

This file is a reference for the `Docker/` directory itself. For instructions:

- Demo / try-it-out stack and server (Caddy) deployment: see
  [Installation](../docs/operations/installation.md). The wiki serves at
  `http://localhost:8484` by default (`MW_SERVER_PORT` in `.env`).
- Developer workflow (`ddev start`, `make phpunit`, etc.): see [`../README.md`](../README.md).
  All commands run from the extension root, not from `Docker/`.

## Files

- `Dockerfile` — multi-stage build: `production-mw` (MediaWiki + NeoWiki on the
  production `php.ini`; intermediate, no `LocalSettings.php`) and `final-mw` (the prebuilt
  demo image published as `ghcr.io/professionalwiki/neowiki:latest`, which bakes in
  `LocalSettings.php`).
- `docker-compose.yml` — the "try-it-out" services (`mediawiki`, `db`, `neo`) plus
  the profile-gated `caddy` (the `server` profile, for HTTPS hosting).
- `docker-compose.ci.yml` — variant used by the image-build CI workflow.
- `SettingsTemplate.php` — the `LocalSettings.php` baked into the demo image.
- `.env.dist` — tracked defaults; auto-copied to `.env` on first `make` run.
- `scripts/preflight.sh` — fail-fast Docker runtime checks for `make up`/`demo` (`make doctor`).
- `fs_overlay/` — files copied into the image at build time (apache config,
  `wait-for-it.sh`).
- `mediawiki/` — gitignored; the MediaWiki core checkout. The dev environment's
  pre-start hook clones it, and the image-build CI checks it out fresh.
