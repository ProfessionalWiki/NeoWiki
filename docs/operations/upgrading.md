---
title: Upgrading
order: 2
---

# Upgrading NeoWiki

Before you start, read the [release notes](https://github.com/ProfessionalWiki/NeoWiki/blob/master/RELEASE-NOTES.md)
of every version after yours: they list any step an upgrade needs beyond the ones below.

## Upgrading a Docker install

Update the copy of the repository the stack runs from: `git pull` in it, or re-download it if you took an archive.

Then, from the repository root:

```sh
make upgrade
```

This pulls the latest demo image, restarts the stack on it, runs MediaWiki's updater, and
[rebuilds](maintenance.md#rebuilding-the-graph) every configured backend's projection.

For a stack started with the `server` profile, run `COMPOSE_PROFILES=server make upgrade` instead.

If the new version cannot read your pre-0.1 data, start fresh with `make remove && make demo`, which deletes that data.

### Optional: refresh the demo content

`make import-demo-data` updates the demo pages to the current demo set, the same content as the public demo wiki. It
overwrites your edits to those pages (still accessible via page history) and leaves pages you created alone. Then run
`make rebuild-graph-databases` so pages that predate the refresh reach every configured store.

## Upgrading a manual install

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

If your wiki runs CirrusSearch, also [update its search index](maintenance.md#making-subjects-searchable).
