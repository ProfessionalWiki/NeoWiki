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

[Rebuild](maintenance.md#rebuilding-the-graph) after every upgrade: with no release notes you cannot tell
whether the new version changed the projected shape, and rebuilds are quick at evaluation scale.

If your wiki runs CirrusSearch, also [update its search index](maintenance.md#making-subjects-searchable).

If your install predates September 2026, run `php maintenance/run.php refreshLinks` once: pages that read another
page's Subjects recorded no dependency on it, and where the wiki holds restricted content, categories and page
properties that earlier parses derived from Subject data were recorded without a permission check. MediaWiki
rewrites those tables only on an edit, not on a view.

If your wiki uses MediaWiki's built-in database search on MySQL or SQLite and has pages saved before September
2026, run `php maintenance/run.php rebuildtextindex` once, so that those pages are
[findable by their Subjects](maintenance.md#making-subjects-searchable). It takes the wiki's search down for the
length of the run.

If your Subjects predate the optional Subject label, run
[clearing default Subject labels](maintenance.md#clearing-default-subject-labels) once, before that rebuild.

## Docker stack moved to Neo4j Community Edition, October 2026

The Docker stack now runs Neo4j Community Edition, which cannot open graph data written by the Enterprise Edition that
older stacks ran: their `neo` container keeps restarting, and `make upgrade` fails. Remove the Neo4j volume before
[upgrading](#upgrading-a-docker-install). That is safe on any stack, since `make upgrade` rebuilds everything Neo4j
holds from the wiki's pages. For a checkout named `NeoWiki` (otherwise, `docker volume ls` shows the volume's name):

```sh
make down
docker volume rm neowiki-neowiki_neo4j-data
```

## Docker wiki port bound to localhost, October 2026

The Docker stack's wiki port, 8484 by default, now only accepts connections from the machine the stack runs on. To
reach the wiki from other machines, use the [`server` profile](installation.md#optional-share-the-demo-with-others),
which serves it over HTTPS.

## New database tables, October 2026

Special:Subjects, relation-target suggestions, "Referenced by" and the Subject counts now read two new tables, and
work without Neo4j. `update.php` creates and fills them. After upgrading through MediaWiki's web updater, which does not
fill them, [rebuild the subject indexes](maintenance.md#rebuilding-the-subject-indexes).

## Demo Attendance Subjects now dependent, October 2026

The demo's `Schema:Attendance` now declares its Subjects [dependent](../glossary.md#dependent-subject), which a Schema
can do only when it is created. On a wiki with the earlier demo content, delete that page before you next
[refresh the demo content](#optional-refresh-the-demo-content); otherwise the refresh reports it as failed and keeps
the old Schema.

## Relations model changed, October 2026

A relation property now lists its target Schemas in `targetSchemas` and has no `relation` field
([Schema format](../api/schema-format.md#relation-relation)). One that still holds `targetSchema` keeps working; the
Schema editor rewrites it on the next save. Scripts and page-source edits that save Schema JSON need `targetSchemas`:
`"targetSchema": "City"` becomes `"targetSchemas": [ "City" ]`. `nw.getSchema` gives Lua modules `targetSchemas`, a
list, in place of `targetSchema` and `relation`, and `SubjectPicker` takes `targetSchemas` in place of `targetSchema`.

Relation edges in Neo4j and relation predicates in the native RDF now take the property's name instead of the
`relation` field's. Cypher and SPARQL that use an old name return nothing until edited: `` -[:`Born in`]-> `` becomes
`` -[:`Birth place`]-> ``, and `neo-prop:Born_in` becomes `neo-prop:Birth_place`. `make import-demo-data` covers the
demo content.

A Relation's `properties` are no longer read, and are dropped when its Subject is next saved.

## Neo4j 5.26.4 required, October 2026

If you use Neo4j, NeoWiki now needs 5.26.4 or later. Upgrade Neo4j first: on an older version, saving Subjects to the
graph fails or gives their Relations the wrong type.

## Removed setting, September 2026

`$wgNeoWikiDereferenceSubjectsToHostingPage` is removed, and there is no alias. A browser dereferencing a Subject's
concept URI lands where [Dereferencing subject IRIs](../api/rdf-export.md#dereferencing-subject-iris) says.

A `MediaWiki:NeoWiki` page still carrying `dereferenceSubjectsToHostingPage` keeps working, and the key does
nothing; saving the page is refused until you remove the key.

## Renamed accessors, September 2026

`nw.getChildSubjects` and `nw.getOtherSubjects` are both now
[`nw.getSubjects`](../authoring/lua-api.md#nwgetsubjectspagename), and there is no alias. The
replacement returns the page's Main Subject as well, first in the list, and marks every Subject with
an `isMainSubject` flag, so a module wanting only the rest filters on that. Modules and templates
already stored on a wiki keep calling the old names and render a script error until edited, so grep
your `Module:` namespace for both after upgrading. `make import-demo-data` covers the demo content.

`nw.getMainSubject` keeps its name and changes one answer. An unlabelled Main Subject on a page
titled by the id of *another* Subject on that page now reads as its Schema name, where it took the
page title before. Only creation without a usable label, and every creation on a subject-first wiki, makes such a title. The `displayName` of the REST API
and the `name` of the Subject's node in a graph store follow the same rule, and a page save or a
rebuild refreshes an existing node.

Two REST surfaces moved with it: `POST /neowiki/v0/page/{pageId}/childSubjects` is now
`POST /neowiki/v0/page/{pageId}/subjects`, and the `childSubjectIds` key of
`PUT /neowiki/v0/page/{pageId}/subjectsOrdering` is now `otherSubjectIds`. Bodies and responses are
otherwise unchanged. See the [REST API reference](../api/rest-api.md#pages-and-subjects).
