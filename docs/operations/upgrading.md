---
title: Upgrading
order: 2
---

# Upgrading NeoWiki

NeoWiki has no releases or version numbers yet, so upgrading means moving to the latest development state.

## Upgrading a Docker install

Update the copy of the repository the stack runs from: `git pull` in it, or re-download it if you took an archive.

Then, from the repository root:

```sh
make upgrade
```

This pulls the latest demo image, restarts the stack on it, runs MediaWiki's updater, and
[rebuilds](maintenance.md#rebuilding-the-graph) every configured backend's projection.

For a stack started with the `server` profile, run `COMPOSE_PROFILES=server make upgrade` instead.

If the new version can no longer read your evaluation data, start fresh with `make remove && make demo`.

If your Subjects predate the optional Subject label, run
[clearing default Subject labels](maintenance.md#clearing-default-subject-labels) once.

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

[Rebuild](maintenance.md#rebuilding-the-graph) after every upgrade: with no release notes there is no way to tell
whether the new version changed the projected shape, and rebuilds are quick at evaluation scale.

If your install predates September 2026 and holds restricted content, run `php maintenance/run.php refreshLinks`
once: categories and page properties that earlier parses derived from Subject data were recorded without a
permission check, and MediaWiki rewrites those tables only on an edit, not on a view.

If your Subjects predate the optional Subject label, run
[clearing default Subject labels](maintenance.md#clearing-default-subject-labels) once, before that rebuild.
