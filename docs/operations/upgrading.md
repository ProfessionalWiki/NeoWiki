---
title: Upgrading
order: 2
---

# Upgrading NeoWiki

NeoWiki has no releases or version numbers yet, so upgrading means moving to the latest development state.

## Method A: Docker

Update the copy of the repository the stack runs from: `git pull` in it, or re-download it if you took an archive.
Keep it at the same path, or `make` targets a different, empty stack and your `Docker/.env` settings are lost. Then,
from the repository root:

```sh
make upgrade
```

This pulls the latest demo image, restarts the stack on it, runs MediaWiki's updater, and
[rebuilds](maintenance.md#rebuilding-the-graph) every configured backend's projection.

For a stack started with the `server` profile, run `COMPOSE_PROFILES=server make upgrade` instead.

If the new version can no longer read your evaluation data, start fresh with `make remove && make demo`.

### Optional: refresh the demo content

`make import-demo-data` updates the demo pages to the current demo set, the same content as the public demo wiki. It
overwrites your edits to those pages, which the page history keeps, and leaves pages you created alone.

## Method B: An existing MediaWiki

From the MediaWiki root, update the code, its dependencies, and the frontend bundle:

```sh
git -C extensions/NeoWiki pull
composer update

cd extensions/NeoWiki/resources/ext.neowiki
npm ci && npm run build
```

Then, back at the MediaWiki root:

```sh
php maintenance/run.php update --quick
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

[Rebuild](maintenance.md#rebuilding-the-graph) after every upgrade: with no release notes there is no way to tell
whether the new version changed the projected shape, and rebuilds are quick at evaluation scale.
