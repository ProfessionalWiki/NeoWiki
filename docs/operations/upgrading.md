---
title: Upgrading
order: 2
---

# Upgrading NeoWiki

Before you start, [back up](maintenance.md#backups) your wiki and read the
[release notes](https://github.com/ProfessionalWiki/NeoWiki/blob/master/RELEASE-NOTES.md) of every version after yours:
they list any step an upgrade needs beyond the ones below.

## Upgrading a Docker install

On 0.1.0 or 0.1.1, follow "Upgrading from 0.1.0 or 0.1.1" below. Otherwise, run this from the directory the stack
runs from:

```sh
make upgrade version=latest
```

This moves the directory to the newest release, pulls that release's demo image, restarts the stack on it, runs
MediaWiki's updater, and [rebuilds](maintenance.md#rebuilding-the-graph) every configured backend's projection. To pick
a release, name it, as in `version=0.2.0`; a release older than yours is refused.

In a clone of the repository, this checks out the release's tag, and stops if that would overwrite a change of yours.
Otherwise it unpacks the release over the directory: your own files, such as `Docker/.env` and your
[settings](installation.md#change-mediawiki-settings), stay, and changes to the release's files, such as
`Docker/docker-compose.yml`, are overwritten.

`make upgrade` keeps the optional services the stack last started with.

If the new version cannot read your pre-0.1 data, start fresh with `make remove && make demo`, which deletes that data.

### Upgrading from 0.1.0 or 0.1.1

Their `make upgrade` ignores `version` and stays on the version you have, so move to the new release yourself this
once:

- **From an unpacked release:** download and unpack the newest
  [release](https://github.com/ProfessionalWiki/NeoWiki/releases/latest). Copy your `Docker/.env` into it, and carry
  over any other change you made to the old copy.
- **From a clone of the repository:** undo your changes to `Docker/docker-compose.yml`, if any, then check out the
  release's tag, as in `git fetch --tags && git checkout 0.2.0`.

If you mounted a `LocalSettings.php` of your own by uncommenting its line in `Docker/docker-compose.yml`, copy the
settings you changed or added into the new release's
[`Docker/LocalSettings.local.php`](installation.md#change-mediawiki-settings): the new release has no such line.

Then run `make upgrade` from the release's root. Once it has worked, delete an unpacked old copy's directory; running
`make remove` in it would delete your data.

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

This stays on 0.1. To move to 0.2, run this first, then the command above:

```sh
COMPOSER=composer.local.json composer require --no-update --no-plugins neowiki/neowiki:^0.2
```

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

- **Docker:** in your clone of the repository, run `git checkout master && git pull`, then `make upgrade`.
- **An existing wiki:** update the code, its dependencies, and the frontend bundle from the MediaWiki root:

  ```sh
  git -C extensions/NeoWiki pull
  composer update

  cd extensions/NeoWiki/resources/ext.neowiki
  npm ci && npm run build
  ```

  Then continue with step 2 of [Upgrading an existing wiki](#upgrading-an-existing-wiki).
