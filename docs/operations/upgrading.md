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

If your wiki runs CirrusSearch, also [update its search index](maintenance.md#making-subjects-searchable).

If your install predates September 2026 and holds restricted content, run `php maintenance/run.php refreshLinks`
once: categories and page properties that earlier parses derived from Subject data were recorded without a
permission check, and MediaWiki rewrites those tables only on an edit, not on a view.

If your wiki uses MediaWiki's built-in database search and has pages saved before September 2026, run
`php maintenance/run.php rebuildtextindex` once, so that those pages are
[findable by their Subjects](maintenance.md#making-subjects-searchable).

If your Subjects predate the optional Subject label, run
[clearing default Subject labels](maintenance.md#clearing-default-subject-labels) once, before that rebuild.

## Renamed accessors, September 2026

`nw.getChildSubjects` and `nw.getOtherSubjects` are both now
[`nw.getSubjects`](../authoring/lua-api.md#nwgetsubjectspagename), and there is no alias. The
replacement returns the page's Main Subject as well, first in the list, and marks every Subject with
an `isMainSubject` flag, so a module wanting only the rest filters on that. Modules and templates
already stored on a wiki keep calling the old names and render a script error until edited, so grep
your `Module:` namespace for both after upgrading. `make import-demo-data` covers the demo content.

`nw.getMainSubject` keeps its name and changes one answer. An unlabelled Main Subject on a page
titled by the id of *another* Subject on that page now reads as its Schema name, where it took the
page title before. Only entity-first creation makes such a title. The `displayName` of the REST API
and the `name` of the Subject's node in a graph store follow the same rule, and a page save or a
rebuild refreshes an existing node.

Two REST surfaces moved with it: `POST /neowiki/v0/page/{pageId}/childSubjects` is now
`POST /neowiki/v0/page/{pageId}/subjects`, and the `childSubjectIds` key of
`PUT /neowiki/v0/page/{pageId}/subjectsOrdering` is now `otherSubjectIds`. Bodies and responses are
otherwise unchanged. See the [REST API reference](../api/rest-api.md#pages-and-subjects).
