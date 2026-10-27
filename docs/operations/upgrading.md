---
title: Upgrading
order: 2
---

# Upgrading NeoWiki

Before you start, [back up](maintenance.md#backups) your wiki and read the
[release notes](https://github.com/ProfessionalWiki/NeoWiki/blob/master/RELEASE-NOTES.md) of every version after yours:
they list any step an upgrade needs beyond the ones below.

## Upgrading a Docker install

### 1. Get the new release

- **From an unpacked release:** download and unpack the newest
  [release](https://github.com/ProfessionalWiki/NeoWiki/releases/latest). Copy your `Docker/.env` into it, and carry
  over any other change you made to the old copy, such as a mounted `LocalSettings.php`. Then delete the old copy's
  directory; running `make remove` in it would delete your data.
- **From a clone of the repository:** check out the release's tag, as in `git fetch --tags && git checkout 0.2.0`.

### 2. Upgrade the stack

Run this from the release's root, adding the profiles and services you start the stack with, as in
`COMPOSE_PROFILES=server make upgrade services=qlever,neo`:

```sh
make upgrade
```

This pulls the demo image of that version, restarts the stack on it, runs MediaWiki's updater, and
[rebuilds](maintenance.md#rebuilding-the-graph) every configured backend's projection.

If the new version cannot read your pre-0.1 data, start fresh with `make remove && make demo`, which deletes that data.

### Optional: refresh the demo content

`make import-demo-data` updates the demo pages to the current demo set, the same content as the public demo wiki. It
overwrites your edits to those pages (still accessible via page history) and leaves pages you created alone. Then run
`make rebuild-graph-databases` so pages that predate the refresh reach every configured store.

## Upgrading an existing wiki

### 1. Update the extension

Run this from the MediaWiki root:

```sh
composer update neowiki/neowiki --no-dev -o
```

This stays on 0.1. To move to 0.2, run `COMPOSER=composer.local.json composer require --no-update neowiki/neowiki:^0.2`
first, then the command above.

### 2. Run the updater

Run this from the MediaWiki root:

```sh
php maintenance/run.php update --quick
```

### 3. Rebuild the graph stores

If you connected a graph store, rebuild it from the MediaWiki root:

```sh
php maintenance/run.php NeoWiki:RebuildGraphDatabases
```

### 4. Update the search index

If your wiki runs CirrusSearch, [update its search index](maintenance.md#making-subjects-searchable).

## Upgrading unreleased code

To follow master instead of releases:

- **Docker:** in your clone of the repository, run `git checkout master && git pull`, then continue with step 2 of
  [Upgrading a Docker install](#upgrading-a-docker-install).
- **An existing wiki:** update the code, its dependencies, and the frontend bundle from the MediaWiki root:

  ```sh
  git -C extensions/NeoWiki pull
  composer update

  cd extensions/NeoWiki/resources/ext.neowiki
  npm ci && npm run build
  ```

  Then continue with step 2 of [Upgrading an existing wiki](#upgrading-an-existing-wiki).
